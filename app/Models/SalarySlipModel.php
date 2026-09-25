<?php

namespace App\Models;

use CodeIgniter\Model;

class SalarySlipModel extends Model
{
    protected $table = 'salary_slips';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'id', 'employee_id', 'employee_name', 'designation', 'phone',
        'sal_month', 'sal_year', 'basic', 'allowance', 'deduction',
        'bonus', 'total', 'created_at',
    ];

    public static function map(array $r): array
    {
        return [
            'id'           => $r['id'],
            'employeeId'   => $r['employee_id'] ?? '',
            'employeeName' => $r['employee_name'],
            'designation'  => $r['designation'] ?? '',
            'phone'        => $r['phone'] ?? '',
            'month'        => (int) $r['sal_month'],
            'year'         => (int) $r['sal_year'],
            'basic'        => (float) $r['basic'],
            'allowance'    => (float) ($r['allowance'] ?? 0),
            'deduction'    => (float) ($r['deduction'] ?? 0),
            'bonus'        => (float) ($r['bonus'] ?? 0),
            'total'        => (float) $r['total'],
            'createdAt'    => $r['created_at'] ?? null,
        ];
    }

    public function allMapped(): array
    {
        return array_map([self::class, 'map'], $this->orderBy('sal_year', 'ASC')->orderBy('sal_month', 'ASC')->findAll());
    }
}
