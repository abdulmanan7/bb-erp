<?php

namespace App\Controllers\Api\Mobile;

use App\Controllers\Api\BaseApiController;
use App\Models\SpendingModel;
use CodeIgniter\HTTP\ResponseInterface;

class Spendings extends BaseApiController
{
    public function index(): ResponseInterface
    {
        $query = (new SpendingModel())->listQuery($this->user()['id'], (string) $this->request->getGet('kind'));
        return $this->paginate($query, [SpendingModel::class, 'map']);
    }

    public function show(string $id): ResponseInterface
    {
        $row = (new SpendingModel())->where(['id' => $id, 'employee_id' => $this->user()['id']])->first();
        return $row
            ? $this->json(['spending' => SpendingModel::map($row)])
            : $this->fail('Spending entry not found.', 404);
    }
}
