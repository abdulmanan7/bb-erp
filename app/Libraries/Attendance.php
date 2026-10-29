<?php

namespace App\Libraries;

use App\Models\AttendanceLocationModel;
use App\Models\AttendanceSessionModel;
use App\Models\ProjectAssignmentModel;
use App\Models\ProjectModel;
use CodeIgniter\Database\Exceptions\DatabaseException;
use Config\Attendance as AttendanceConfig;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

/** Server-side attendance rules. The client never decides whether a check-in or location is valid. */
final class Attendance
{
    public const EXC_OUTSIDE = 'OUTSIDE_BOUNDARY';
    public const EXC_POOR_ACCURACY = 'POOR_ACCURACY';
    public const EXC_STALE = 'STALE_LOCATION';
    public const EXC_MISSING = 'LOCATION_MISSING';
    public const EXC_MOCKED = 'MOCKED_LOCATION';

    private const WINDOW_TOLERANCE_SECONDS = 60;
    private const MAX_CLOCK_SKEW_SECONDS = 60;
    private const DUPLICATE_KEY_ERROR = 1062;
    private const EXIT_ALERT_COOLDOWN_SECONDS = 600;

    private AttendanceConfig $config;

    public function __construct(
        private readonly AttendanceSessionModel $sessions = new AttendanceSessionModel(),
        private readonly AttendanceLocationModel $locations = new AttendanceLocationModel(),
        private readonly ProjectModel $projects = new ProjectModel(),
        private readonly ProjectAssignmentModel $assignments = new ProjectAssignmentModel(),
        private readonly Notifier $notifier = new Notifier(),
    ) {
        $this->config = config(AttendanceConfig::class);
    }

    public function checkIn(array $employee, array $input): array
    {
        AttendanceAutoClose::closeExpired();
        $eventId = $this->eventId($input['eventId'] ?? null);
        if ($eventId !== null && $existing = $this->sessions->where(['employee_id' => $employee['id'], 'check_in_event_id' => $eventId])->first()) {
            return $existing;
        }

        $project = $this->projects->find((string) ($input['projectId'] ?? ''));
        if (! $project) {
            throw new AttendanceException('Project not found.', 'PROJECT_NOT_FOUND', 404);
        }
        if ($project['status'] !== 'active') {
            throw new AttendanceException('This project is not active.', 'PROJECT_INACTIVE');
        }
        if (! $this->assignments->isActiveFor($project['id'], $employee['id'])) {
            throw new AttendanceException('You are not assigned to this project.', 'PROJECT_NOT_ASSIGNED', 403);
        }
        if ($this->sessions->activeFor($employee['id'])) {
            throw new AttendanceException('You are already checked in.', 'ALREADY_CHECKED_IN', 409);
        }

        $fix = $this->fix($input);
        if ($fix === null) {
            throw new AttendanceException("We couldn't verify your current location. Please enable location services and try again.", 'LOCATION_REQUIRED');
        }
        if ($this->isStale($fix)) {
            throw new AttendanceException('Your location reading is too old. Please refresh your location and try again.', 'LOCATION_STALE');
        }
        if ($fix['accuracy'] > $this->config->maxAccuracyMeters) {
            throw new AttendanceException('GPS accuracy is too low. Move to an open area and try again.', 'POOR_ACCURACY');
        }
        if ($fix['mocked']) {
            throw new AttendanceException('Mock or simulated locations are not allowed.', 'MOCKED_LOCATION');
        }
        $distance = $this->distance($project, $fix);
        if ($distance > $project['radius_m']) {
            throw new AttendanceException('You are currently outside the allowed attendance area for this project. Please move within the project location and try again.', 'OUTSIDE_BOUNDARY');
        }

        $now = Clock::now();
        $id = bin2hex(random_bytes(16));
        $row = [
            'id'                  => $id,
            'employee_id'         => $employee['id'],
            'project_id'          => $project['id'],
            'attendance_date'     => (new DateTimeImmutable('now', new DateTimeZone($project['timezone'])))->format('Y-m-d'),
            'status'              => 'active',
            'check_in_at'         => $now,
            'check_in_lat'        => $fix['latitude'],
            'check_in_lng'        => $fix['longitude'],
            'check_in_accuracy'   => $fix['accuracy'],
            'check_in_distance_m' => round($distance, 2),
            'check_in_inside'     => 1,
            'check_in_event_id'   => $eventId,
            'check_in_device'     => (string) ($input['deviceName'] ?? ''),
            'created_at'          => $now,
            'updated_at'          => $now,
        ] + $this->lastLocation($fix, $project, $distance, $now);

        try {
            $saved = $this->sessions->insert($row, false);
        } catch (DatabaseException) {
            $saved = false;
        }
        if (! $saved) {
            if ((int) db_connect()->error()['code'] === self::DUPLICATE_KEY_ERROR) {
                throw new AttendanceException('You are already checked in.', 'ALREADY_CHECKED_IN', 409);
            }
            throw new RuntimeException('Could not record check-in.');
        }
        return $this->sessions->find($id);
    }

