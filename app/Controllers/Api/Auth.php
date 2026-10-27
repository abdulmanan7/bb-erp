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

        if (! $user || ! UserModel::passwordMatches($p, (string) $user['password'])) {
            return $this->fail('Invalid username or password.', 401);
        }

        $users->rehashIfPlain($user, $p);

        return $this->grant(UserModel::sessionPayload($user));
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
}
