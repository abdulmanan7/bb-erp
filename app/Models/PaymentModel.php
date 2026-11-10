<?php

namespace App\Models;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Model;

class PaymentModel extends Model
{
    protected $table = 'payments';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'id', 'type', 'p_date', 'head_id', 'sub_head_id', 'total', 'description', 'created_by', 'created_at',
    ];

    public static function map(array $r): array
    {
        return [
            'id'          => $r['id'],
            'type'        => $r['type'],
            'date'        => $r['p_date'],
            'headId'      => $r['head_id'],
            'subHeadId'   => $r['sub_head_id'] ?? '',
            'total'       => (float) $r['total'],
            'paid'        => (float) ($r['paid'] ?? 0),
            'description' => $r['description'] ?? '',
            'createdBy'   => $r['created_by'] ?? '',
            'createdAt'   => $r['created_at'] ?? null,
        ];
    }

    public function allMapped(): array
    {
        $rows = $this->withPaid()->orderBy('payments.p_date', 'DESC')->get()->getResultArray();
        return array_map([self::class, 'map'], $rows);
    }

    public function findMapped(string $id): ?array
    {
        $row = $this->withPaid()->where('payments.id', $id)->get()->getRowArray();
        return $row ? self::map($row) : null;
    }

    public function paidFor(string $id): float
    {
        return (float) ($this->db->table('vouchers')->selectSum('amount')->where('payment_id', $id)->get()->getRow()->amount ?? 0);
    }

    public function hasVouchers(string $id): bool
    {
        return $this->db->table('vouchers')->where('payment_id', $id)->countAllResults() > 0;
    }

    private function withPaid(): BaseBuilder
    {
        return $this->db->table('payments')
            ->select('payments.*, COALESCE(SUM(v.amount), 0) AS paid')
            ->join('vouchers v', 'v.payment_id = payments.id', 'left')
            ->groupBy('payments.id');
    }
}
