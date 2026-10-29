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

    /** Sessions in the date range (mapped), with on-site seconds derived from the location trail. */
    public static function sessions(string $from, string $to, string $employeeId = ''): array
    {
        $filters = ['from' => $from, 'to' => $to];
        if ($employeeId !== '') {
            $filters['employeeId'] = $employeeId;
        }
        $rows = (new AttendanceSessionModel())->listQuery($filters)->get()->getResultArray();
        $points = self::pointsBySession(array_column($rows, 'id'));

        return array_map(function ($row) use ($points) {
            $session = AttendanceSessionModel::present($row, false);
            $session['durationSeconds'] = self::onSiteSeconds($row, $points[$row['id']] ?? []);
            return $session;
        }, $rows);
    }

    /**
     * Seconds the employee was inside the project boundary. Time counts only between two consecutive
     * inside points; gaps longer than the tracking threshold and any leg that leaves the site are excluded.
     * Without any location points, a closed session counts check-in to check-out when both ends are inside.
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
        $seconds = 0;
        for ($i = 1, $count = count($timeline); $i < $count; $i++) {
            [$from, $fromInside] = $timeline[$i - 1];
            [$to, $toInside] = $timeline[$i];
            $gap = $to - $from;
            if ($fromInside && $toInside && $gap > 0 && $gap <= $maxGap) {
                $seconds += $gap;
            }
        }
        return $seconds;
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

    /** @return array<string, list<array{captured_at: string, inside_boundary: int}>> */
    private static function pointsBySession(array $sessionIds): array
    {
        if ($sessionIds === []) {
            return [];
        }
        $grouped = [];
        $rows = db_connect()->table('attendance_location_logs')
            ->select('session_id, captured_at, inside_boundary')
            ->whereIn('session_id', $sessionIds)
            ->orderBy('captured_at', 'ASC')
            ->get()->getResultArray();
        foreach ($rows as $point) {
            $grouped[$point['session_id']][] = $point;
        }
        return $grouped;
    }
}
