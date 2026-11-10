<?php

namespace App\Controllers\Api;

use App\Libraries\HeadAccess;
use App\Models\PaymentModel;
use CodeIgniter\HTTP\ResponseInterface;

class Payments extends BaseApiController
{
    public function create(): ResponseInterface
    {
        $user = $this->user();
        $data = $this->payload($this->body(), $user);
        if (is_string($data)) {
            return $this->fail($data);
        }

        $m = new PaymentModel();
        $id = $this->newId();
        $m->insert($data + ['id' => $id, 'created_by' => $user['name'], 'created_at' => $this->now()]);
        return $this->json(['payment' => $m->findMapped($id)]);
    }

    public function update($id = null): ResponseInterface
    {
        $m = new PaymentModel();
        $existing = $m->find($id);
        if (! $existing) {
            return $this->fail('Payment not found.', 404);
        }
        $data = $this->payload($this->body(), $this->user());
        if (is_string($data)) {
            return $this->fail($data);
        }
        $locked = $m->hasVouchers($id)
            && ((float) $existing['total'] !== $data['total'] || $existing['head_id'] !== $data['head_id'] || $existing['sub_head_id'] !== $data['sub_head_id']);
        if ($locked) {
            return $this->fail('Head and total cannot be changed after vouchers are linked to this payment.');
        }

        $m->update($id, $data);
        return $this->json(['payment' => $m->findMapped($id)]);
    }

    public function delete($id = null): ResponseInterface
    {
        $m = new PaymentModel();
        if (! $m->find($id)) {
            return $this->fail('Payment not found.', 404);
        }
        if ($m->hasVouchers($id)) {
            return $this->fail('Delete this payment\'s vouchers first.');
        }
        $m->delete($id);
        return $this->json(['ok' => true]);
    }

    /** @return array|string validated column values, or an error message */
    private function payload(array $b, array $user): array|string
    {
        $head = HeadAccess::resolve($user, (string) ($b['headId'] ?? ''), (string) ($b['subHeadId'] ?? ''));
        if (is_string($head)) {
            return $head;
        }
        $total = (float) ($b['total'] ?? 0);
        if ($total <= 0) {
            return 'Total must be greater than zero.';
        }
        return [
            'type'        => $head['type'],
            'p_date'      => $this->validDate($b['date'] ?? ''),
            'head_id'     => $head['id'],
            'sub_head_id' => $head['subHeadId'],
            'total'       => $total,
            'description' => trim((string) ($b['description'] ?? '')),
        ];
    }
}
