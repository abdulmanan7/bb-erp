<?php

namespace App\Controllers\Api;

use App\Models\EmployeeModel;
use App\Models\HeadModel;
use App\Models\SubHeadModel;
use App\Models\VoucherModel;
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

        $amount = (float) ($b['amount'] ?? 0);
        if ($amount <= 0) {
            return $this->fail('Amount must be greater than zero.');
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
            'amount'      => $amount,
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

        $head = (new HeadModel())->find((string) ($b['headId'] ?? ''));
        if (! $head) {
            return $this->fail('Select a valid account head.');
        }
        $subHeadId = (string) ($b['subHeadId'] ?? '');
        if ($subHeadId !== '' && ! (new SubHeadModel())->where('id', $subHeadId)->where('head_id', $head['id'])->first()) {
            return $this->fail('Invalid sub-head.');
        }

        $amount = (float) ($b['amount'] ?? 0);
        if ($amount <= 0) {
            return $this->fail('Amount must be greater than zero.');
        }

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
            'amount'      => $amount,
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
        $this->deleteAttachmentFile($id);
        return $this->json(['ok' => true]);
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
