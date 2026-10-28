<?php

namespace App\Libraries;

use App\Models\AttendanceSessionModel;
use DateTimeImmutable;

/** Builds per-day and per-month on-site totals from attendance sessions. */
final class AttendanceReport
{
    /** @return array{from: string, to: string} */
    public static function monthRange(string $month): array
    {
        $start = new DateTimeImmutable($month . '-01');
        return ['from' => $start->format('Y-m-d'), 'to' => $start->modify('last day of this month')->format('Y-m-d')];
    }

    /** Sessions in the date range (employee view, mapped), optionally for one employee. */
    public static function sessions(string $from, string $to, string $employeeId = ''): array
    {
        $filters = ['from' => $from, 'to' => $to];
        if ($employeeId !== '') {
            $filters['employeeId'] = $employeeId;
        }
        $rows = (new AttendanceSessionModel())->listQuery($filters)->get()->getResultArray();
        return array_map(fn ($row) => AttendanceSessionModel::present($row, false), $rows);
    }

    /** One row per attendance date, summing every session that started that day. */
    public static function days(array $sessions): array
    {
        $days = [];
        foreach ($sessions as $session) {
            $date = $session['attendanceDate'];
            $day = $days[$date] ?? ['date' => $date, 'sessions' => 0, 'totalSeconds' => 0, 'firstCheckIn' => null, 'lastCheckOut' => null, 'exceptions' => []];
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
        ];
    }
}
