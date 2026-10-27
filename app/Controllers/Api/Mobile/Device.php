<?php

namespace App\Controllers\Api\Mobile;

use App\Controllers\Api\BaseApiController;
use App\Libraries\Clock;
use App\Models\DevicePushTokenModel;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Attendance;
use Config\Mobile;

class Device extends BaseApiController
{
    /** Runtime settings the app needs for tracking and sync. Change them here, not in the app. */
    public function config(): ResponseInterface
    {
        $attendance = config(Attendance::class);
        return $this->json([
            'trackingIntervalSeconds' => $attendance->trackingIntervalSeconds,
            'syncIntervalSeconds'     => $attendance->syncIntervalSeconds,
            'maxAccuracyMeters'       => $attendance->maxAccuracyMeters,
            'maxLocationAgeSeconds'   => $attendance->maxLocationAgeSeconds,
            'maxLocationBatch'        => $attendance->maxLocationBatch,
            'serverTime'              => Clock::iso(Clock::now()),
        ]);
    }

    public function registerPushToken(): ResponseInterface
    {
        $b = $this->body();
        $token = trim((string) ($b['token'] ?? ''));
        $platform = (string) ($b['platform'] ?? '');
        if (strlen($token) < 20 || strlen($token) > 255 || ! in_array($platform, config(Mobile::class)->platforms, true)) {
            return $this->fail('A push token and a valid platform (android or ios) are required.');
        }

        (new DevicePushTokenModel())->register($this->user(), [
            'token'      => $token,
            'platform'   => $platform,
            'deviceId'   => mb_substr(trim((string) ($b['deviceId'] ?? '')), 0, 100),
            'deviceInfo' => mb_substr(trim((string) ($b['deviceInfo'] ?? '')), 0, 150),
        ]);
        return $this->json(['ok' => true]);
    }

    public function removePushToken(): ResponseInterface
    {
        (new DevicePushTokenModel())->removeToken(trim((string) ($this->body()['token'] ?? '')), $this->user()['uid']);
        return $this->json(['ok' => true]);
    }
}
