<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Attendance extends BaseConfig
{
    /** Reject check-in when the GPS accuracy radius exceeds this (metres). */
    public float $maxAccuracyMeters = 50;

    /** Reject check-in when the device fix is older than this (seconds). */
    public int $maxLocationAgeSeconds = 120;

    /** Mobile tracking interval handed to the app (seconds). */
    public int $trackingIntervalSeconds = 180;

    /** Mobile sync interval for queued location records (seconds). */
    public int $syncIntervalSeconds = 300;

    /** A last-known location older than this is shown as "stale" (seconds). */
    public int $staleLocationSeconds = 600;

    /** A location received this long after capture is flagged as delayed (seconds). */
    public int $delayedAfterSeconds = 300;

    /** A silence longer than this inside a session is reported as an interruption (seconds). */
    public int $gapThresholdSeconds = 600;

    /** Check-ins up to this many minutes after a project's start time are on time. */
    public int $lateGraceMinutes = 10;

    /** Project timezone used when a project does not set one. */
    public string $defaultTimezone = 'Asia/Karachi';

    /** Maximum location records accepted per upload request. */
    public int $maxLocationBatch = 200;
}
