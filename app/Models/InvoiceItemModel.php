<?php

namespace App\Models;

use CodeIgniter\Model;

class InvoiceItemModel extends Model
{
    protected $table = 'invoice_items';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = ['invoice_id', 'description', 'qty', 'rate'];

    public function forInvoice(string $invoiceId): array
    {
        return array_map(
            fn ($r) => [
                'desc' => $r['description'],
                'qty'  => (float) $r['qty'],
                'rate' => (float) $r['rate'],
            ],
            $this->where('invoice_id', $invoiceId)->orderBy('id', 'ASC')->findAll()
        );
    }

    public function replaceFor(string $invoiceId, array $items): void
    {
        $this->where('invoice_id', $invoiceId)->delete();
        $rows = [];
        foreach ($items as $it) {
            $rows[] = [
                'invoice_id'  => $invoiceId,
                'description' => (string) ($it['desc'] ?? ''),
                'qty'         => (float) ($it['qty'] ?? 0),
                'rate'        => (float) ($it['rate'] ?? 0),
            ];
        }
        if ($rows !== []) {
            $this->insertBatch($rows);
        }
    }
}
