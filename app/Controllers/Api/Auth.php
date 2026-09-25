<?php

namespace App\Controllers\Api;

use App\Models\SettingModel;
use App\Models\UserModel;
use CodeIgniter\HTTP\ResponseInterface;

class Auth extends BaseApiController
{
    public function login(): ResponseInterface
    {
        $b = $this->body();
        $u = trim((string) ($b['username'] ?? ''));
        $p = (string) ($b['password'] ?? '');

        $users = new UserModel();
        $user = $users->findByUsername($u);

        if (! $user || ! $this->passwordMatches($p, (string) $user['password'])) {
            return $this->fail('Invalid username or password.', 401);
        }

        // Upgrade a plain-text password to a hash on first successful login
        if (! $this->isHashed((string) $user['password'])) {
            $users->update($user['id'], ['password' => password_hash($p, PASSWORD_DEFAULT)]);
        }

        $payload = [
            'role'     => $user['type'] === 'admin' ? 'admin' : 'employee',
            'uid'      => $user['id'],
            'name'     => $user['name'],
            'username' => $user['username'],
        ];
        // For staff, `id` is the linked employee record (used for assigned heads)
        if ($payload['role'] === 'employee') {
            $payload['id'] = $user['employee_id'];
        }

        return $this->grant($payload);
    }

    public function logout(): ResponseInterface
    {
        session()->destroy();
        return $this->json(['ok' => true]);
    }

    public function me(): ResponseInterface
    {
        return $this->json([
            'user'     => session()->get('erp_user'),
            'settings' => (new SettingModel())->mapped(),
        ]);
    }

    private function grant(array $user): ResponseInterface
    {
        session()->regenerate(true);
        session()->set('erp_user', $user);
        return $this->json(['user' => $user]);
    }

    private function isHashed(string $stored): bool
    {
        return str_starts_with($stored, '$2y$') || str_starts_with($stored, '$argon2');
    }

    private function passwordMatches(string $input, string $stored): bool
    {
        if ($stored === '') {
            return false;
        }
        return $this->isHashed($stored)
            ? password_verify($input, $stored)
            : hash_equals($stored, $input);
    }
}
