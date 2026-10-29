<?php

namespace App\Controllers\Api;

use App\Libraries\AttendanceAutoClose;
use App\Libraries\Clock;
use App\Libraries\TrackingGaps;
use App\Models\AttendanceLocationModel;
use App\Models\AttendanceSessionModel;
use App\Models\EmployeeModel;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Attendance;
use DateTimeImmutable;
use DateTimeZone;

class AttendanceMonitor extends BaseApiController
{
    public function sessions(): ResponseInterface
    {
        AttendanceAutoClose::closeExpired();
        $filters = [
            'employeeId' => (string) $this->request->getGet('employeeId'),
            'projectId'  => (string) $this->request->getGet('projectId'),
            'status'     => (string) $this->request->getGet('status'),
            'from'       => $this->date($this->request->getGet('from')),
            'to'         => $this->date($this->request->getGet('to')),
        ];
        return $this->paginate(
            (new AttendanceSessionModel())->listQuery($filters),
            fn ($row) => AttendanceSessionModel::present($row, true)
        );
    }

    /** Route for one session: recorded points, out-of-boundary events and tracking interruptions. */
    public function track(string $id): ResponseInterface
    {
        $row = (new AttendanceSessionModel())->detailed($id);
        if (! $row) {
            return $this->fail('Attendance session not found.', 404);
        }

        $points = (new AttendanceLocationModel())->forSession($id);
        $end = $row['check_out_at'] ?? Clock::now();
        $gaps = TrackingGaps::between(
            $row['check_in_at'],
            array_column($points, 'captured_at'),
            $end,
            config(Attendance::class)->gapThresholdSeconds
        );
        $mapped = array_map(fn ($p) => $this->point($p), $points);

        return $this->json([
            'session'       => AttendanceSessionModel::present($row, true),
            'points'        => $mapped,
            'outOfBoundary' => array_values(array_filter($mapped, fn ($p) => ! $p['inside'])),
            'gaps'          => $gaps,
        ]);
    }

    /** Every employee with today's status: checked in, checked out, on leave, or not checked in. */
    public function roster(): ResponseInterface
    {
        AttendanceAutoClose::closeExpired();
        $today = (new DateTimeImmutable('now', new DateTimeZone(config(Attendance::class)->defaultTimezone)))->format('Y-m-d');
        $date = $this->date($this->request->getGet('date')) ?? $today;

        $sessions = new AttendanceSessionModel();
        $candidates = $sessions->listQuery(['from' => $date, 'to' => $date])->get()->getResultArray();
        if ($date === $today) {
            $candidates = [...$sessions->listQuery(['status' => 'active'])->get()->getResultArray(), ...$candidates];
        }
        $sessionByEmployee = [];
        foreach ($candidates as $row) {
            $sessionByEmployee[$row['employee_id']] ??= $row;
        }

        $leaves = db_connect()->table('leave_requests lr')
            ->select('lr.employee_id, lr.start_date, lr.end_date, lt.name AS leave_type_name')
            ->join('leave_types lt', 'lt.id = lr.leave_type_id', 'left')
            ->where('lr.status', 'approved')
            ->where('lr.start_date <=', $date)
            ->where('lr.end_date >=', $date)
            ->get()->getResultArray();
        $leaveByEmployee = array_column($leaves, null, 'employee_id');

        $rows = array_map(
            fn ($employee) => $this->rosterRow($employee, $sessionByEmployee[$employee['id']] ?? null, $leaveByEmployee[$employee['id']] ?? null),
            (new EmployeeModel())->orderBy('name', 'ASC')->findAll()
        );
        $count = fn (string $status) => count(array_filter($rows, fn ($r) => $r['status'] === $status));

        return $this->json([
            'date'      => $date,
            'counts'    => [
                'checkedIn'    => $count('checked_in'),
                'checkedOut'   => $count('checked_out'),
                'notCheckedIn' => $count('not_checked_in'),
                'onLeave'      => $count('on_leave'),
            ],
            'employees' => $rows,
        ]);
    }

    private function rosterRow(array $employee, ?array $row, ?array $leave): array
    {
        $session = $row ? AttendanceSessionModel::present($row, true) : null;
        $status = match (true) {
            $session !== null && $session['status'] === 'active' => 'checked_in',
            $leave !== null => 'on_leave',
            $session !== null => 'checked_out',
            default => 'not_checked_in',
        };
        return [
            'id'          => $employee['id'],
            'name'        => $employee['name'],
            'designation' => $employee['designation'],
            'status'      => $status,
            'leave'       => $leave ? ['typeName' => $leave['leave_type_name'] ?? '', 'startDate' => $leave['start_date'], 'endDate' => $leave['end_date']] : null,
            'session'     => $session,
        ];
    }

    private function point(array $p): array
    {
        return [
            'at'         => Clock::iso($p['captured_at']),
            'receivedAt' => Clock::iso($p['received_at']),
            'latitude'   => (float) $p['latitude'],
            'longitude'  => (float) $p['longitude'],
            'accuracy'   => (float) $p['accuracy'],
            'distanceM'  => (float) $p['distance_m'],
            'inside'     => (bool) $p['inside_boundary'],
            'mocked'     => (bool) $p['is_mocked'],
            'delayed'    => (bool) $p['is_delayed'],
        ];
    }
}
