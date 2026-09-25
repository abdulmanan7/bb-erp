<?php

namespace App\Controllers\Api;

use App\Models\EmployeeModel;
use App\Models\HeadModel;
use App\Models\InvoiceItemModel;
use App\Models\InvoiceModel;
use App\Models\SalarySlipModel;
use App\Models\SettingModel;
use App\Models\UserModel;
use App\Models\VoucherModel;
use CodeIgniter\HTTP\ResponseInterface;

class Backup extends BaseApiController
{
    private const TABLES = [
        'employee_heads', 'invoice_items', 'vouchers',
        'invoices', 'salary_slips', 'users', 'employees', 'heads', 'settings',
    ];

    public function export(): ResponseInterface
    {
        $users = array_map(
            [UserModel::class, 'exportMap'],
            (new UserModel())->findAll()
        );
        return $this->json([
            'settings'  => (new SettingModel())->mapped(),
            'users'     => $users,
            'employees' => (new EmployeeModel())->allMapped(),
            'heads'     => (new HeadModel())->allMapped(),
            'vouchers'  => (new VoucherModel())->allMapped(),
            'invoices'  => (new InvoiceModel())->allMapped(),
            'salary'    => (new SalarySlipModel())->allMapped(),
        ]);
    }

    public function import(): ResponseInterface
    {
        $b = $this->body();
        if (empty($b['settings']) || ! isset($b['vouchers'])) {
            return $this->fail('Invalid backup file.');
        }

        $db = db_connect();
        $db->transStart();
        foreach (self::TABLES as $t) {
            $db->table($t)->truncate();
        }
        $this->importSettings($b['settings']);
        $this->importHeads((array) ($b['heads'] ?? []));
        $this->importEmployees((array) ($b['employees'] ?? []));
        $this->importUsers((array) ($b['users'] ?? []), $b);
        $this->importVouchers((array) ($b['vouchers'] ?? []));
        $this->importInvoices((array) ($b['invoices'] ?? []));
        $this->importSalary((array) ($b['salary'] ?? []));
        $db->transComplete();

        if (! $db->transStatus()) {
            return $this->fail('Import failed — no data was changed.', 500);
        }
        return $this->json(['ok' => true]);
    }

    public function reset(): ResponseInterface
    {
        $db = db_connect();
        $db->transStart();
        foreach (self::TABLES as $t) {
            $db->table($t)->truncate();
        }
        $db->transComplete();
        $this->importSettings([]); // reseed defaults
        $this->seedAdmin();
        session()->destroy();
        return $this->json(['ok' => true]);
    }

    private function importSettings(array $s): void
    {
        (new SettingModel())->insert([
            'id'              => 1,
            'company_name'    => (string) ($s['companyName'] ?? 'BB Builders'),
            'tagline'         => (string) ($s['tagline'] ?? ''),
            'address'         => (string) ($s['address'] ?? ''),
            'phone'           => (string) ($s['phone'] ?? ''),
            'email'           => (string) ($s['email'] ?? ''),
            'logo'            => (string) ($s['logo'] ?? ''),
            'primary_color'   => (string) ($s['primary'] ?? '#0b2e4f'),
            'secondary_color' => (string) ($s['secondary'] ?? '#e0952e'),
            'currency'        => (string) ($s['currency'] ?? 'Rs'),
            'tax_rate'        => (float) ($s['taxRate'] ?? 0),
            'invoice_footer'  => (string) ($s['invoiceFooter'] ?? ''),
        ]);
    }

    private function seedAdmin(string $username = 'BBAccounts', string $password = 'admin2026'): void
    {
        (new UserModel())->insert([
            'id'         => 'admin-main',
            'employee_id' => '',
            'name'       => 'Admin',
            'username'   => $username,
            'password'   => $this->isHashed($password) ? $password : password_hash($password, PASSWORD_DEFAULT),
            'type'       => 'admin',
            'created_at' => $this->now(),
        ]);
    }

    /**
     * New-format backups carry a `users` array. Legacy backups keep admin
     * creds in settings.* and employee logins on the employee objects.
     */
    private function importUsers(array $users, array $full): void
    {
        $m = new UserModel();
        $hasAdmin = false;

        foreach ($users as $u) {
            $type = ($u['type'] ?? '') === 'admin' ? 'admin' : 'staff';
            $hasAdmin = $hasAdmin || $type === 'admin';
            $m->insert([
                'id'          => (string) ($u['id'] ?? $this->newId()),
                'employee_id' => (string) ($u['employeeId'] ?? ''),
                'name'        => (string) ($u['name'] ?? ''),
                'username'    => (string) ($u['username'] ?? ''),
                'password'    => $this->storePassword((string) ($u['password'] ?? '')),
                'type'        => $type,
                'created_at'  => $this->now(),
            ]);
        }

        // Legacy format: employee objects embed login fields
        foreach ((array) ($full['employees'] ?? []) as $e) {
            if (empty($e['loginEnabled']) || empty($e['username'])) {
                continue;
            }
            $m->insert([
                'id'          => $this->newId(),
                'employee_id' => (string) ($e['id'] ?? ''),
                'name'        => (string) ($e['name'] ?? ''),
                'username'    => (string) $e['username'],
                'password'    => $this->storePassword((string) ($e['password'] ?? '')),
                'type'        => 'staff',
                'created_at'  => $this->now(),
            ]);
        }

        // Legacy format: admin creds lived in settings.adminUser/adminPass
        if (! $hasAdmin) {
            $s = (array) ($full['settings'] ?? []);
            $this->seedAdmin(
                (string) ($s['adminUser'] ?? 'BBAccounts'),
                (string) ($s['adminPass'] ?? 'admin2026')
            );
        }
    }

