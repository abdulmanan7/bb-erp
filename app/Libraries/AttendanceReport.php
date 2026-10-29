<?php

namespace App\Libraries;

use App\Models\AttendanceSessionModel;
use Config\Attendance;
use DateTimeImmutable;

/** Builds per-day and per-month on-site totals from attendance sessions and their location trail. */
final class AttendanceReport
{
    /** @return array{from: string, to: string} */
    public static function monthRange(string $month): array
    {
        $start = new DateTimeImmutable($month . '-01');
        return ['from' => $start->format('Y-m-d'), 'to' => $start->modify('last day of this month')->format('Y-m-d')];
    }

    /** Sessions in the date range (mapped). Durations are the stored on-site seconds. */
    public static function sessions(string $from, string $to, string $employeeId = ''): array
    {
        $filters = ['from' => $from, 'to' => $to];
        if ($employeeId !== '') {
            $filters['employeeId'] = $employeeId;
        }
        $rows = (new AttendanceSessionModel())->listQuery($filters)->get()->getResultArray();
        return array_map(fn ($row) => AttendanceSessionModel::present($row, false), $rows);
    }

    /**
     * Seconds the employee was inside the project boundary. Time counts only between two consecutive
     * inside points; gaps longer than the tracking threshold and any leg that leaves the site are excluded.
     * Without any location points, a closed session counts check-in to check-out when both ends are inside.
     * An open session (no check-out) stops counting at check-in plus the employee's shift length.
     */
    public static function onSiteSeconds(array $row, array $points): int
    {
        if ($points === []) {
            $closedInside = $row['check_out_at'] !== null && $row['check_in_inside'] && $row['check_out_inside'];
            return $closedInside ? max(0, Clock::timestamp($row['check_out_at']) - Clock::timestamp($row['check_in_at'])) : 0;
        }

        $timeline = [[Clock::timestamp($row['check_in_at']), (bool) $row['check_in_inside']]];
        foreach ($points as $point) {
            $timeline[] = [Clock::timestamp($point['captured_at']), (bool) $point['inside_boundary']];
        }
        if ($row['check_out_at'] !== null) {
            $timeline[] = [Clock::timestamp($row['check_out_at']), (bool) $row['check_out_inside']];
        }
        usort($timeline, fn ($a, $b) => $a[0] <=> $b[0]);

        $maxGap = config(Attendance::class)->gapThresholdSeconds;
        $deadline = $row['status'] === 'active'
            ? Clock::timestamp($row['check_in_at']) + (int) round(($row['employee_shift_hours'] ?? 8) * 3600)
            : null;
        $seconds = 0;
        for ($i = 1, $count = count($timeline); $i < $count; $i++) {
            [$from, $fromInside] = $timeline[$i - 1];
            [$to, $toInside] = $timeline[$i];
            $gap = $to - $from;
            if ($fromInside && $toInside && $gap > 0 && $gap <= $maxGap) {
                $seconds += $deadline === null ? $gap : max(0, min($to, $deadline) - $from);
            }
        }
        return $seconds;
    }

    /** Recalculates and stores a session's on-site seconds from its current location trail. */
    public static function refreshDuration(string $sessionId): void
    {
        $db = db_connect();
        $row = $db->table('attendance_sessions s')
            ->select('s.*, e.shift_hours AS employee_shift_hours')
            ->join('employees e', 'e.id = s.employee_id', 'left')
            ->where('s.id', $sessionId)
            ->get()->getRowArray();
        if (! $row) {
            return;
        }
        $points = $db->table('attendance_location_logs')
            ->select('captured_at, inside_boundary')
            ->where('session_id', $sessionId)
            ->orderBy('captured_at', 'ASC')
            ->get()->getResultArray();

        $db->table('attendance_sessions')->where('id', $sessionId)->update([
            'duration_seconds' => self::onSiteSeconds($row, $points),
        ]);
    }

    /** One row per attendance date, summing every session that started that day. */
    public static function days(array $sessions): array
    {
        $days = [];
        foreach ($sessions as $session) {
            $date = $session['attendanceDate'];
            $day = $days[$date] ?? ['date' => $date, 'sessions' => 0, 'totalSeconds' => 0, 'firstCheckIn' => null, 'lastCheckOut' => null, 'openSessions' => 0, 'exceptions' => []];
            $day['sessions']++;
            $day['totalSeconds'] += $session['durationSeconds'];
            $checkIn = $session['checkIn']['at'];
            $checkOut = $session['checkOut']['at'] ?? null;
            if ($day['firstCheckIn'] === null || $checkIn < $day['firstCheckIn']) {
                $day['firstCheckIn'] = $checkIn;
            }
            if ($checkOut !== null && ($day['lastCheckOut'] === null || $checkOut > $day['lastCheckOut'])) {
                $day['lastCheckOut'] = $checkOut;
            }
            if ($session['status'] === 'active') {
                $day['openSessions']++;
            }
            $day['exceptions'] = array_values(array_unique([...$day['exceptions'], ...$session['exceptions']]));
            $days[$date] = $day;
        }
        ksort($days);
        return array_values($days);
    }

    public static function summary(array $days): array
    {
        $seconds = array_sum(array_column($days, 'totalSeconds'));
        return [
            'daysPresent'        => count($days),
            'sessions'           => array_sum(array_column($days, 'sessions')),
            'totalSeconds'       => $seconds,
            'totalHours'         => round($seconds / 3600, 2),
            'daysWithExceptions' => count(array_filter($days, fn ($day) => $day['exceptions'] !== [])),
            'openSessions'       => array_sum(array_column($days, 'openSessions')),
        ];
    }
}
