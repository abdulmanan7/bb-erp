<?php

namespace App\Models;

use CodeIgniter\Model;

class VoucherPaymentModel extends Model
{
    protected $table = 'voucher_payments';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'id', 'voucher_id', 'amount', 'pay_date', 'note', 'created_by', 'created_at',
    ];

    public static function map(array $r): array
    {
        return [
            'id'        => $r['id'],
            'voucherId' => $r['voucher_id'],
            'amount'    => (float) $r['amount'],
            'date'      => $r['pay_date'],
            'note'      => $r['note'],
            'createdBy' => $r['created_by'],
        ];
    }

    public function allMapped(): array
    {
        return array_map([self::class, 'map'], $this->orderBy('pay_date', 'DESC')->findAll());
    }

    /** Sum of payments recorded against a voucher. */
    public function paidFor(string $voucherId): float
    {
        return (float) ($this->selectSum('amount')->where('voucher_id', $voucherId)->first()['amount'] ?? 0);
    }
}
