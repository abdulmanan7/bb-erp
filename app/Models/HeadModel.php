<?php

namespace App\Models;

use CodeIgniter\Model;

class HeadModel extends Model
{
    protected $table = 'heads';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = ['id', 'name', 'type', 'category', 'created_at'];

    public static function map(array $r): array
    {
        return [
            'id'        => $r['id'],
            'name'      => $r['name'],
            'type'      => $r['type'],
            'group'     => $r['category'] ?? '',
            'createdAt' => $r['created_at'] ?? null,
        ];
    }

    public function allMapped(): array
    {
        return array_map([self::class, 'map'], $this->orderBy('name', 'ASC')->findAll());
    }
}
