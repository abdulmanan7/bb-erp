<?php

namespace App\Controllers\Api;

use App\Libraries\Attachments;
use App\Libraries\HeadAccess;
use App\Models\PaymentModel;
use App\Models\VoucherModel;
use CodeIgniter\HTTP\ResponseInterface;

class Vouchers extends BaseApiController
{
    public function create(): ResponseInterface
    {
        $b = $this->body();
        $user = $this->user();

        $payment = null;
        if (! empty($b['paymentId'])) {
            $payment = (new PaymentModel())->findMapped((string) $b['paymentId']);
            if (! $payment) {
                return $this->fail('Payment not found.', 404);
            }
        }
        $src = $payment ?? $b;
        $head = HeadAccess::resolve($user, (string) ($src['headId'] ?? ''), (string) ($src['subHeadId'] ?? ''));
        if (is_string($head)) {
            return $this->fail($head);
        }
        $amount = (float) ($b['amount'] ?? 0);
        if ($error = $this->amountError($amount, $payment)) {
            return $this->fail($error);
        }

        $m = new VoucherModel();
        $id = $this->newId();
        $file = Attachments::save('vouchers', $id, $b['attachment'] ?? null);
        if (is_string($file)) {
            return $this->fail($file);
        }

        $date = $this->validDate($b['date'] ?? '');
        $m->insert([
            'id'          => $id,
            'no'          => $m->nextNo($head['type'], $date),
            'type'        => $head['type'],
            'v_date'      => $date,
            'head_id'     => $head['id'],
            'sub_head_id' => $head['subHeadId'],
            'amount'      => $amount,
            'paid'        => $amount,
            'payment_id'  => $payment['id'] ?? '',
            'party'       => trim((string) ($b['party'] ?? '')),
            'description' => trim((string) ($b['description'] ?? '')),
            'attachment'  => $file === true ? (string) ($b['attachment']['name'] ?? '') : '',
            'created_by'  => $user['name'],
            'created_at'  => $this->now(),
        ]);
        return $this->json(['voucher' => VoucherModel::map($m->find($id))]);
    }

    public function update($id = null): ResponseInterface
    {
        $m = new VoucherModel();
        $existing = $m->find($id);
        if (! $existing) {
            return $this->fail('Voucher not found.', 404);
        }
        $b = $this->body();

        $payment = $existing['payment_id'] !== '' ? (new PaymentModel())->findMapped($existing['payment_id']) : null;
        $src = $payment ?? $b;
        $head = HeadAccess::resolve($this->user(), (string) ($src['headId'] ?? ''), (string) ($src['subHeadId'] ?? ''));
        if (is_string($head)) {
            return $this->fail($head);
        }
        $amount = (float) ($b['amount'] ?? $existing['amount']);
        if ($error = $this->amountError($amount, $payment, (float) $existing['amount'])) {
            return $this->fail($error);
        }

        $attachment = $existing['attachment'];
        if (! empty($b['removeAttachment'])) {
            Attachments::delete('vouchers', $id);
            $attachment = '';
        }
        $file = Attachments::save('vouchers', $id, $b['attachment'] ?? null);
        if (is_string($file)) {
            return $this->fail($file);
        }
        if ($file === true) {
            $attachment = (string) ($b['attachment']['name'] ?? '');
        }

        $m->update($id, [
            'type'        => $head['type'],
            'v_date'      => $this->validDate($b['date'] ?? ''),
            'head_id'     => $head['id'],
            'sub_head_id' => $head['subHeadId'],
            'amount'      => $amount,
            'paid'        => $amount,
            'party'       => trim((string) ($b['party'] ?? '')),
            'description' => trim((string) ($b['description'] ?? '')),
            'attachment'  => $attachment,
        ]);
        return $this->json(['voucher' => VoucherModel::map($m->find($id))]);
    }

    public function delete($id = null): ResponseInterface
    {
        if (! (new VoucherModel())->find($id)) {
            return $this->fail('Voucher not found.', 404);
        }
        $this->removeVouchers([$id]);
        return $this->json(['ok' => true]);
    }

    public function bulkDelete(): ResponseInterface
    {
        $ids = array_values(array_unique(array_filter((array) ($this->body()['ids'] ?? []), 'is_string')));
        if ($ids === []) {
            return $this->fail('Select at least one voucher.');
        }
        if (count($ids) > 500) {
            return $this->fail('You can delete up to 500 vouchers at a time.');
        }
        $found = (array) ((new VoucherModel())->whereIn('id', $ids)->findColumn('id') ?? []);
        $this->removeVouchers($found);
        return $this->json(['deleted' => count($found)]);
    }

    private function removeVouchers(array $ids): void
    {
        if ($ids === []) {
            return;
        }
        (new VoucherModel())->whereIn('id', $ids)->delete();
        foreach ($ids as $id) {
            Attachments::delete('vouchers', $id);
        }
    }

    /** Error message when the amount is not positive or would exceed the payment's balance. */
    private function amountError(float $amount, ?array $payment, float $current = 0): ?string
    {
        if ($amount <= 0) {
            return 'Enter an amount greater than zero.';
        }
        if ($payment && $amount > $payment['total'] - $payment['paid'] + $current + 0.001) {
            return 'Amount exceeds the remaining balance of this payment.';
        }
        return null;
    }

    /**
     * Streams the stored file. Staff may only fetch their own vouchers' files.
     */
    public function attachment($id = null): ResponseInterface
    {
        $m = new VoucherModel();
        $v = $m->find($id);
        if (! $v || $v['attachment'] === '') {
            return $this->fail('Attachment not found.', 404);
        }
        $user = $this->user();
        if ($user['role'] !== 'admin' && $v['created_by'] !== $user['name']) {
            return $this->fail('Access denied.', 403);
        }

        $path = Attachments::file('vouchers', $id);
        if ($path === null) {
            return $this->fail('Attachment file missing.', 404);
        }

        return $this->response
            ->setHeader('Content-Type', Attachments::mime($v['attachment']))
            ->setHeader('Content-Disposition', 'inline; filename="' . basename($v['attachment']) . '"')
            ->setBody(file_get_contents($path));
    }
}
