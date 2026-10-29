<?php

namespace App\Models;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Model;

class SpendingModel extends Model
{
    protected $table = 'spendings';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'id', 'kind', 'employee_id', 'project_id', 'head_id', 'sub_head_id', 'spend_date',
        'amount', 'party', 'description', 'attachment', 'created_by', 'created_at',
    ];

    public static function map(array $r): array
    {
        return [
            'id'          => $r['id'],
            'kind'        => $r['kind'],
            'employeeId'  => $r['employee_id'],
            'projectId'   => $r['project_id'],
            'headId'      => $r['head_id'] ?? '',
            'subHeadId'   => $r['sub_head_id'] ?? '',
            'date'        => $r['spend_date'],
            'amount'      => (float) $r['amount'],
            'party'       => $r['party'] ?? '',
            'description' => $r['description'] ?? '',
            'attachment'  => $r['attachment'] ?? '',
            'createdBy'   => $r['created_by'] ?? '',
            'createdAt'   => $r['created_at'] ?? null,
        ];
    }

    public function listQuery(string $employeeId, string $kind = ''): BaseBuilder
    {
        $q = $this->db->table('spendings')
            ->where('employee_id', $employeeId)
            ->orderBy('spend_date', 'DESC')
            ->orderBy('created_at', 'DESC');
        if (in_array($kind, ['advance', 'spend'], true)) {
            $q->where('kind', $kind);
        }
        return $q;
    }

    /** All entries, or only one employee's entries when an id is given. */
    public function mappedFor(?string $employeeId = null): array
    {
        if ($employeeId !== null) {
            $this->where('employee_id', $employeeId);
        }
        return array_map([self::class, 'map'], $this->orderBy('spend_date', 'DESC')->findAll());
    }
}
