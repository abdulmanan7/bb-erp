<?php

namespace App\Controllers\Api;

use App\Models\SalarySlipModel;
use CodeIgniter\HTTP\ResponseInterface;

class Salary extends BaseApiController
{
    public function create(): ResponseInterface
    {
        $b = $this->body();
        $name = trim((string) ($b['employeeName'] ?? ''));
        if ($name === '') {
            return $this->fail('Employee name required.');
        }
        $m = new SalarySlipModel();
        $id = $this->newId();
        $m->insert($this->rowData($b) + ['id' => $id, 'created_at' => $this->now()]);
        return $this->json(['slip' => SalarySlipModel::map($m->find($id))]);
    }

    public function update($id = null): ResponseInterface
    {
        $m = new SalarySlipModel();
        if (! $m->find($id)) {
            return $this->fail('Slip not found.', 404);
        }
        $b = $this->body();
        if (trim((string) ($b['employeeName'] ?? '')) === '') {
            return $this->fail('Employee name required.');
        }
        $m->update($id, $this->rowData($b));
        return $this->json(['slip' => SalarySlipModel::map($m->find($id))]);
    }

    public function delete($id = null): ResponseInterface
    {
        $m = new SalarySlipModel();
        if (! $m->find($id)) {
            return $this->fail('Slip not found.', 404);
        }
        $m->delete($id);
        return $this->json(['ok' => true]);
    }

    private function rowData(array $b): array
    {
        $basic = (float) ($b['basic'] ?? 0);
        $allowance = (float) ($b['allowance'] ?? 0);
        $deduction = (float) ($b['deduction'] ?? 0);
        $bonus = (float) ($b['bonus'] ?? 0);
        return [
            'employee_id'   => (string) ($b['employeeId'] ?? ''),
            'employee_name' => trim((string) ($b['employeeName'] ?? '')),
            'designation'   => trim((string) ($b['designation'] ?? '')),
            'phone'         => trim((string) ($b['phone'] ?? '')),
            'sal_month'     => min(12, max(1, (int) ($b['month'] ?? 1))),
            'sal_year'      => (int) ($b['year'] ?? (int) date('Y')),
            'basic'         => $basic,
            'allowance'     => $allowance,
            'deduction'     => $deduction,
            'bonus'         => $bonus,
            'total'         => $basic + $allowance - $deduction + $bonus,
        ];
    }
}
