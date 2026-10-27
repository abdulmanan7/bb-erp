<?php

namespace App\Models;

use App\Libraries\Clock;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Model;

class NotificationModel extends Model
{
    protected $table = 'notifications';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'id', 'employee_id', 'category', 'title', 'body', 'reference_type', 'reference_id',
        'dedupe_key', 'push_status', 'read_at', 'created_at',
    ];

    public function listQuery(string $employeeId, bool $unreadOnly): BaseBuilder
    {
        $q = $this->db->table($this->table)->where('employee_id', $employeeId)->orderBy('created_at', 'DESC')->orderBy('id', 'DESC');
        if ($unreadOnly) {
            $q->where('read_at', null);
        }
        return $q;
    }

    public function unreadCount(string $employeeId): int
    {
        return $this->where(['employee_id' => $employeeId, 'read_at' => null])->countAllResults();
    }

    public function existingDedupeKeys(array $keys): array
    {
        if ($keys === []) {
            return [];
        }
        return array_column($this->select('dedupe_key')->whereIn('dedupe_key', $keys)->findAll(), 'dedupe_key');
    }

    public static function map(array $r): array
    {
        return [
            'id'            => $r['id'],
            'category'      => $r['category'],
            'title'         => $r['title'],
            'body'          => $r['body'] ?? '',
            'referenceType' => $r['reference_type'],
            'referenceId'   => $r['reference_id'],
            'read'          => $r['read_at'] !== null,
            'createdAt'     => Clock::iso($r['created_at']),
        ];
    }
}
