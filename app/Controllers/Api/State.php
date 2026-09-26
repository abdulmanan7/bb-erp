<?php

namespace App\Controllers\Api;

use App\Models\EmployeeModel;
use App\Models\HeadModel;
use App\Models\InvoiceModel;
use App\Models\SalarySlipModel;
use App\Models\SettingModel;
use App\Models\SubHeadModel;
use App\Models\VoucherModel;
use CodeIgniter\HTTP\ResponseInterface;

class State extends BaseApiController
{
    /**
     * Returns the full app state the SPA renders from.
     * Admins get everything; employees get only what they need.
     */
    public function index(): ResponseInterface
    {
        $user = $this->user();
        $settings = new SettingModel();
        $heads = new HeadModel();
        $vouchers = new VoucherModel();
        $employees = new EmployeeModel();

        if ($user['role'] === 'admin') {
            return $this->json([
                'settings'  => $settings->mapped(),
                'heads'     => $heads->allMapped(),
                'subHeads'  => (new SubHeadModel())->allMapped(),
                'vouchers'  => $vouchers->allMapped(),
                'employees' => $employees->allMapped(),
                'invoices'  => (new InvoiceModel())->allMapped(),
                'salary'    => (new SalarySlipModel())->allMapped(),
            ]);
        }

        $emp = $employees->find($user['id'] ?? '');
        $assigned = $emp ? $employees->assignedHeadIds($emp['id']) : [];

        return $this->json([
            'settings'  => $settings->mapped(),
            'heads'     => array_values(array_filter(
                $heads->allMapped(),
                fn ($h) => in_array($h['id'], $assigned, true)
            )),
            'subHeads'  => array_values(array_filter(
                (new SubHeadModel())->allMapped(),
                fn ($s) => in_array($s['headId'], $assigned, true)
            )),
            'vouchers'  => array_values(array_filter(
                $vouchers->allMapped(),
                fn ($v) => $v['createdBy'] === $user['name']
            )),
            'employees' => $emp ? [$employees->map($emp)] : [],
            'invoices'  => [],
            'salary'    => [],
        ]);
    }
}
