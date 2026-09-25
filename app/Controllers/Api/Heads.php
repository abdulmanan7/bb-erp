<?php

namespace App\Controllers\Api;

use App\Models\HeadModel;
use CodeIgniter\HTTP\ResponseInterface;

class Heads extends BaseApiController
{
    public function create(): ResponseInterface
    {
        $b = $this->body();
        $name = trim((string) ($b['name'] ?? ''));
        if ($name === '') {
            return $this->fail('Head name required.');
        }
        $m = new HeadModel();
        $id = $this->newId();
        $m->insert([
            'id'         => $id,
            'name'       => $name,
            'type'       => ($b['type'] ?? '') === 'Income' ? 'Income' : 'Expense',
            'category'   => trim((string) ($b['group'] ?? '')),
            'created_at' => $this->now(),
        ]);
        return $this->json(['head' => HeadModel::map($m->find($id))]);
    }

    public function update($id = null): ResponseInterface
    {
        $m = new HeadModel();
        if (! $m->find($id)) {
            return $this->fail('Head not found.', 404);
        }
        $b = $this->body();
        $name = trim((string) ($b['name'] ?? ''));
        if ($name === '') {
            return $this->fail('Head name required.');
        }
        $m->update($id, [
            'name'     => $name,
            'type'     => ($b['type'] ?? '') === 'Income' ? 'Income' : 'Expense',
            'category' => trim((string) ($b['group'] ?? '')),
        ]);
        return $this->json(['head' => HeadModel::map($m->find($id))]);
    }

    public function delete($id = null): ResponseInterface
    {
        $m = new HeadModel();
        if (! $m->find($id)) {
            return $this->fail('Head not found.', 404);
        }
        $m->delete($id);
        db_connect()->table('employee_heads')->where('head_id', $id)->delete();
        return $this->json(['ok' => true]);
    }
}
