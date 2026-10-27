<?php

namespace App\Models;

use App\Libraries\Clock;
use CodeIgniter\Model;
use Config\Mobile;

class MobileSessionModel extends Model
{
    protected $table = 'mobile_sessions';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = [
        'id', 'user_id', 'employee_id', 'device_id', 'device_name', 'platform',
        'access_token_hash', 'access_expires_at', 'refresh_token_hash', 'expires_at',
        'last_used_at', 'revoked_at', 'created_at',
    ];

    private const LAST_USED_WRITE_INTERVAL = 300;

    /** Starts a mobile login. Raw tokens are returned once; only their hashes are stored. */
    public function issue(array $user, array $device): array
    {
        $id = bin2hex(random_bytes(16));
        $now = Clock::now();
        $expiresAt = gmdate('Y-m-d H:i:s', time() + config(Mobile::class)->refreshTokenTtl);
        $tokens = $this->newTokens();

        $this->insert([
            'id'           => $id,
            'user_id'      => $user['id'],
            'employee_id'  => $user['employee_id'],
            'device_id'    => $device['deviceId'],
            'device_name'  => $device['deviceName'],
            'platform'     => $device['platform'],
            'expires_at'   => $expiresAt,
            'last_used_at' => $now,
            'created_at'   => $now,
        ] + $this->tokenColumns($tokens, $expiresAt));

        return $this->tokenResponse($id, $tokens, $expiresAt);
    }

    /** Resolves a bearer access token to the employee payload, or null if invalid, expired or revoked. */
    public function userForAccessToken(string $accessToken): ?array
    {
        $now = Clock::now();
        $session = $this->where('access_token_hash', hash('sha256', $accessToken))
            ->where('access_expires_at >', $now)
            ->where('expires_at >', $now)
            ->where('revoked_at', null)
            ->first();
        if (! $session) {
            return null;
        }

        $user = $this->activeUser($session['user_id']);
        if ($user && $this->isStale($session['last_used_at'], $now)) {
            $this->update($session['id'], ['last_used_at' => $now]);
        }
        return $user ? UserModel::sessionPayload($user) : null;
    }

    /** Rotates the refresh token. Presenting an already-rotated token revokes the whole session. */
    public function rotate(string $refreshToken): ?array
    {
        $session = $this->sessionForRefreshToken($refreshToken);
        if (! $session) {
            return null;
        }

        $now = Clock::now();
        $tokens = $this->newTokens();
        $this->update($session['id'], ['last_used_at' => $now] + $this->tokenColumns($tokens, $session['expires_at']));
        return $this->tokenResponse($session['id'], $tokens, $session['expires_at']);
    }

    /** Returns the live session for a refresh token. A token that does not match the current one revokes the session. */
    public function sessionForRefreshToken(string $refreshToken): ?array
    {
        [$id, $secret] = array_pad(explode('.', $refreshToken, 2), 2, '');
        $session = $id !== '' && $secret !== '' ? $this->find($id) : null;
        if (! $session || $session['revoked_at'] !== null) {
            return null;
        }
        if (! hash_equals($session['refresh_token_hash'], hash('sha256', $secret))) {
            $this->revoke($id);
            return null;
        }

        if ($session['expires_at'] <= Clock::now() || ! $this->activeUser($session['user_id'])) {
            $this->revoke($id);
            return null;
        }
        return $session;
    }

    public function revoke(string $id): void
    {
        $this->update($id, ['revoked_at' => Clock::now()]);
    }

    public function revokeForUser(string $userId): void
    {
        $this->where('user_id', $userId)->where('revoked_at', null)->set(['revoked_at' => Clock::now()])->update();
    }

    private function activeUser(string $userId): ?array
    {
        $user = (new UserModel())->find($userId);
        return $user && $user['type'] === 'staff' ? $user : null;
    }

    private function isStale(?string $lastUsedAt, string $now): bool
    {
        return $lastUsedAt === null || Clock::timestamp($now) - Clock::timestamp($lastUsedAt) >= self::LAST_USED_WRITE_INTERVAL;
    }

    private function newTokens(): array
    {
        return ['access' => $this->secret(), 'refresh' => $this->secret()];
    }

    private function tokenColumns(array $tokens, string $expiresAt): array
    {
        return [
            'access_token_hash'  => hash('sha256', $tokens['access']),
            'access_expires_at'  => $this->accessExpiry($expiresAt),
            'refresh_token_hash' => hash('sha256', $tokens['refresh']),
        ];
    }

    private function tokenResponse(string $id, array $tokens, string $expiresAt): array
    {
        return [
            'accessToken'      => $tokens['access'],
            'accessExpiresAt'  => Clock::iso($this->accessExpiry($expiresAt)),
            'refreshToken'     => $id . '.' . $tokens['refresh'],
            'refreshExpiresAt' => Clock::iso($expiresAt),
        ];
    }

    private function accessExpiry(string $refreshExpiresAt): string
    {
        return gmdate('Y-m-d H:i:s', min(time() + config(Mobile::class)->accessTokenTtl, Clock::timestamp($refreshExpiresAt)));
    }

    private function secret(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }
}