    public function checkOut(array $employee, array $input): array
    {
        $eventId = $this->eventId($input['eventId'] ?? null);
        if ($eventId !== null && $existing = $this->sessions->where(['employee_id' => $employee['id'], 'check_out_event_id' => $eventId])->first()) {
            return $existing;
        }

        $session = $this->sessions->activeFor($employee['id']);
        if (! $session) {
            throw new AttendanceException('You are not checked in.', 'NO_ACTIVE_SESSION', 409);
        }
        $project = $this->projects->find($session['project_id']);

        $now = Clock::now();
        $fix = $this->fix($input);
        $exceptions = [];
        $point = [];
        if ($fix === null) {
            $exceptions[] = self::EXC_MISSING;
        } else {
            $distance = $project ? $this->distance($project, $fix) : null;
            if ($distance !== null && $distance > $project['radius_m']) {
                $exceptions[] = self::EXC_OUTSIDE;
            }
            if ($fix['accuracy'] > $this->config->maxAccuracyMeters) {
                $exceptions[] = self::EXC_POOR_ACCURACY;
            }
            if ($this->isStale($fix)) {
                $exceptions[] = self::EXC_STALE;
            }
            if ($fix['mocked']) {
                $exceptions[] = self::EXC_MOCKED;
            }
            $point = [
                'check_out_lat'        => $fix['latitude'],
                'check_out_lng'        => $fix['longitude'],
                'check_out_accuracy'   => $fix['accuracy'],
                'check_out_distance_m' => $distance === null ? null : round($distance, 2),
                'check_out_inside'     => $distance === null ? null : (int) ($distance <= $project['radius_m']),
            ] + $this->lastLocation($fix, $project, $distance, $now);
        }

        $closed = $this->sessions->closeIfActive($session['id'], $point + [
            'check_out_at'       => $now,
            'check_out_event_id' => $eventId,
            'duration_seconds'   => max(0, Clock::timestamp($now) - Clock::timestamp($session['check_in_at'])),
            'exceptions'         => $this->mergeExceptions($session['exceptions'], $exceptions),
            'updated_at'         => $now,
        ]);
        if (! $closed) {
            throw new AttendanceException('You are not checked in.', 'NO_ACTIVE_SESSION', 409);
        }
        AttendanceReport::refreshDuration($session['id']);
        return $this->sessions->find($session['id']);
    }

    /**
     * Accepts a batch of queued device points for one session. Returns per-event results so the app can
     * drop accepted and duplicate records from its offline queue and keep rejected ones for review.
     */
    public function recordLocations(array $employee, array $input): array
    {
        $session = $this->sessions->find((string) ($input['sessionId'] ?? ''));
        if (! $session || $session['employee_id'] !== $employee['id']) {
            throw new AttendanceException('Attendance session not found.', 'SESSION_NOT_FOUND', 404);
        }
        $batch = $input['locations'] ?? null;
        if (! is_array($batch) || $batch === [] || count($batch) > $this->config->maxLocationBatch) {
            throw new AttendanceException('Send between 1 and ' . $this->config->maxLocationBatch . ' locations.', 'INVALID_BATCH');
        }

        $project = $this->projects->find($session['project_id']);
        $now = Clock::now();
        $windowStart = Clock::timestamp($session['check_in_at']) - self::WINDOW_TOLERANCE_SECONDS;
        $windowEnd = Clock::timestamp($session['check_out_at'] ?? $now) + self::WINDOW_TOLERANCE_SECONDS;

        $candidates = [];
        $rejected = [];
        foreach ($batch as $point) {
            $point = is_array($point) ? $point : [];
            $eventId = $this->eventId($point['eventId'] ?? null);
            $fix = $this->fix($point);
            if ($eventId === null) {
                $rejected[] = ['eventId' => null, 'reason' => 'INVALID_EVENT'];
            } elseif ($fix === null) {
                $rejected[] = ['eventId' => $eventId, 'reason' => 'INVALID_LOCATION'];
            } elseif (Clock::timestamp($fix['capturedAt']) < $windowStart || Clock::timestamp($fix['capturedAt']) > $windowEnd) {
                $rejected[] = ['eventId' => $eventId, 'reason' => 'OUTSIDE_SESSION_WINDOW'];
            } else {
                $candidates[$eventId] = $fix;
            }
        }

        $duplicates = $this->locations->existingEventIds($employee['id'], array_keys($candidates));
        $rows = [];
        foreach (array_diff_key($candidates, array_flip($duplicates)) as $eventId => $fix) {
            $distance = $project ? $this->distance($project, $fix) : 0.0;
            $rows[] = [
                'event_id'        => $eventId,
                'session_id'      => $session['id'],
                'employee_id'     => $employee['id'],
                'project_id'      => $session['project_id'],
                'latitude'        => $fix['latitude'],
                'longitude'       => $fix['longitude'],
                'accuracy'        => $fix['accuracy'],
                'distance_m'      => round($distance, 2),
                'inside_boundary' => (int) ($project && $distance <= $project['radius_m']),
                'is_mocked'       => (int) $fix['mocked'],
                'captured_at'     => $fix['capturedAt'],
                'received_at'     => $now,
                'is_delayed'      => (int) $this->isDelayed($fix, $now),
            ];
        }

        usort($rows, fn ($a, $b) => strcmp($a['captured_at'], $b['captured_at']));
        if ($rows !== []) {
            db_connect()->table('attendance_location_logs')->ignore(true)->insertBatch($rows);
            $this->applyBatchToSession($session, $rows);
            $this->notifyBoundaryExit($session, $rows);
            AttendanceReport::refreshDuration($session['id']);
        }

        return [
            'accepted'   => array_column($rows, 'event_id'),
            'duplicates' => $duplicates,
            'rejected'   => $rejected,
        ];
    }

