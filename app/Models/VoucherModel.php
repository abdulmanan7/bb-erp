<?php

namespace App\Models;

use CodeIgniter\Model;

class VoucherModel extends Model
{
    protected $table = 'vouchers';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'id', 'no', 'type', 'v_date', 'head_id', 'sub_head_id', 'amount',
        'total', 'paid', 'status',
        'party', 'description', 'attachment', 'created_by', 'created_at',
    ];

    public static function map(array $r): array
    {
        return [
            'id'          => $r['id'],
            'no'          => $r['no'],
            'type'        => $r['type'],
            'date'        => $r['v_date'],
            'headId'      => $r['head_id'],
            'subHeadId'   => $r['sub_head_id'] ?? '',
            'amount'      => (float) $r['amount'],
            'total'       => (float) ($r['total'] ?? $r['amount']),
            'paid'        => (float) ($r['paid'] ?? $r['amount']),
            'status'      => $r['status'] ?? 'paid',
            'party'       => $r['party'] ?? '',
            'description' => $r['description'] ?? '',
            'attachment'  => $r['attachment'] ?? '',
            'createdBy'   => $r['created_by'] ?? '',
            'createdAt'   => $r['created_at'] ?? null,
        ];
    }

    public function allMapped(): array
    {
        return array_map([self::class, 'map'], $this->orderBy('v_date', 'ASC')->findAll());
    }

    public function nextNo(string $type, string $date): string
    {
        $prefix = $type === 'Payment' ? 'PV' : 'RV';
        $year = substr($date, 0, 4);
        $count = $this->like('no', "{$prefix}-{$year}-", 'after')->countAllResults();
        return sprintf('%s-%s-%04d', $prefix, $year, $count + 1);
    }
}
