<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Libraries\ApiUser;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\HTTP\ResponseInterface;
use DateTimeImmutable;

abstract class BaseApiController extends BaseController
{
    protected function user(): ?array
    {
        return ApiUser::get() ?? session()->get('erp_user');
    }

    protected function json($data, int $code = 200): ResponseInterface
    {
        return $this->response->setStatusCode($code)->setJSON($data);
    }

    protected function fail(string $message, int $code = 400, ?string $errorCode = null): ResponseInterface
    {
        return $this->json(array_filter(['error' => $message, 'code' => $errorCode]), $code);
    }

    protected function paginate(BaseBuilder $query, callable $map): ResponseInterface
    {
        $perPage = min(100, max(1, (int) ($this->request->getGet('perPage') ?? 20)));
        $page = max(1, (int) ($this->request->getGet('page') ?? 1));
        $total = (clone $query)->countAllResults(false);
        $rows = $query->limit($perPage, ($page - 1) * $perPage)->get()->getResultArray();

        return $this->json([
            'items'   => array_map($map, $rows),
            'page'    => $page,
            'perPage' => $perPage,
            'total'   => $total,
        ]);
    }

    protected function date(mixed $value): ?string
    {
        $date = is_string($value) ? DateTimeImmutable::createFromFormat('!Y-m-d', $value) : false;
        return $date && $date->format('Y-m-d') === $value ? $value : null;
    }

    protected function body(): array
    {
        return (array) ($this->request->getJSON(true) ?? []);
    }

    protected function newId(): string
    {
        return bin2hex(random_bytes(8));
    }

    protected function validDate($value): string
    {
        $value = (string) $value;
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : date('Y-m-d');
    }

    protected function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}
