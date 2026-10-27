<?php

namespace App\Models;

use App\Libraries\Clock;
use CodeIgniter\Model;

class ProjectAssignmentModel extends Model
{
    protected $table = 'project_assignments';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = ['project_id', 'employee_id', 'status', 'created_at', 'updated_at'];

    /** Replaces the active roster; removed employees are deactivated so history is kept. */
    public function setAssignments(string $projectId, array $employeeIds): void
    {
        $now = Clock::now();
        $employeeIds = array_values(array_unique(array_filter($employeeIds)));
        $existing = array_column($this->where('project_id', $projectId)->findAll(), null, 'employee_id');

        $this->db->transStart();
        foreach ($existing as $employeeId => $row) {
            if ($row['status'] === 'active' && ! in_array($employeeId, $employeeIds, true)) {
                $this->update($row['id'], ['status' => 'inactive', 'updated_at' => $now]);
            }
        }
        foreach ($employeeIds as $employeeId) {
            if (isset($existing[$employeeId])) {
                if ($existing[$employeeId]['status'] !== 'active') {
                    $this->update($existing[$employeeId]['id'], ['status' => 'active', 'updated_at' => $now]);
                }
            } else {
                $this->insert([
                    'project_id'  => $projectId,
                    'employee_id' => $employeeId,
                    'status'      => 'active',
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]);
            }
        }
        $this->db->transComplete();
    }

    /** @return array<string, list<array{id: string, name: string, designation: string}>> keyed by project id */
    public function activeEmployeesByProject(array $projectIds): array
    {
        if ($projectIds === []) {
            return [];
        }
        $rows = $this->db->table('project_assignments pa')
            ->select('pa.project_id, e.id, e.name, e.designation')
            ->join('employees e', 'e.id = pa.employee_id')
            ->where('pa.status', 'active')
            ->whereIn('pa.project_id', $projectIds)
            ->orderBy('e.name', 'ASC')
            ->get()->getResultArray();

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['project_id']][] = ['id' => $row['id'], 'name' => $row['name'], 'designation' => $row['designation']];
        }
        return $grouped;
    }

    /** Projects an employee may check in to: active project and active assignment. */
    public function activeProjectsFor(string $employeeId): array
    {
        return $this->db->table('project_assignments pa')
            ->select('p.*')
            ->join('projects p', 'p.id = pa.project_id')
            ->where('pa.employee_id', $employeeId)
            ->where('pa.status', 'active')
            ->where('p.status', 'active')
            ->orderBy('p.name', 'ASC')
            ->get()->getResultArray();
    }

    public function isActiveFor(string $projectId, string $employeeId): bool
    {
        return $this->where(['project_id' => $projectId, 'employee_id' => $employeeId, 'status' => 'active'])->first() !== null;
    }
}