    private function importHeads(array $rows): void
    {
        $m = new HeadModel();
        foreach ($rows as $h) {
            $m->insert([
                'id'         => (string) ($h['id'] ?? $this->newId()),
                'name'       => (string) ($h['name'] ?? ''),
                'type'       => ($h['type'] ?? '') === 'Income' ? 'Income' : 'Expense',
                'category'   => (string) ($h['group'] ?? ''),
                'created_at' => $this->now(),
            ]);
        }
    }

    private function importEmployees(array $rows): void
    {
        $m = new EmployeeModel();
        foreach ($rows as $e) {
            $id = (string) ($e['id'] ?? $this->newId());
            $m->insert([
                'id'          => $id,
                'name'        => (string) ($e['name'] ?? ''),
                'phone'       => (string) ($e['phone'] ?? ''),
                'designation' => (string) ($e['designation'] ?? ''),
                'created_at'  => $this->now(),
            ]);
            $m->setAssignedHeads($id, (array) ($e['assignedHeads'] ?? []));
        }
    }

    private function importVouchers(array $rows): void
    {
        $m = new VoucherModel();
        foreach ($rows as $v) {
            $m->insert([
                'id'          => (string) ($v['id'] ?? $this->newId()),
                'no'          => (string) ($v['no'] ?? ''),
                'type'        => ($v['type'] ?? '') === 'Receipt' ? 'Receipt' : 'Payment',
                'v_date'      => $this->validDate($v['date'] ?? ''),
                'head_id'     => (string) ($v['headId'] ?? ''),
                'amount'      => (float) ($v['amount'] ?? 0),
                'party'       => (string) ($v['party'] ?? ''),
                'description' => (string) ($v['description'] ?? ''),
                'created_by'  => (string) ($v['createdBy'] ?? ''),
                'created_at'  => $this->now(),
            ]);
        }
    }

    private function importInvoices(array $rows): void
    {
        $m = new InvoiceModel();
        $items = new InvoiceItemModel();
        foreach ($rows as $inv) {
            $id = (string) ($inv['id'] ?? $this->newId());
            $m->insert([
                'id'         => $id,
                'no'         => (string) ($inv['no'] ?? ''),
                'client'     => (string) ($inv['client'] ?? ''),
                'address'    => (string) ($inv['address'] ?? ''),
                'inv_date'   => $this->validDate($inv['date'] ?? ''),
                'notes'      => (string) ($inv['notes'] ?? ''),
                'created_by' => (string) ($inv['createdBy'] ?? ''),
                'created_at' => $this->now(),
            ]);
            $items->replaceFor($id, (array) ($inv['items'] ?? []));
        }
    }

    private function importSalary(array $rows): void
    {
        $m = new SalarySlipModel();
        foreach ($rows as $s) {
            $basic = (float) ($s['basic'] ?? 0);
            $allowance = (float) ($s['allowance'] ?? 0);
            $deduction = (float) ($s['deduction'] ?? 0);
            $bonus = (float) ($s['bonus'] ?? 0);
            $m->insert([
                'id'            => (string) ($s['id'] ?? $this->newId()),
                'employee_id'   => (string) ($s['employeeId'] ?? ''),
                'employee_name' => (string) ($s['employeeName'] ?? ''),
                'designation'   => (string) ($s['designation'] ?? ''),
                'phone'         => (string) ($s['phone'] ?? ''),
                'sal_month'     => min(12, max(1, (int) ($s['month'] ?? 1))),
                'sal_year'      => (int) ($s['year'] ?? (int) date('Y')),
                'basic'         => $basic,
                'allowance'     => $allowance,
                'deduction'     => $deduction,
                'bonus'         => $bonus,
                'total'         => (float) ($s['total'] ?? ($basic + $allowance - $deduction + $bonus)),
                'created_at'    => $this->now(),
            ]);
        }
    }

    private function storePassword(string $password): string
    {
        if ($password === '') {
            return '';
        }
        return $this->isHashed($password) ? $password : password_hash($password, PASSWORD_DEFAULT);
    }

    private function isHashed(string $stored): bool
    {
        return str_starts_with($stored, '$2y$') || str_starts_with($stored, '$argon2');
    }
}
