<?php

namespace App\Controllers\Api;

use App\Models\HeadModel;
use App\Models\SubHeadModel;
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
        if (array_key_exists('subHeads', $b)) {
            $this->syncSubHeads($id, (array) $b['subHeads']);
        }
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
        if (array_key_exists('subHeads', $b)) {
            $this->syncSubHeads($id, (array) $b['subHeads']);
        }
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
        db_connect()->table('sub_heads')->where('head_id', $id)->delete();
        return $this->json(['ok' => true]);
    }

    /**
     * Reconciles a head's sub-head list: keeps existing rows (by id) so
     * vouchers pointing at them stay linked, inserts new ones, drops removed.
     */
    private function syncSubHeads(string $headId, array $subs): void
    {
        $m = new SubHeadModel();
        $keep = [];
        foreach ($subs as $s) {
            $name = trim((string) (is_array($s) ? ($s['name'] ?? '') : $s));
            if ($name === '') {
                continue;
            }
            $id = is_array($s) ? (string) ($s['id'] ?? '') : '';
            if ($id !== '' && $m->where('id', $id)->where('head_id', $headId)->first()) {
                $m->update($id, ['name' => $name]);
                $keep[] = $id;
            } else {
                $newId = $this->newId();
                $m->insert(['id' => $newId, 'head_id' => $headId, 'name' => $name, 'created_at' => $this->now()]);
                $keep[] = $newId;
            }
        }
        $m->where('head_id', $headId)->whereNotIn('id', $keep ?: [''])->delete();
    }
}
