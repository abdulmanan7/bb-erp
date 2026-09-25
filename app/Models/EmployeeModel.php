<?php

namespace App\Models;

use CodeIgniter\Model;

class EmployeeModel extends Model
{
    protected $table = 'employees';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'id', 'name', 'phone', 'designation', 'created_at',
    ];

    public function assignedHeadIds(string $employeeId): array
    {
        return array_column(
            $this->db->table('employee_heads')->select('head_id')->where('employee_id', $employeeId)->get()->getResultArray(),
            'head_id'
        );
    }

    public function setAssignedHeads(string $employeeId, array $headIds): void
    {
        $pivot = $this->db->table('employee_heads');
        $pivot->where('employee_id', $employeeId)->delete();
        $rows = [];
        foreach (array_unique(array_filter($headIds)) as $headId) {
            $rows[] = ['employee_id' => $employeeId, 'head_id' => (string) $headId];
        }
        if ($rows !== []) {
            $pivot->insertBatch($rows);
        }
    }

    public function map(array $r): array
    {
        $user = (new UserModel())->findByEmployee($r['id']);
        return [
            'id'           => $r['id'],
            'name'         => $r['name'],
            'phone'        => $r['phone'] ?? '',
            'designation'  => $r['designation'] ?? '',
            'loginEnabled' => $user !== null,
            'username'     => $user['username'] ?? '',
            'assignedHeads' => $this->assignedHeadIds($r['id']),
            'createdAt'    => $r['created_at'] ?? null,
        ];
    }

    public function allMapped(): array
    {
        return array_map(fn ($r) => $this->map($r), $this->orderBy('name', 'ASC')->findAll());
    }
}
