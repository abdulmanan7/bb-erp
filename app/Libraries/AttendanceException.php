<?php

namespace App\Libraries;

use RuntimeException;

/** Business-rule rejection with a stable machine-readable code for the mobile app. */
class AttendanceException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $status = 422,
    ) {
        parent::__construct($message);
    }
}
