<?php

namespace App\Controllers\Api;

use App\Models\EmployeeModel;
use App\Models\ProjectAssignmentModel;
use App\Models\ProjectModel;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Attendance;

class Projects extends BaseApiController
{
    public function index(): ResponseInterface
    {
        $m = new ProjectModel();
        $search = trim((string) $this->request->getGet('search'));
        if ($search !== '') {
            $m->groupStart()->like('name', $search)->orLike('address', $search)->groupEnd();
        }
        if (in_array($this->request->getGet('status'), ['active', 'inactive'], true)) {
            $m->where('status', $this->request->getGet('status'));
        }
        return $this->json(['projects' => $this->withEmployees($m->orderBy('name', 'ASC')->findAll())]);
    }

    public function create(): ResponseInterface
    {
        $data = $this->payload($this->body());
        if (is_string($data)) {
            return $this->fail($data);
        }

        $id = $this->newId();
        $now = $this->now();
        (new ProjectModel())->insert($data + ['id' => $id, 'created_at' => $now, 'updated_at' => $now]);
        return $this->json(['project' => $this->withEmployees([(new ProjectModel())->find($id)])[0]], 201);
    }

    public function update(string $id): ResponseInterface
    {
        $m = new ProjectModel();
        if (! $m->find($id)) {
            return $this->fail('Project not found.', 404);
        }
        $data = $this->payload($this->body());
        if (is_string($data)) {
            return $this->fail($data);
        }

        $m->update($id, $data + ['updated_at' => $this->now()]);
        return $this->json(['project' => $this->withEmployees([$m->find($id)])[0]]);
    }

    public function assign(string $id): ResponseInterface
    {
        $m = new ProjectModel();
        if (! $m->find($id)) {
            return $this->fail('Project not found.', 404);
        }
        $employeeIds = array_values(array_unique(array_filter((array) ($this->body()['employeeIds'] ?? []), 'is_string')));
        if ($employeeIds !== [] && (new EmployeeModel())->whereIn('id', $employeeIds)->countAllResults() !== count($employeeIds)) {
            return $this->fail('One or more selected employees were not found.');
        }

        (new ProjectAssignmentModel())->setAssignments($id, $employeeIds);
        return $this->json(['project' => $this->withEmployees([$m->find($id)])[0]]);
    }

    /** @return array|string validated project data, or an error message */
    private function payload(array $b): array|string
    {
        $name = trim((string) ($b['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 150) {
            return 'Project name is required (max 150 characters).';
        }
        $lat = $b['latitude'] ?? null;
        $lng = $b['longitude'] ?? null;
        if (! is_numeric($lat) || abs((float) $lat) > 90) {
            return 'Enter a valid latitude between -90 and 90.';
        }
        if (! is_numeric($lng) || abs((float) $lng) > 180) {
            return 'Enter a valid longitude between -180 and 180.';
        }
        $radius = $b['radiusM'] ?? 100;
        if (! is_numeric($radius) || (int) $radius < 10 || (int) $radius > 5000) {
            return 'Attendance radius must be between 10 and 5000 metres.';
        }
        $timezone = (string) ($b['timezone'] ?? config(Attendance::class)->defaultTimezone);
        if (! in_array($timezone, timezone_identifiers_list(), true)) {
            return 'Select a valid timezone.';
        }
        $startTime = trim((string) ($b['startTime'] ?? ''));
        if ($startTime !== '' && ! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $startTime)) {
            return 'Enter a valid start time (HH:MM).';
        }
        $status = (string) ($b['status'] ?? 'active');
        if (! in_array($status, ['active', 'inactive'], true)) {
            return 'Invalid project status.';
        }

        return [
            'name'        => $name,
            'description' => trim((string) ($b['description'] ?? '')),
            'address'     => trim((string) ($b['address'] ?? '')),
            'latitude'    => round((float) $lat, 7),
            'longitude'   => round((float) $lng, 7),
            'radius_m'    => (int) $radius,
            'timezone'    => $timezone,
            'start_time'  => $startTime === '' ? null : $startTime . ':00',
            'status'      => $status,
        ];
    }

    private function withEmployees(array $rows): array
    {
        $byProject = (new ProjectAssignmentModel())->activeEmployeesByProject(array_column($rows, 'id'));
        return array_map(
            fn ($row) => ProjectModel::map($row) + ['assignedEmployees' => $byProject[$row['id']] ?? []],
            $rows
        );
    }
}
