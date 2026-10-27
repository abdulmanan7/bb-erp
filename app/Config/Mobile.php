<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Mobile extends BaseConfig
{
    /** Access token lifetime (seconds). Silently refreshed by the app. */
    public int $accessTokenTtl = 900;

    /** Absolute cap on a mobile login, from first sign-in (seconds). Default 365 days. */
    public int $refreshTokenTtl = 31536000;

    /** Failed login attempts allowed per IP+username within the window. */
    public int $loginMaxAttempts = 10;

    public int $loginWindowSeconds = 900;

    /** Platforms a device can register as. */
    public array $platforms = ['android', 'ios'];

    /** Firebase project and service-account JSON path. Push is skipped when either is empty. */
    public string $fcmProjectId = '';

    public string $fcmServiceAccountPath = '';
}
