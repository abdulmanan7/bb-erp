<?php

namespace App\Controllers\Api;

use App\Models\EmployeeModel;
use App\Models\SalarySlipModel;
use App\Models\VoucherModel;
use CodeIgniter\HTTP\ResponseInterface;

class Salary extends BaseApiController
{
    private const SALARY_HEAD = 'aaebc5788557b160';

    public function create(): ResponseInterface
    {
        $b = $this->body();
        $name = trim((string) ($b['employeeName'] ?? ''));
        if ($name === '') {
            return $this->fail('Employee name required.');
        }
        $m = new SalarySlipModel();
        $id = $this->newId();
        $row = $this->rowData($b) + ['id' => $id, 'voucher_id' => '', 'created_at' => $this->now()];
        if ($row['status'] === 'paid') {
            $row['voucher_id'] = $this->postExpenseVoucher($row);
        }
        $m->insert($row);
        return $this->json(['slip' => SalarySlipModel::map($m->find($id))]);
    }

    public function update($id = null): ResponseInterface
    {
        $m = new SalarySlipModel();
        $existing = $m->find($id);
        if (! $existing) {
            return $this->fail('Slip not found.', 404);
        }
        $b = $this->body();
        if (trim((string) ($b['employeeName'] ?? '')) === '') {
            return $this->fail('Employee name required.');
        }
        $row = $this->rowData($b);
        if ($row['status'] === 'paid' && ! $existing['voucher_id']) {
            $row['voucher_id'] = $this->postExpenseVoucher($row);
        } elseif ($row['status'] === 'pending' && $existing['voucher_id']) {
            $this->removeVoucher($existing['voucher_id']);
            $row['voucher_id'] = '';
        }
        $m->update($id, $row);
        return $this->json(['slip' => SalarySlipModel::map($m->find($id))]);
    }

    public function markPaid($id = null): ResponseInterface
    {
        $m = new SalarySlipModel();
        $s = $m->find($id);
        if (! $s) {
            return $this->fail('Slip not found.', 404);
        }
        if ($s['status'] === 'paid') {
            return $this->fail('Slip is already paid.');
        }
        $m->update($id, [
            'status'     => 'paid',
            'voucher_id' => $this->postExpenseVoucher($s),
        ]);
        return $this->json(['slip' => SalarySlipModel::map($m->find($id))]);
    }

    /**
     * Creates a pending slip for every employee who doesn't already have one
     * for the given month/year, using their salary defaults.
     */
    public function generate(): ResponseInterface
    {
        $b = $this->body();
        $month = min(12, max(1, (int) ($b['month'] ?? date('n'))));
        $year = (int) ($b['year'] ?? date('Y'));

        $m = new SalarySlipModel();
        $created = 0;
        foreach ((new EmployeeModel())->findAll() as $e) {
            $dup = $m->where('employee_id', $e['id'])
                ->where('sal_month', $month)->where('sal_year', $year)->first();
            if ($dup) {
                continue;
            }
            $basic = (float) ($e['basic_salary'] ?? 0);
            $allowance = (float) ($e['allowance'] ?? 0);
            $deduction = (float) ($e['deduction'] ?? 0);
            $bonus = (float) ($e['bonus'] ?? 0);
            $m->insert([
                'id'            => $this->newId(),
                'employee_id'   => $e['id'],
                'employee_name' => $e['name'],
                'designation'   => $e['designation'] ?? '',
                'phone'         => $e['phone'] ?? '',
                'sal_month'     => $month,
                'sal_year'      => $year,
                'basic'         => $basic,
                'allowance'     => $allowance,
                'deduction'     => $deduction,
                'bonus'         => $bonus,
                'total'         => $basic + $allowance - $deduction + $bonus,
                'status'        => 'pending',
                'voucher_id'    => '',
                'created_at'    => $this->now(),
            ]);
            $created++;
        }
        return $this->json(['created' => $created]);
    }

    public function delete($id = null): ResponseInterface
    {
        $m = new SalarySlipModel();
        $s = $m->find($id);
        if (! $s) {
            return $this->fail('Slip not found.', 404);
        }
        $m->delete($id);
        if (! empty($s['voucher_id'])) {
            $this->removeVoucher($s['voucher_id']);
        }
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
            'status'        => ($b['status'] ?? '') === 'paid' ? 'paid' : 'pending',
        ];
    }

    /** Returns the created voucher id (or '' when skipped). */
    private function postExpenseVoucher(array $slip): string
    {
        $total = (float) ($slip['total'] ?? 0);
        if ($total <= 0) {
            return '';
        }
        $date = date('Y-m-d');
        $monthName = date('F', mktime(0, 0, 0, (int) $slip['sal_month'], 1));
        $m = new VoucherModel();
        $id = $this->newId();
        $m->insert([
            'id'          => $id,
            'no'          => $m->nextNo('Payment', $date),
            'type'        => 'Payment',
            'v_date'      => $date,
            'head_id'     => self::SALARY_HEAD,
            'sub_head_id' => '',
            'amount'      => $total,
            'total'       => $total,
            'paid'        => $total,
            'status'      => 'paid',
            'party'       => $slip['employee_name'],
            'description' => 'Salary ' . $monthName . ' ' . $slip['sal_year'],
            'attachment'  => '',
            'created_by'  => $this->user()['name'] ?? 'Admin',
            'created_at'  => $this->now(),
        ]);
        return $id;
    }

    private function removeVoucher(string $voucherId): void
    {
        (new VoucherModel())->delete($voucherId);
        foreach (glob(WRITEPATH . 'uploads/vouchers/' . $voucherId . '.*') ?: [] as $f) {
            @unlink($f);
        }
    }
}
