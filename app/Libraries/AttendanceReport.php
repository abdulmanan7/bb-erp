<?php

namespace App\Libraries;

use App\Models\AttendanceSessionModel;
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
     * Seconds on site. Time between two consecutive points counts when both ends are inside the boundary,
     * however long the gap, so weak internet or a dead battery does not zero the day. Time after the last
     * recorded point is not counted, and an auto-closed session has no check-out endpoint. A manual check-out
     * counts from the last point when it is inside. An open session stops at check-in plus the shift length.
     */
    public static function onSiteSeconds(array $row, array $points): int
    {
        $autoClosed = str_contains((string) $row['exceptions'], 'AUTO_CLOSED');
        $timeline = [[Clock::timestamp($row['check_in_at']), (bool) $row['check_in_inside']]];
        foreach ($points as $point) {
            $timeline[] = [Clock::timestamp($point['captured_at']), (bool) $point['inside_boundary']];
        }
        if ($row['check_out_at'] !== null && ! $autoClosed) {
            $timeline[] = [Clock::timestamp($row['check_out_at']), (bool) $row['check_out_inside']];
        }
        usort($timeline, fn ($a, $b) => $a[0] <=> $b[0]);

        $deadline = $row['status'] === 'active'
            ? Clock::timestamp($row['check_in_at']) + (int) round(($row['employee_shift_hours'] ?? 8) * 3600)
            : null;
        $seconds = 0;
        for ($i = 1, $count = count($timeline); $i < $count; $i++) {
            [$from, $fromInside] = $timeline[$i - 1];
            [$to, $toInside] = $timeline[$i];
            if ($fromInside && $toInside && $to > $from) {
                $seconds += $deadline === null ? $to - $from : max(0, min($to, $deadline) - $from);
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
            $day = $days[$date] ?? ['date' => $date, 'sessions' => 0, 'totalSeconds' => 0, 'firstCheckIn' => null, 'lateMinutes' => null, 'isLate' => false, 'lastCheckOut' => null, 'openSessions' => 0, 'exceptions' => []];
            $day['sessions']++;
            $day['totalSeconds'] += $session['durationSeconds'];
            $checkIn = $session['checkIn']['at'];
            $checkOut = $session['checkOut']['at'] ?? null;
            if ($day['firstCheckIn'] === null || $checkIn < $day['firstCheckIn']) {
                $day['firstCheckIn'] = $checkIn;
                $day['lateMinutes'] = $session['lateMinutes'];
                $day['isLate'] = $session['isLate'];
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
            'daysLate'           => count(array_filter($days, fn ($day) => $day['isLate'])),
            'openSessions'       => array_sum(array_column($days, 'openSessions')),
        ];
    }
}
