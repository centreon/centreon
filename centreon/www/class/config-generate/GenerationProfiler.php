<?php

/*
 * DO NOT MERGE — throwaway instrumentation for MON-214480 (profiling only).
 * Adds timers and memory snapshots around existing config-generation calls.
 * Does not change any generation logic.
 */

/**
 * Class
 *
 * @class GenerationProfiler
 */
final class GenerationProfiler
{
    /** @var array<string, array{count: int, time_s: float}> */
    private static array $timers = [];

    private static ?float $runStart = null;

    private static int $pollerId = 0;

    public static function startRun(int $pollerId): void
    {
        self::$timers = [];
        self::$runStart = microtime(true);
        self::$pollerId = $pollerId;
    }

    public static function mark(): float
    {
        return microtime(true);
    }

    public static function record(string $label, float $startMark): void
    {
        $elapsed = microtime(true) - $startMark;
        self::$timers[$label]['count'] = (self::$timers[$label]['count'] ?? 0) + 1;
        self::$timers[$label]['time_s'] = (self::$timers[$label]['time_s'] ?? 0) + $elapsed;
    }

    public static function dumpRun(int $hostCount = 0): void
    {
        if (self::$runStart === null) {
            return;
        }

        $payload = [
            'ts' => date('c'),
            'poller_id' => self::$pollerId,
            'host_count' => $hostCount,
            'total_time_s' => microtime(true) - self::$runStart,
            'peak_memory_bytes' => memory_get_peak_usage(true),
            'timers' => self::$timers,
        ];

        @file_put_contents('/tmp/generation-profile.ndjson', json_encode($payload) . "\n", FILE_APPEND | LOCK_EX);
        self::$runStart = null;
    }
}
