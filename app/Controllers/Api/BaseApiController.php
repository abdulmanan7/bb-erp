<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use CodeIgniter\HTTP\ResponseInterface;

abstract class BaseApiController extends BaseController
{
    protected function user(): ?array
    {
        return session()->get('erp_user');
    }

    protected function json($data, int $code = 200): ResponseInterface
    {
        return $this->response->setStatusCode($code)->setJSON($data);
    }

    protected function fail(string $message, int $code = 400): ResponseInterface
    {
        return $this->json(['error' => $message], $code);
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
