<?php

namespace App\Controllers\Api;

use App\Libraries\Attachments;
use App\Models\EmployeeModel;
use App\Models\HeadModel;
use App\Models\ProjectAssignmentModel;
use App\Models\ProjectModel;
use App\Models\SpendingModel;
use App\Models\SubHeadModel;
use CodeIgniter\HTTP\ResponseInterface;

class Spendings extends BaseApiController
{
    private const DIR = 'spendings';

    public function create(): ResponseInterface
    {
        $b = $this->body();
        $user = $this->user();
        $data = $this->payload($b, $user);
        if (is_string($data)) {
            return $this->fail($data);
        }

        $id = $this->newId();
        $file = Attachments::save(self::DIR, $id, $b['attachment'] ?? null);
        if (is_string($file)) {
            return $this->fail($file);
        }

        $m = new SpendingModel();
        $m->insert($data + [
            'id'         => $id,
            'attachment' => $file === true ? (string) ($b['attachment']['name'] ?? '') : '',
            'created_by' => $user['name'],
            'created_at' => $this->now(),
        ]);
        return $this->json(['spending' => SpendingModel::map($m->find($id))]);
    }

    public function update($id = null): ResponseInterface
    {
        $m = new SpendingModel();
        $existing = $m->find($id);
        if (! $existing) {
            return $this->fail('Entry not found.', 404);
        }
        $b = $this->body();
        $data = $this->payload($b, $this->user());
        if (is_string($data)) {
            return $this->fail($data);
        }

        $file = Attachments::save(self::DIR, $id, $b['attachment'] ?? null);
        if (is_string($file)) {
            return $this->fail($file);
        }
        $attachment = $existing['attachment'];
        if ($file === true) {
            $attachment = (string) ($b['attachment']['name'] ?? '');
        } elseif (! empty($b['removeAttachment'])) {
            Attachments::delete(self::DIR, $id);
            $attachment = '';
        }

        $m->update($id, $data + ['attachment' => $attachment]);
        return $this->json(['spending' => SpendingModel::map($m->find($id))]);
    }

    public function delete($id = null): ResponseInterface
    {
        $m = new SpendingModel();
        if (! $m->find($id)) {
            return $this->fail('Entry not found.', 404);
        }
        $m->delete($id);
        Attachments::delete(self::DIR, $id);
        return $this->json(['ok' => true]);
    }

    /**
     * Streams the stored file. Staff may only fetch their own entries' files.
     */
    public function attachment($id = null): ResponseInterface
    {
        $s = (new SpendingModel())->find($id);
        if (! $s || $s['attachment'] === '') {
            return $this->fail('Attachment not found.', 404);
        }
        $user = $this->user();
        if ($user['role'] !== 'admin' && $s['employee_id'] !== $user['id']) {
            return $this->fail('Access denied.', 403);
        }

        $path = Attachments::file(self::DIR, $id);
        if ($path === null) {
            return $this->fail('Attachment file missing.', 404);
        }

        return $this->response
            ->setHeader('Content-Type', Attachments::mime($s['attachment']))
            ->setHeader('Content-Disposition', 'inline; filename="' . basename($s['attachment']) . '"')
            ->setBody(file_get_contents($path));
    }

    /** @return array|string validated column values, or an error message */
    private function payload(array $b, array $user): array|string
    {
        $kind = (string) ($b['kind'] ?? '');
        if (! in_array($kind, ['advance', 'spend'], true)) {
            return 'Select a valid entry type.';
        }

        $isAdmin = $user['role'] === 'admin';
        $employeeId = $isAdmin ? (string) ($b['employeeId'] ?? '') : (string) ($user['id'] ?? '');
        if (! (new EmployeeModel())->find($employeeId)) {
            return 'Select a valid employee.';
        }

        $projectId = (string) ($b['projectId'] ?? '');
        $project = (new ProjectModel())->find($projectId);
        if (! $project || $project['status'] !== 'active') {
            return 'Select an active project.';
        }
        $assigned = (new ProjectAssignmentModel())
            ->where(['project_id' => $projectId, 'employee_id' => $employeeId, 'status' => 'active'])
            ->first();
        if (! $isAdmin && ! $assigned) {
            return 'This project is not assigned to you.';
        }

        $headId = '';
        $subHeadId = '';
        if ($kind === 'spend') {
            $head = (new HeadModel())->find((string) ($b['headId'] ?? ''));
            if (! $head || $head['type'] !== 'Expense') {
                return 'Select a valid expense head.';
            }
            if (! $isAdmin && ! in_array($head['id'], (new EmployeeModel())->assignedHeadIds($employeeId), true)) {
                return 'This head is not assigned to you.';
            }
            $headId = $head['id'];
            $subHeadId = (string) ($b['subHeadId'] ?? '');
            if ($subHeadId !== '' && ! (new SubHeadModel())->where('id', $subHeadId)->where('head_id', $headId)->first()) {
                return 'Invalid sub-head.';
            }
        }

        $amount = (float) ($b['amount'] ?? 0);
        if ($amount <= 0) {
            return 'Enter an amount greater than zero.';
        }

        return [
            'kind'        => $kind,
            'employee_id' => $employeeId,
            'project_id'  => $projectId,
            'head_id'     => $headId,
            'sub_head_id' => $subHeadId,
            'spend_date'  => $this->validDate($b['date'] ?? ''),
            'amount'      => $amount,
            'party'       => trim((string) ($b['party'] ?? '')),
            'description' => trim((string) ($b['description'] ?? '')),
        ];
    }
}
