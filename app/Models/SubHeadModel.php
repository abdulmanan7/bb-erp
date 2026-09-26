<?php

namespace App\Models;

use CodeIgniter\Model;

class SubHeadModel extends Model
{
    protected $table = 'sub_heads';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = ['id', 'head_id', 'name', 'created_at'];

    public static function map(array $r): array
    {
        return [
            'id'     => $r['id'],
            'headId' => $r['head_id'],
            'name'   => $r['name'],
        ];
    }

    public function allMapped(): array
    {
        return array_map([self::class, 'map'], $this->orderBy('name', 'ASC')->findAll());
    }
}
