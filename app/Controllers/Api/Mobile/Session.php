<?php

namespace App\Controllers\Api\Mobile;

use App\Controllers\Api\BaseApiController;
use App\Models\DevicePushTokenModel;
use App\Models\MobileSessionModel;
use App\Models\UserModel;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Mobile;

class Session extends BaseApiController
{
    public function login(): ResponseInterface
    {
        $b = $this->body();
        $username = trim((string) ($b['username'] ?? ''));
        $password = (string) ($b['password'] ?? '');
        $device = $this->device($b);
        if ($device === null) {
            return $this->fail('A deviceId and a valid platform (android or ios) are required.');
        }

        $config = config(Mobile::class);
        $throttleKey = 'mobile_login_' . md5($this->request->getIPAddress() . '|' . strtolower($username));
        if (! service('throttler')->check($throttleKey, $config->loginMaxAttempts, $config->loginWindowSeconds)) {
            return $this->fail('Too many login attempts. Please try again later.', 429, 'RATE_LIMITED');
        }

        $users = new UserModel();
        $user = $users->findByUsername($username);
        if (! $user || $user['type'] !== 'staff' || ! UserModel::passwordMatches($password, (string) $user['password'])) {
            return $this->fail('Invalid username or password.', 401, 'INVALID_CREDENTIALS');
        }
        $users->rehashIfPlain($user, $password);

        return $this->json([
            'user'   => UserModel::sessionPayload($user),
            'tokens' => (new MobileSessionModel())->issue($user, $device),
        ]);
    }

    public function refresh(): ResponseInterface
    {
        $tokens = (new MobileSessionModel())->rotate((string) ($this->body()['refreshToken'] ?? ''));
        if (! $tokens) {
            return $this->fail('Your session has ended. Please sign in again.', 401, 'SESSION_EXPIRED');
        }
        return $this->json(['tokens' => $tokens]);
    }

    public function logout(): ResponseInterface
    {
        $sessions = new MobileSessionModel();
        $session = $sessions->sessionForRefreshToken((string) ($this->body()['refreshToken'] ?? ''));
        if ($session) {
            $sessions->revoke($session['id']);
            (new DevicePushTokenModel())->removeForDevice($session['user_id'], $session['device_id']);
        }
        return $this->json(['ok' => true]);
    }

    /** @return array{deviceId: string, deviceName: string, platform: string}|null */
    private function device(array $b): ?array
    {
        $deviceId = trim((string) ($b['deviceId'] ?? ''));
        $platform = (string) ($b['platform'] ?? '');
        if (strlen($deviceId) < 8 || strlen($deviceId) > 100 || ! in_array($platform, config(Mobile::class)->platforms, true)) {
            return null;
        }
        return [
            'deviceId'   => $deviceId,
            'deviceName' => mb_substr(trim((string) ($b['deviceName'] ?? '')), 0, 150),
            'platform'   => $platform,
        ];
    }
}
