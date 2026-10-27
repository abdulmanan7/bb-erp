<?php

namespace App\Models;

use App\Libraries\Clock;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Model;

class LeaveRequestModel extends Model
{
    protected $table = 'leave_requests';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'id', 'employee_id', 'leave_type_id', 'start_date', 'end_date', 'days', 'reason',
        'status', 'admin_remarks', 'reviewed_by', 'reviewed_at', 'created_at', 'updated_at',
    ];

    private const OPEN_STATUSES = ['pending', 'approved'];

    public function listQuery(array $filters): BaseBuilder
    {
        $q = $this->db->table('leave_requests lr')
            ->select('lr.*, lt.name AS leave_type_name, e.name AS employee_name')
            ->join('leave_types lt', 'lt.id = lr.leave_type_id', 'left')
            ->join('employees e', 'e.id = lr.employee_id', 'left')
            ->orderBy('lr.created_at', 'DESC');

        if (! empty($filters['employeeId'])) {
            $q->where('lr.employee_id', $filters['employeeId']);
        }
        if (in_array($filters['status'] ?? '', ['pending', 'approved', 'rejected', 'cancelled'], true)) {
            $q->where('lr.status', $filters['status']);
        }
        return $q;
    }

    public function detailed(string $id, array $filters = []): ?array
    {
        return $this->listQuery($filters)->where('lr.id', $id)->get()->getRowArray();
    }

    public function overlaps(string $employeeId, string $startDate, string $endDate): bool
    {
        return $this->where('employee_id', $employeeId)
            ->whereIn('status', self::OPEN_STATUSES)
            ->where('start_date <=', $endDate)
            ->where('end_date >=', $startDate)
            ->first() !== null;
    }

    public static function map(array $r): array
    {
        return [
            'id'            => $r['id'],
            'employeeId'    => $r['employee_id'],
            'employeeName'  => $r['employee_name'] ?? '',
            'leaveTypeId'   => $r['leave_type_id'],
            'leaveTypeName' => $r['leave_type_name'] ?? '',
            'startDate'     => $r['start_date'],
            'endDate'       => $r['end_date'],
            'days'          => (int) $r['days'],
            'reason'        => $r['reason'],
            'status'        => $r['status'],
            'adminRemarks'  => $r['admin_remarks'],
            'reviewedBy'    => $r['reviewed_by'],
            'reviewedAt'    => Clock::iso($r['reviewed_at']),
            'createdAt'     => Clock::iso($r['created_at']),
        ];
    }
}
