<?php

namespace App\Controllers\Api;

use App\Libraries\Notifier;
use App\Models\LeaveRequestModel;
use CodeIgniter\HTTP\ResponseInterface;

class Leaves extends BaseApiController
{
    private const REVIEW_STATUSES = ['approved', 'rejected'];

    public function index(): ResponseInterface
    {
        $filters = [
            'employeeId' => (string) $this->request->getGet('employeeId'),
            'status'     => (string) $this->request->getGet('status'),
        ];
        return $this->paginate((new LeaveRequestModel())->listQuery($filters), [LeaveRequestModel::class, 'map']);
    }

    public function review(string $id): ResponseInterface
    {
        $m = new LeaveRequestModel();
        $leave = $m->find($id);
        if (! $leave) {
            return $this->fail('Leave request not found.', 404);
        }
        $b = $this->body();
        $status = (string) ($b['status'] ?? '');
        if (! in_array($status, self::REVIEW_STATUSES, true)) {
            return $this->fail('Status must be approved or rejected.');
        }
        if ($leave['status'] !== 'pending') {
            return $this->fail('Only pending leave requests can be reviewed.', 409, 'LEAVE_NOT_PENDING');
        }

        $now = $this->now();
        $remarks = mb_substr(trim((string) ($b['remarks'] ?? '')), 0, 255);
        $m->update($id, [
            'status'        => $status,
            'admin_remarks' => $remarks,
            'reviewed_by'   => $this->user()['name'],
            'reviewed_at'   => $now,
            'updated_at'    => $now,
        ]);

        $row = $m->detailed($id);
        (new Notifier())->notify(
            [$leave['employee_id']],
            'leave',
            "Leave request {$status}",
            "Your {$row['leave_type_name']} from {$row['start_date']} to {$row['end_date']} was {$status}."
                . ($remarks !== '' ? " Remarks: {$remarks}" : ''),
            'leave',
            $id,
            "leave:{$id}:{$status}"
        );
        return $this->json(['leave' => LeaveRequestModel::map($row)]);
    }
}
