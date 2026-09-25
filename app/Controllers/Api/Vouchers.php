<?php

namespace App\Controllers\Api;

use App\Models\EmployeeModel;
use App\Models\HeadModel;
use App\Models\VoucherModel;
use CodeIgniter\HTTP\ResponseInterface;

class Vouchers extends BaseApiController
{
    public function create(): ResponseInterface
    {
        $b = $this->body();
        $user = $this->user();

        $heads = new HeadModel();
        $head = $heads->find((string) ($b['headId'] ?? ''));
        if (! $head) {
            return $this->fail('Select a valid account head.');
        }
        if ($user['role'] !== 'admin'
            && ! in_array($head['id'], (new EmployeeModel())->assignedHeadIds($user['id'] ?? ''), true)) {
            return $this->fail('This head is not assigned to you.', 403);
        }

        $amount = (float) ($b['amount'] ?? 0);
        if ($amount <= 0) {
            return $this->fail('Amount must be greater than zero.');
        }

        $date = $this->validDate($b['date'] ?? '');
        $type = $head['type'] === 'Expense' ? 'Payment' : 'Receipt';

        $m = new VoucherModel();
        $id = $this->newId();
        $m->insert([
            'id'          => $id,
            'no'          => $m->nextNo($type, $date),
            'type'        => $type,
            'v_date'      => $date,
            'head_id'     => $head['id'],
            'amount'      => $amount,
            'party'       => trim((string) ($b['party'] ?? '')),
            'description' => trim((string) ($b['description'] ?? '')),
            'created_by'  => $user['name'],
            'created_at'  => $this->now(),
        ]);
        return $this->json(['voucher' => VoucherModel::map($m->find($id))]);
    }

    public function update($id = null): ResponseInterface
    {
        $m = new VoucherModel();
        if (! $m->find($id)) {
            return $this->fail('Voucher not found.', 404);
        }
        $b = $this->body();

        $head = (new HeadModel())->find((string) ($b['headId'] ?? ''));
        if (! $head) {
            return $this->fail('Select a valid account head.');
        }
        $amount = (float) ($b['amount'] ?? 0);
        if ($amount <= 0) {
            return $this->fail('Amount must be greater than zero.');
        }

        $m->update($id, [
            'type'        => $head['type'] === 'Expense' ? 'Payment' : 'Receipt',
            'v_date'      => $this->validDate($b['date'] ?? ''),
            'head_id'     => $head['id'],
            'amount'      => $amount,
            'party'       => trim((string) ($b['party'] ?? '')),
            'description' => trim((string) ($b['description'] ?? '')),
        ]);
        return $this->json(['voucher' => VoucherModel::map($m->find($id))]);
    }

    public function delete($id = null): ResponseInterface
    {
        $m = new VoucherModel();
        if (! $m->find($id)) {
            return $this->fail('Voucher not found.', 404);
        }
        $m->delete($id);
        return $this->json(['ok' => true]);
    }
}
