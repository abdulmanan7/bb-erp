<?php

namespace App\Libraries;

use App\Models\AttendanceSessionModel;

/**
 * Closes open sessions once their employee's shift length has elapsed. The check-out time is the shift end,
 * the last known position is kept, and the session is flagged AUTO_CLOSED for admin review.
 */
final class AttendanceAutoClose
{
    public static function closeExpired(): int
    {
        $rows = db_connect()->table('attendance_sessions s')
            ->select('s.*, e.shift_hours AS employee_shift_hours')
            ->join('employees e', 'e.id = s.employee_id', 'left')
            ->where('s.status', 'active')
            ->get()->getResultArray();

        $sessions = new AttendanceSessionModel();
        $closed = 0;
        foreach ($rows as $row) {
            $shiftEnd = Clock::timestamp($row['check_in_at']) + (int) round(($row['employee_shift_hours'] ?? 8) * 3600);
            if (time() < $shiftEnd) {
                continue;
            }
            $now = Clock::now();
            $exceptions = array_unique(array_filter([...explode(',', $row['exceptions']), 'AUTO_CLOSED']));
            $didClose = $sessions->closeIfActive($row['id'], [
                'check_out_at'         => gmdate('Y-m-d H:i:s', $shiftEnd),
                'check_out_lat'        => $row['last_lat'],
                'check_out_lng'        => $row['last_lng'],
                'check_out_distance_m' => $row['last_distance_m'],
                'check_out_inside'     => $row['last_inside'],
                'duration_seconds'     => 0,
                'exceptions'           => implode(',', $exceptions),
                'updated_at'           => $now,
            ]);
            if ($didClose) {
                AttendanceReport::refreshDuration($row['id']);
                (new Notifier())->notify(
                    [$row['employee_id']],
                    'attendance',
                    'Attendance closed automatically',
                    'Your attendance was closed at the end of your shift. Contact your admin if the record is wrong.',
                    'attendance_session',
                    $row['id'],
                    "attendance_autoclose:{$row['id']}",
                );
                $closed++;
            }
        }
        return $closed;
    }
}
