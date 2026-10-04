<?php

namespace App\Controllers\Api;

use App\Models\EmployeeModel;
use App\Models\HeadModel;
use App\Models\SalarySlipModel;
use App\Models\SubHeadModel;
use App\Models\VoucherModel;
use App\Models\VoucherPaymentModel;
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
            $ph = $this->paymentHead($b);
            if (is_string($ph)) {
                return $this->fail($ph);
            }
            $row['voucher_id'] = $this->postExpenseVoucher($row, $ph[0], $ph[1]);
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
        $row['voucher_id'] = $existing['voucher_id'];
        if ($row['status'] === 'paid') {
            $vouchers = new VoucherModel();
            $voucher = $existing['voucher_id'] ? $vouchers->find($existing['voucher_id']) : null;
            $ph = $this->paymentHead($b, is_array($voucher) ? $voucher : null);
            if (is_string($ph)) {
                return $this->fail($ph);
            }
            if ($voucher) {
                $vouchers->update($existing['voucher_id'], [
                    'head_id'     => $ph[0],
                    'sub_head_id' => $ph[1],
                    'amount'      => $row['total'],
                    'total'       => $row['total'],
                    'paid'        => $row['total'],
                    'party'       => $row['employee_name'],
                    'description' => 'Salary ' . date('F', mktime(0, 0, 0, $row['sal_month'], 1)) . ' ' . $row['sal_year'],
                ]);
            } else {
                $row['voucher_id'] = $this->postExpenseVoucher($row, $ph[0], $ph[1]);
            }
        } elseif ($existing['voucher_id']) {
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
        $ph = $this->paymentHead($this->body());
        if (is_string($ph)) {
            return $this->fail($ph);
        }
        $m->update($id, [
            'status'     => 'paid',
            'voucher_id' => $this->postExpenseVoucher($s, $ph[0], $ph[1]),
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

    /**
     * Resolves the expense head + sub-head the salary voucher posts to.
     * Defaults to the dedicated salary head (or the slip's existing voucher
     * head when none is provided). Returns [headId, subHeadId] or an error.
     */
    private function paymentHead(array $b, ?array $voucher = null)
    {
        $headId = (string) ($b['headId'] ?? '');
        if ($headId === '') {
            $headId = $voucher['head_id'] ?? self::SALARY_HEAD;
        }
        $head = (new HeadModel())->find($headId);
        if (! $head) {
            return 'Invalid expense head selected.';
        }
        if (($head['type'] ?? '') !== 'Expense') {
            return 'Salary must be posted to an expense head.';
        }
        $subId = (string) ($b['subHeadId'] ?? '');
        if ($subId === '' && $voucher && ($voucher['head_id'] ?? '') === $headId) {
            $subId = (string) ($voucher['sub_head_id'] ?? '');
        }
        if ($subId !== '' && ! (new SubHeadModel())->where('id', $subId)->where('head_id', $headId)->first()) {
            return 'Invalid sub-head selected.';
        }
        return [$headId, $subId];
    }

    /** Returns the created voucher id (or '' when skipped). */
    private function postExpenseVoucher(array $slip, string $headId, string $subHeadId): string
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
            'head_id'     => $headId,
            'sub_head_id' => $subHeadId,
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
        (new VoucherPaymentModel())->insert([
            'id'         => $this->newId(),
            'voucher_id' => $id,
            'amount'     => $total,
            'pay_date'   => $date,
            'note'       => 'Salary payment',
            'created_by' => $this->user()['name'] ?? 'Admin',
            'created_at' => $this->now(),
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
