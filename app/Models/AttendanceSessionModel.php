<?php

namespace App\Models;

use App\Libraries\Clock;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Model;
use Config\Attendance;

class AttendanceSessionModel extends Model
{
    protected $table = 'attendance_sessions';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'id', 'employee_id', 'project_id', 'attendance_date', 'status',
        'check_in_at', 'check_in_lat', 'check_in_lng', 'check_in_accuracy', 'check_in_distance_m',
        'check_in_inside', 'check_in_event_id', 'check_in_device',
        'check_out_at', 'check_out_lat', 'check_out_lng', 'check_out_accuracy', 'check_out_distance_m',
        'check_out_inside', 'check_out_event_id', 'duration_seconds', 'exceptions',
        'last_location_at', 'last_lat', 'last_lng', 'last_distance_m', 'last_inside', 'last_delayed',
        'created_at', 'updated_at',
    ];

    public function activeFor(string $employeeId): ?array
    {
        return $this->where(['employee_id' => $employeeId, 'status' => 'active'])->first();
    }

    public function detailed(string $id, array $filters = []): ?array
    {
        return $this->listQuery($filters)->where('s.id', $id)->get()->getRowArray();
    }

    /** Closes only if still active, so concurrent or repeated check-outs cannot both succeed. */
    public function closeIfActive(string $id, array $data): bool
    {
        $this->db->table($this->table)->where(['id' => $id, 'status' => 'active'])->update($data + ['status' => 'closed']);
        return $this->db->affectedRows() === 1;
    }

    public function listQuery(array $filters): BaseBuilder
    {
        $q = $this->db->table('attendance_sessions s')
            ->select("s.*, e.name AS employee_name, e.designation AS employee_designation, e.shift_hours AS employee_shift_hours,
                p.name AS project_name, p.timezone AS project_timezone, p.latitude AS project_lat,
                p.longitude AS project_lng, p.radius_m AS project_radius_m,
                (pa.status = 'active') AS assignment_active")
            ->join('employees e', 'e.id = s.employee_id', 'left')
            ->join('projects p', 'p.id = s.project_id', 'left')
            ->join('project_assignments pa', 'pa.project_id = s.project_id AND pa.employee_id = s.employee_id', 'left')
            ->orderBy('s.check_in_at', 'DESC');

        if (! empty($filters['employeeId'])) {
            $q->where('s.employee_id', $filters['employeeId']);
        }
        if (! empty($filters['projectId'])) {
            $q->where('s.project_id', $filters['projectId']);
        }
        if (in_array($filters['status'] ?? '', ['active', 'closed'], true)) {
            $q->where('s.status', $filters['status']);
        }
        if (! empty($filters['from'])) {
            $q->where('s.attendance_date >=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $q->where('s.attendance_date <=', $filters['to']);
        }
        return $q;
    }

    /** Employee view omits coordinates; admin view adds coordinates, project boundary and last-known location. */
    public static function present(array $r, bool $admin): array
    {
        $now = Clock::now();
        $active = $r['status'] === 'active';
        $data = [
            'id'               => $r['id'],
            'employeeId'       => $r['employee_id'],
            'employeeName'     => $r['employee_name'] ?? '',
            'designation'      => $r['employee_designation'] ?? '',
            'projectId'        => $r['project_id'],
            'projectName'      => $r['project_name'] ?? '',
            'projectTimezone'  => $r['project_timezone'] ?? '',
            'attendanceDate'   => $r['attendance_date'],
            'status'           => $r['status'],
            'assignmentActive' => (bool) ($r['assignment_active'] ?? false),
            'checkIn'          => self::point($r, 'check_in', $admin),
            'checkOut'         => self::point($r, 'check_out', $admin),
            'durationSeconds'  => $active ? Clock::timestamp($now) - Clock::timestamp($r['check_in_at']) : (int) $r['duration_seconds'],
            'exceptions'       => array_values(array_filter(explode(',', (string) $r['exceptions']))),
        ];
        if ($admin) {
            $data['projectLocation'] = [
                'latitude'  => (float) $r['project_lat'],
                'longitude' => (float) $r['project_lng'],
                'radiusM'   => (int) $r['project_radius_m'],
            ];
            $data['lastLocation'] = [
                'at'        => Clock::iso($r['last_location_at']),
                'latitude'  => self::num($r['last_lat']),
                'longitude' => self::num($r['last_lng']),
                'distanceM' => self::num($r['last_distance_m']),
                'inside'    => $r['last_inside'] === null ? null : (bool) $r['last_inside'],
                'state'     => self::trackingState($r, $now),
            ];
        }
        return $data;
    }

    private static function point(array $r, string $prefix, bool $admin): ?array
    {
        if ($r["{$prefix}_at"] === null) {
            return null;
        }
        $point = [
            'at'        => Clock::iso($r["{$prefix}_at"]),
            'accuracy'  => self::num($r["{$prefix}_accuracy"]),
            'distanceM' => self::num($r["{$prefix}_distance_m"]),
            'inside'    => $r["{$prefix}_inside"] === null ? null : (bool) $r["{$prefix}_inside"],
        ];
        if ($admin) {
            $point['latitude'] = self::num($r["{$prefix}_lat"]);
            $point['longitude'] = self::num($r["{$prefix}_lng"]);
        }
        return $point;
    }

    private static function trackingState(array $r, string $now): string
    {
        if ($r['last_location_at'] === null) {
            return 'unavailable';
        }
        if (Clock::timestamp($now) - Clock::timestamp($r['last_location_at']) > config(Attendance::class)->staleLocationSeconds) {
            return 'stale';
        }
        return (bool) $r['last_delayed'] ? 'delayed' : 'current';
    }

    private static function num(mixed $value): ?float
    {
        return $value === null ? null : (float) $value;
    }
}
