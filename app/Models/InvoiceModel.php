<?php

namespace App\Models;

use CodeIgniter\Model;

class InvoiceModel extends Model
{
    protected $table = 'invoices';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'id', 'no', 'client', 'address', 'inv_date',
        'notes', 'created_by', 'created_at',
    ];

    public function map(array $r): array
    {
        return [
            'id'        => $r['id'],
            'no'        => $r['no'],
            'client'    => $r['client'],
            'address'   => $r['address'] ?? '',
            'date'      => $r['inv_date'],
            'items'     => (new InvoiceItemModel())->forInvoice($r['id']),
            'notes'     => $r['notes'] ?? '',
            'createdBy' => $r['created_by'] ?? '',
            'createdAt' => $r['created_at'] ?? null,
        ];
    }

    public function allMapped(): array
    {
        return array_map(fn ($r) => $this->map($r), $this->orderBy('inv_date', 'ASC')->findAll());
    }

    public function nextNo(string $date): string
    {
        $year = substr($date, 0, 4);
        $count = $this->like('no', "INV-{$year}-", 'after')->countAllResults();
        return sprintf('INV-%s-%04d', $year, $count + 1);
    }
}
