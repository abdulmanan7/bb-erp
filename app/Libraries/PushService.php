<?php

namespace App\Libraries;

use App\Models\DevicePushTokenModel;
use Config\Mobile;

/** Firebase Cloud Messaging (HTTP v1). FCM delivers to iOS through APNs when the APNs key is uploaded to Firebase. */
final class PushService
{
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';
    private const CACHE_KEY = 'fcm_access_token';

    private Mobile $config;

    public function __construct()
    {
        $this->config = config(Mobile::class);
    }

    public function isConfigured(): bool
    {
        return $this->config->fcmProjectId !== '' && is_file($this->config->fcmServiceAccountPath);
    }

    /**
     * @param list<array{token: string, user_id: string}> $devices
     * @return bool true if at least one device accepted the message
     */
    public function send(array $devices, string $title, string $body, array $data = []): bool
    {
        $accessToken = $this->accessToken();
        if ($accessToken === null || $devices === []) {
            return false;
        }

        $delivered = false;
        $url = 'https://fcm.googleapis.com/v1/projects/' . $this->config->fcmProjectId . '/messages:send';
        foreach ($devices as $device) {
            $response = service('curlrequest')->request('POST', $url, [
                'headers'     => ['Authorization' => 'Bearer ' . $accessToken],
                'json'        => ['message' => [
                    'token'        => $device['token'],
                    'notification' => ['title' => $title, 'body' => $body],
                    'data'         => array_map('strval', $data),
                ]],
                'http_errors' => false,
            ]);

            if ($response->getStatusCode() === 200) {
                $delivered = true;
            } elseif ($response->getStatusCode() === 404) {
                (new DevicePushTokenModel())->removeToken($device['token'], $device['user_id']);
            }
        }
        return $delivered;
    }

    private function accessToken(): ?string
    {
        if (! $this->isConfigured()) {
            return null;
        }
        $cached = cache()->get(self::CACHE_KEY);
        if (is_string($cached)) {
            return $cached;
        }

        $account = json_decode((string) file_get_contents($this->config->fcmServiceAccountPath), true);
        if (! is_array($account) || empty($account['private_key']) || empty($account['client_email'])) {
            return null;
        }

        $now = time();
        $header = $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claims = $this->base64Url(json_encode([
            'iss'   => $account['client_email'],
            'scope' => self::SCOPE,
            'aud'   => self::TOKEN_URL,
            'iat'   => $now,
            'exp'   => $now + 3600,
        ]));
        if (! openssl_sign("{$header}.{$claims}", $signature, $account['private_key'], OPENSSL_ALGO_SHA256)) {
            return null;
        }

        $response = service('curlrequest')->request('POST', self::TOKEN_URL, [
            'form_params' => [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => "{$header}.{$claims}." . $this->base64Url($signature),
            ],
            'http_errors' => false,
        ]);
        $token = json_decode($response->getBody(), true)['access_token'] ?? null;
        if (! is_string($token)) {
            return null;
        }

        cache()->save(self::CACHE_KEY, $token, 3000);
        return $token;
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
