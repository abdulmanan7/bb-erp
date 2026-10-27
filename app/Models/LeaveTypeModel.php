<?php

namespace App\Models;

use CodeIgniter\Model;

class LeaveTypeModel extends Model
{
    protected $table = 'leave_types';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = ['id', 'name', 'is_active', 'created_at'];

    public function activeTypes(): array
    {
        return $this->where('is_active', 1)->orderBy('name', 'ASC')->findAll();
    }

    public function findActive(string $id): ?array
    {
        return $this->where(['id' => $id, 'is_active' => 1])->first();
    }
}
