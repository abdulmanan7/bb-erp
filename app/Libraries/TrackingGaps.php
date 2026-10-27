<?php

namespace App\Libraries;

final class TrackingGaps
{
    /**
     * Silences longer than the threshold between the session start, each recorded point and the session end.
     *
     * @param list<string> $times UTC datetimes of recorded points, ascending
     * @return list<array{from: string, to: string, seconds: int}>
     */
    public static function between(string $start, array $times, string $end, int $thresholdSeconds): array
    {
        $timeline = [$start, ...$times, $end];
        $gaps = [];
        for ($i = 1, $count = count($timeline); $i < $count; $i++) {
            $seconds = Clock::timestamp($timeline[$i]) - Clock::timestamp($timeline[$i - 1]);
            if ($seconds > $thresholdSeconds) {
                $gaps[] = ['from' => Clock::iso($timeline[$i - 1]), 'to' => Clock::iso($timeline[$i]), 'seconds' => $seconds];
            }
        }
        return $gaps;
    }
}
