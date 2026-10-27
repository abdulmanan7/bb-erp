<?php

namespace App\Controllers\Api;

use App\Libraries\Notifier;
use App\Models\AnnouncementModel;
use App\Models\EmployeeModel;
use CodeIgniter\HTTP\ResponseInterface;

class Announcements extends BaseApiController
{
    public function index(): ResponseInterface
    {
        $m = new AnnouncementModel();
        return $this->paginate($m->adminListQuery(), fn ($row) => AnnouncementModel::map($row) + [
            'employeeIds' => $m->targetIds($row['id']),
        ]);
    }

    public function create(): ResponseInterface
    {
        $data = $this->payload($this->body());
        if (is_string($data)) {
            return $this->fail($data);
        }

        $m = new AnnouncementModel();
        $id = $this->newId();
        $now = $this->now();
        $m->insert($data['row'] + [
            'id'         => $id,
            'status'     => 'draft',
            'created_by' => $this->user()['name'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $m->saveTargets($id, $data['employeeIds']);
        return $this->json(['announcement' => $this->detail($id)], 201);
    }

    public function update(string $id): ResponseInterface
    {
        $m = new AnnouncementModel();
        if (! $m->find($id)) {
            return $this->fail('Announcement not found.', 404);
        }
        $data = $this->payload($this->body());
        if (is_string($data)) {
            return $this->fail($data);
        }

        $m->update($id, $data['row'] + ['updated_at' => $this->now()]);
        $m->saveTargets($id, $data['employeeIds']);
        return $this->json(['announcement' => $this->detail($id)]);
    }

    /** Publishing is one-way and notifies recipients once; later edits do not re-notify. */
    public function publish(string $id): ResponseInterface
    {
        $m = new AnnouncementModel();
        $announcement = $m->find($id);
        if (! $announcement) {
            return $this->fail('Announcement not found.', 404);
        }
        if ($announcement['status'] === 'published') {
            return $this->fail('This announcement is already published.', 409, 'ALREADY_PUBLISHED');
        }

        $now = $this->now();
        $m->update($id, ['status' => 'published', 'published_at' => $now, 'updated_at' => $now]);

        $recipients = $announcement['audience'] === 'all'
            ? (new EmployeeModel())->findColumn('id') ?? []
            : $m->targetIds($id);
        (new Notifier())->notify(
            $recipients,
            'announcement',
            $announcement['title'],
            mb_substr(trim(strip_tags((string) $announcement['content'])), 0, 140),
            'announcement',
            $id,
            "announcement:{$id}"
        );
        return $this->json(['announcement' => $this->detail($id)]);
    }

    /** @return array|string validated fields, or an error message */
    private function payload(array $b): array|string
    {
        $title = trim((string) ($b['title'] ?? ''));
        if ($title === '' || mb_strlen($title) > 200) {
            return 'Title is required (max 200 characters).';
        }
        $audience = (string) ($b['audience'] ?? 'all');
        if (! in_array($audience, ['all', 'selected'], true)) {
            return 'Audience must be all or selected.';
        }
        $employeeIds = array_values(array_unique(array_filter((array) ($b['employeeIds'] ?? []), 'is_string')));
        if ($audience === 'selected') {
            if ($employeeIds === []) {
                return 'Select at least one employee for a targeted announcement.';
            }
            if ((new EmployeeModel())->whereIn('id', $employeeIds)->countAllResults() !== count($employeeIds)) {
                return 'One or more selected employees were not found.';
            }
        }

        return [
            'row' => [
                'title'    => $title,
                'content'  => trim((string) ($b['content'] ?? '')),
                'audience' => $audience,
            ],
            'employeeIds' => $audience === 'selected' ? $employeeIds : [],
        ];
    }

    private function detail(string $id): array
    {
        $m = new AnnouncementModel();
        return AnnouncementModel::map($m->find($id)) + ['employeeIds' => $m->targetIds($id)];
    }
}
