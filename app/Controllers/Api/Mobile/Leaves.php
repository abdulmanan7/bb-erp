<?php

namespace App\Controllers\Api\Mobile;

use App\Controllers\Api\BaseApiController;
use App\Models\LeaveRequestModel;
use App\Models\LeaveTypeModel;
use CodeIgniter\HTTP\ResponseInterface;

class Leaves extends BaseApiController
{
    private const MAX_DAYS = 365;

    public function types(): ResponseInterface
    {
        $types = array_map(fn ($t) => ['id' => $t['id'], 'name' => $t['name']], (new LeaveTypeModel())->activeTypes());
        return $this->json(['leaveTypes' => $types]);
    }

    public function index(): ResponseInterface
    {
        $filters = [
            'employeeId' => $this->user()['id'],
            'status'     => (string) $this->request->getGet('status'),
        ];
        return $this->paginate((new LeaveRequestModel())->listQuery($filters), [LeaveRequestModel::class, 'map']);
    }

    public function show(string $id): ResponseInterface
    {
        $row = (new LeaveRequestModel())->detailed($id, ['employeeId' => $this->user()['id']]);
        return $row
            ? $this->json(['leave' => LeaveRequestModel::map($row)])
            : $this->fail('Leave request not found.', 404);
    }

    public function create(): ResponseInterface
    {
        $b = $this->body();
        $employeeId = $this->user()['id'];

        if (! (new LeaveTypeModel())->findActive((string) ($b['leaveTypeId'] ?? ''))) {
            return $this->fail('Select a valid leave type.');
        }
        $start = $this->date($b['startDate'] ?? null);
        $end = $this->date($b['endDate'] ?? null);
        if ($start === null || $end === null) {
            return $this->fail('Enter valid start and end dates (YYYY-MM-DD).');
        }
        if ($end < $start) {
            return $this->fail('The end date cannot be before the start date.');
        }
        $days = (int) ((strtotime($end) - strtotime($start)) / 86400) + 1;
        if ($days > self::MAX_DAYS) {
            return $this->fail('A single leave request cannot exceed ' . self::MAX_DAYS . ' days.');
        }
        $reason = trim((string) ($b['reason'] ?? ''));
        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 500) {
            return $this->fail('The reason must be between 5 and 500 characters.');
        }

        $m = new LeaveRequestModel();
        if ($m->overlaps($employeeId, $start, $end)) {
            return $this->fail('You already have a pending or approved leave in these dates.', 409, 'LEAVE_OVERLAP');
        }

        $id = $this->newId();
        $now = $this->now();
        $m->insert([
            'id'            => $id,
            'employee_id'   => $employeeId,
            'leave_type_id' => $b['leaveTypeId'],
            'start_date'    => $start,
            'end_date'      => $end,
            'days'          => $days,
            'reason'        => $reason,
            'status'        => 'pending',
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);
        return $this->json(['leave' => LeaveRequestModel::map($m->detailed($id))], 201);
    }

    public function cancel(string $id): ResponseInterface
    {
        $m = new LeaveRequestModel();
        $leave = $m->detailed($id, ['employeeId' => $this->user()['id']]);
        if (! $leave) {
            return $this->fail('Leave request not found.', 404);
        }
        if ($leave['status'] !== 'pending') {
            return $this->fail('Only pending leave requests can be cancelled.', 409, 'LEAVE_NOT_CANCELLABLE');
        }

        $m->update($id, ['status' => 'cancelled', 'updated_at' => $this->now()]);
        return $this->json(['leave' => LeaveRequestModel::map($m->detailed($id))]);
    }
}
