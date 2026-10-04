<?php

namespace App\Controllers\Api;

use App\Models\EmployeeModel;
use App\Models\HeadModel;
use App\Models\SubHeadModel;
use App\Models\VoucherModel;
use App\Models\VoucherPaymentModel;
use CodeIgniter\HTTP\ResponseInterface;

class Vouchers extends BaseApiController
{
    private const ALLOWED_EXT  = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];
    private const MAX_SIZE     = 5 * 1024 * 1024; // 5 MB after base64 decode

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

        $subHeadId = (string) ($b['subHeadId'] ?? '');
        if ($subHeadId !== '' && ! (new SubHeadModel())->where('id', $subHeadId)->where('head_id', $head['id'])->first()) {
            return $this->fail('Invalid sub-head.');
        }

        $amounts = $this->amounts($b);
        if (is_string($amounts)) {
            return $this->fail($amounts);
        }

        $date = $this->validDate($b['date'] ?? '');
        $type = $head['type'] === 'Expense' ? 'Payment' : 'Receipt';

        $m = new VoucherModel();
        $id = $this->newId();
        $file = $this->saveAttachment($id, $b['attachment'] ?? null);
        if (is_string($file)) {
            return $this->fail($file);
        }

        $m->insert([
            'id'          => $id,
            'no'          => $m->nextNo($type, $date),
            'type'        => $type,
            'v_date'      => $date,
            'head_id'     => $head['id'],
            'sub_head_id' => $subHeadId,
            'amount'      => $amounts['paid'],
            'total'       => $amounts['total'],
            'paid'        => $amounts['paid'],
            'status'      => $amounts['status'],
            'party'       => trim((string) ($b['party'] ?? '')),
            'description' => trim((string) ($b['description'] ?? '')),
            'attachment'  => $file === true ? (string) ($b['attachment']['name'] ?? '') : '',
            'created_by'  => $user['name'],
            'created_at'  => $this->now(),
        ]);
        if ($amounts['paid'] > 0) {
            $this->recordPayment($id, $amounts['paid'], $date, 'Initial payment', $user['name']);
        }
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

        $head = (new HeadModel())->find((string) ($b['headId'] ?? ''));
        if (! $head) {
            return $this->fail('Select a valid account head.');
        }
        $subHeadId = (string) ($b['subHeadId'] ?? '');
        if ($subHeadId !== '' && ! (new SubHeadModel())->where('id', $subHeadId)->where('head_id', $head['id'])->first()) {
            return $this->fail('Invalid sub-head.');
        }

        // Paid is owned by the payment ledger — only total is editable here.
        $total = (float) ($b['total'] ?? $existing['total']);
        if ($total <= 0) {
            return $this->fail('Total must be greater than zero.');
        }
        $paid = (float) $existing['paid'];
        $status = $paid <= 0 ? 'pending' : ($paid >= $total ? 'paid' : 'partial');

        $attachment = $existing['attachment'];
        if (! empty($b['removeAttachment'])) {
            $this->deleteAttachmentFile($id);
            $attachment = '';
        }
        $file = $this->saveAttachment($id, $b['attachment'] ?? null);
        if (is_string($file)) {
            return $this->fail($file);
        }
        if ($file === true) {
            $attachment = (string) ($b['attachment']['name'] ?? '');
        }

        $m->update($id, [
            'type'        => $head['type'] === 'Expense' ? 'Payment' : 'Receipt',
            'v_date'      => $this->validDate($b['date'] ?? ''),
            'head_id'     => $head['id'],
            'sub_head_id' => $subHeadId,
            'amount'      => $paid,
            'total'       => $total,
            'paid'        => $paid,
            'status'      => $status,
            'party'       => trim((string) ($b['party'] ?? '')),
            'description' => trim((string) ($b['description'] ?? '')),
            'attachment'  => $attachment,
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
        (new VoucherPaymentModel())->where('voucher_id', $id)->delete();
        $this->deleteAttachmentFile($id);
        return $this->json(['ok' => true]);
    }

    /**
     * Records an installment against a voucher. Staff may only pay
     * vouchers they created themselves.
     */
    public function addPayment($id = null): ResponseInterface
    {
        $m = new VoucherModel();
        $v = $m->find($id);
        if (! $v) {
            return $this->fail('Voucher not found.', 404);
        }
        $user = $this->user();
        if ($user['role'] !== 'admin' && $v['created_by'] !== $user['name']) {
            return $this->fail('Access denied.', 403);
        }

        $b = $this->body();
        $amount = (float) ($b['amount'] ?? 0);
        if ($amount <= 0) {
            return $this->fail('Enter a payment amount greater than zero.');
        }
        $remaining = (float) $v['total'] - (float) $v['paid'];
        if ($amount > $remaining + 0.001) {
            return $this->fail('Payment exceeds the remaining balance.');
        }
        $date = $this->validDate($b['date'] ?? date('Y-m-d'));
        $this->recordPayment($id, $amount, $date, trim((string) ($b['note'] ?? '')), $user['name']);
        $this->syncPaid($id);
        return $this->json(['voucher' => VoucherModel::map($m->find($id))]);
    }

    public function deletePayment($paymentId = null): ResponseInterface
    {
        $pm = new VoucherPaymentModel();
        $p = $pm->find($paymentId);
        if (! $p) {
            return $this->fail('Payment not found.', 404);
        }
        $pm->delete($paymentId);
        $this->syncPaid($p['voucher_id']);
        return $this->json(['ok' => true]);
    }

    private function recordPayment(string $voucherId, float $amount, string $date, string $note, string $by): void
    {
        (new VoucherPaymentModel())->insert([
            'id'         => $this->newId(),
            'voucher_id' => $voucherId,
            'amount'     => $amount,
            'pay_date'   => $date,
            'note'       => $note,
            'created_by' => $by,
            'created_at' => $this->now(),
        ]);
    }

    /** Recomputes paid/amount/status on the voucher from its ledger. */
    private function syncPaid(string $voucherId): void
    {
        $m = new VoucherModel();
        $v = $m->find($voucherId);
        if (! $v) {
            return;
        }
        $pm = new VoucherPaymentModel();
        $paid = $pm->paidFor($voucherId);
        // Defensive: a voucher with paid>0 but an empty ledger (created before
        // tracking) gets a synthetic backfill entry so history stays complete.
        if ($paid <= 0 && (float) $v['paid'] > 0) {
            $this->recordPayment($voucherId, (float) $v['paid'], $v['v_date'], 'Recorded before payment tracking', $v['created_by']);
            $paid = $pm->paidFor($voucherId);
        }
        $total = (float) $v['total'];
        $m->update($voucherId, [
            'amount' => $paid,
            'paid'   => $paid,
            'status' => $paid <= 0 ? 'pending' : ($paid >= $total ? 'paid' : 'partial'),
        ]);
    }

    /**
     * Total = the voucher's full amount; paid = amount actually settled.
     * amount stays equal to paid so cash-flow reports keep working.
     * Returns ['total','paid','status'] or an error string.
     */
    private function amounts(array $b)
    {
        $total = (float) ($b['total'] ?? $b['amount'] ?? 0);
        $paid = isset($b['paid']) ? (float) $b['paid'] : $total;
        if ($total <= 0) {
            return 'Total must be greater than zero.';
        }
        if ($paid < 0) {
            return 'Paid amount cannot be negative.';
        }
        if ($paid > $total) {
            return 'Paid amount cannot exceed the total.';
        }
        return [
            'total'  => $total,
            'paid'   => $paid,
            'status' => $paid <= 0 ? 'pending' : ($paid >= $total ? 'paid' : 'partial'),
        ];
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

        $files = glob($this->attachmentPath($id) . '.*');
        if (empty($files) || ! is_file($files[0])) {
            return $this->fail('Attachment file missing.', 404);
        }
        $path = $files[0];

        $ext = strtolower(pathinfo($v['attachment'], PATHINFO_EXTENSION));
        $mime = $ext === 'pdf' ? 'application/pdf' : 'image/' . ($ext === 'jpg' ? 'jpeg' : $ext);

        return $this->response
            ->setHeader('Content-Type', $mime)
            ->setHeader('Content-Disposition', 'inline; filename="' . basename($v['attachment']) . '"')
            ->setBody(file_get_contents($path));
    }

    /**
     * Saves an uploaded attachment sent as {name, data} (data = base64 data URL).
     * Returns true on save, null when nothing was sent, or an error string.
     */
    private function saveAttachment(string $voucherId, $attachment)
    {
        if (! is_array($attachment) || empty($attachment['data'])) {
            return null;
        }

        $data = (string) $attachment['data'];
        if (str_starts_with($data, 'data:')) {
            $data = substr($data, strpos($data, ',') + 1);
        }
        $binary = base64_decode($data, true);
        if ($binary === false || strlen($binary) > self::MAX_SIZE) {
            return 'Attachment too large (max 5 MB).';
        }

        $ext = strtolower(pathinfo((string) ($attachment['name'] ?? ''), PATHINFO_EXTENSION));
        if (! in_array($ext, self::ALLOWED_EXT, true)) {
            return 'Only images and PDF files are allowed.';
        }

        $dir = WRITEPATH . 'uploads/vouchers';
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $this->deleteAttachmentFile($voucherId);
        file_put_contents($this->attachmentPath($voucherId, $ext), $binary);
        return true;
    }

    private function attachmentPath(string $voucherId, string $ext = ''): string
    {
        return WRITEPATH . 'uploads/vouchers/' . $voucherId . ($ext !== '' ? '.' . $ext : '');
    }

    private function deleteAttachmentFile(string $voucherId): void
    {
        foreach (glob($this->attachmentPath($voucherId) . '.*') ?: [] as $f) {
            @unlink($f);
        }
    }
}
