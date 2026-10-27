<?php

namespace App\Libraries;

use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/** Server-side UTC handling. Database datetimes are always stored in UTC. */
final class Clock
{
    public static function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }

    public static function timestamp(string $utc): int
    {
        return (int) strtotime($utc . ' UTC');
    }

    public static function iso(?string $utc): ?string
    {
        return $utc === null || $utc === '' ? null : gmdate('Y-m-d\TH:i:s\Z', self::timestamp($utc));
    }

    /** Parses a client-supplied ISO-8601 timestamp into a UTC database datetime. */
    public static function fromInput(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }
        try {
            return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        } catch (Throwable) {
            return null;
        }
    }
}
