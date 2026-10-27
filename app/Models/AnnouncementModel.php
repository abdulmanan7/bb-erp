<?php

namespace App\Models;

use App\Libraries\Clock;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Model;

class AnnouncementModel extends Model
{
    protected $table = 'announcements';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'id', 'title', 'content', 'audience', 'status', 'published_at', 'created_by', 'created_at', 'updated_at',
    ];

    public function targetIds(string $announcementId): array
    {
        return array_column(
            $this->db->table('announcement_targets')->select('employee_id')->where('announcement_id', $announcementId)->get()->getResultArray(),
            'employee_id'
        );
    }

    public function saveTargets(string $announcementId, array $employeeIds): void
    {
        $this->db->table('announcement_targets')->where('announcement_id', $announcementId)->delete();
        $rows = array_map(
            fn ($employeeId) => ['announcement_id' => $announcementId, 'employee_id' => $employeeId],
            array_values(array_unique(array_filter($employeeIds)))
        );
        if ($rows !== []) {
            $this->db->table('announcement_targets')->ignore(true)->insertBatch($rows);
        }
    }

    public function adminListQuery(): BaseBuilder
    {
        return $this->db->table($this->table)->orderBy('created_at', 'DESC');
    }

    /** Published announcements an employee is allowed to see, with their read state. */
    public function visibleQuery(string $employeeId): BaseBuilder
    {
        return $this->db->table('announcements a')
            ->select('a.*, ar.read_at')
            ->join('announcement_reads ar', 'ar.announcement_id = a.id AND ar.employee_id = ' . $this->db->escape($employeeId), 'left')
            ->where('a.status', 'published')
            ->groupStart()
                ->where('a.audience', 'all')
                ->orWhereIn('a.id', fn (BaseBuilder $b) => $b->select('announcement_id')->from('announcement_targets')->where('employee_id', $employeeId))
            ->groupEnd()
            ->orderBy('a.published_at', 'DESC');
    }

    public function markRead(string $announcementId, string $employeeId): void
    {
        $this->db->table('announcement_reads')->ignore(true)->insert([
            'announcement_id' => $announcementId,
            'employee_id'     => $employeeId,
            'read_at'         => Clock::now(),
        ]);
    }

    public static function map(array $r, bool $withRead = false): array
    {
        $data = [
            'id'          => $r['id'],
            'title'       => $r['title'],
            'content'     => $r['content'] ?? '',
            'audience'    => $r['audience'],
            'status'      => $r['status'],
            'publishedAt' => Clock::iso($r['published_at'] ?? null),
            'createdBy'   => $r['created_by'],
            'createdAt'   => Clock::iso($r['created_at'] ?? null),
            'updatedAt'   => Clock::iso($r['updated_at'] ?? null),
        ];
        if ($withRead) {
            $data['read'] = ! empty($r['read_at']);
        }
        return $data;
    }
}