    /** @param list<array> $rows sorted by captured_at */
    private function notifyBoundaryExit(array $session, array $rows): void
    {
        $wasInside = (bool) $session['last_inside'];
        foreach ($rows as $row) {
            $isInside = (bool) $row['inside_boundary'];
            if ($wasInside && ! $isInside) {
                $cacheKey = "attendance_exit_alert:{$session['id']}";
                if (! cache()->get($cacheKey)) {
                    $this->notifier->notify(
                        [$session['employee_id']],
                        'attendance',
                        'Left the site area',
                        'You are outside the project boundary. Your attendance is still running.',
                        'attendance_session',
                        $session['id'],
                        "attendance_exit:{$row['event_id']}",
                    );
                    cache()->save($cacheKey, true, self::EXIT_ALERT_COOLDOWN_SECONDS);
                }
                return;
            }
            $wasInside = $isInside;
        }
    }

    private function applyBatchToSession(array $session, array $rows): void
    {
        $latest = end($rows);

        $update = [];
        if ($session['last_location_at'] === null || $latest['captured_at'] > $session['last_location_at']) {
            $update = [
                'last_location_at' => $latest['captured_at'],
                'last_lat'         => $latest['latitude'],
                'last_lng'         => $latest['longitude'],
                'last_distance_m'  => $latest['distance_m'],
                'last_inside'      => $latest['inside_boundary'],
                'last_delayed'     => $latest['is_delayed'],
            ];
        }
        if (in_array(1, array_column($rows, 'is_mocked'), true)) {
            $update['exceptions'] = $this->mergeExceptions($session['exceptions'], [self::EXC_MOCKED]);
        }
        if ($update !== []) {
            $this->sessions->update($session['id'], $update + ['updated_at' => Clock::now()]);
        }
    }

    /** @return array{latitude: float, longitude: float, accuracy: float, mocked: bool, capturedAt: string}|null */
    private function fix(array $input): ?array
    {
        $lat = $input['latitude'] ?? null;
        $lng = $input['longitude'] ?? null;
        $accuracy = $input['accuracy'] ?? null;
        if (! is_numeric($lat) || ! is_numeric($lng) || ! is_numeric($accuracy)) {
            return null;
        }
        $lat = (float) $lat;
        $lng = (float) $lng;
        $accuracy = (float) $accuracy;
        if (abs($lat) > 90 || abs($lng) > 180 || $accuracy < 0) {
            return null;
        }
        $capturedAt = Clock::fromInput($input['capturedAt'] ?? null);
        if ($capturedAt !== null && Clock::timestamp($capturedAt) > time() + self::MAX_CLOCK_SKEW_SECONDS) {
            $capturedAt = null;
        }
        return [
            'latitude'   => $lat,
            'longitude'  => $lng,
            'accuracy'   => $accuracy,
            'mocked'     => filter_var($input['isMocked'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'capturedAt' => $capturedAt ?? Clock::now(),
        ];
    }

    private function eventId(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^[A-Za-z0-9_-]{8,64}$/', $value) ? $value : null;
    }

    private function distance(array $project, array $fix): float
    {
        return Geo::distanceMeters((float) $project['latitude'], (float) $project['longitude'], $fix['latitude'], $fix['longitude']);
    }

    private function isStale(array $fix): bool
    {
        return Clock::timestamp(Clock::now()) - Clock::timestamp($fix['capturedAt']) > $this->config->maxLocationAgeSeconds;
    }

    private function isDelayed(array $fix, string $now): bool
    {
        return Clock::timestamp($now) - Clock::timestamp($fix['capturedAt']) > $this->config->delayedAfterSeconds;
    }

    /** Session-level "last known location" fields, used by the admin monitor. */
    private function lastLocation(array $fix, ?array $project, ?float $distance, string $now): array
    {
        return [
            'last_location_at' => $fix['capturedAt'],
            'last_lat'         => $fix['latitude'],
            'last_lng'         => $fix['longitude'],
            'last_distance_m'  => $distance === null ? null : round($distance, 2),
            'last_inside'      => $project && $distance !== null ? (int) ($distance <= $project['radius_m']) : null,
            'last_delayed'     => (int) $this->isDelayed($fix, $now),
        ];
    }

    private function mergeExceptions(string $existing, array $codes): string
    {
        return implode(',', array_unique(array_filter(array_merge(explode(',', $existing), $codes))));
    }
}
