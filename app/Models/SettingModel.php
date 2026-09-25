<?php

namespace App\Models;

use CodeIgniter\Model;

class SettingModel extends Model
{
    protected $table = 'settings';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'id', 'company_name', 'tagline', 'address', 'phone', 'email', 'logo',
        'primary_color', 'secondary_color',
        'currency', 'tax_rate', 'invoice_footer',
    ];

    public function getRow(): array
    {
        return $this->find(1) ?? [];
    }

    /** Settings as the frontend expects them. */
    public function mapped(): array
    {
        $r = $this->getRow();
        return [
            'companyName'   => $r['company_name'] ?? 'BB Builders',
            'tagline'       => $r['tagline'] ?? '',
            'address'       => $r['address'] ?? '',
            'phone'         => $r['phone'] ?? '',
            'email'         => $r['email'] ?? '',
            'logo'          => $r['logo'] ?? '',
            'primary'       => $r['primary_color'] ?? '#0b2e4f',
            'secondary'     => $r['secondary_color'] ?? '#e0952e',
            'currency'      => $r['currency'] ?? 'Rs',
            'taxRate'       => (float) ($r['tax_rate'] ?? 0),
            'invoiceFooter' => $r['invoice_footer'] ?? '',
        ];
    }
}
