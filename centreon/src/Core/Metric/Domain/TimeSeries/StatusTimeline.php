<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * For more information : contact@centreon.com
 *
 */

declare(strict_types=1);

namespace Core\Metric\Domain\TimeSeries;

/**
 * Status graph: averaged status values (100 OK, 75 WARNING, 0 CRITICAL, unknown: no value) turned
 * into the intervals of each status. Shared by the RRD and VictoriaMetrics readers.
 */
final class StatusTimeline
{
    /**
     * @param iterable<array{0: int|string, 1: float|int|null}> $rows [timestamp, average status value]
     *
     * @return array{
     *     critical: list<array{start: string, end: string}>,
     *     warning: list<array{start: string, end: string}>,
     *     ok: list<array{start: string, end: string}>,
     *     unknown: list<array{start: string, end: string}>
     * }
     */
    public static function fromRows(iterable $rows): array
    {
        $statuses = ['critical' => [], 'warning' => [], 'ok' => [], 'unknown' => []];

        $lastStatus = null;
        $interval = ['start' => '', 'end' => ''];
        $time = '';
        foreach ($rows as $row) {
            $time = (string) $row[0];
            $currentStatus = self::statusOf($row[1]);
            if ($lastStatus === null) {
                $interval = ['start' => $time, 'end' => ''];
                $lastStatus = $currentStatus;
            } elseif ($lastStatus !== $currentStatus) {
                $interval['end'] = $time;
                $statuses[$lastStatus][] = $interval;
                $lastStatus = $currentStatus;
                $interval = ['start' => $time, 'end' => ''];
            }
        }

        if ($lastStatus !== null) {
            $interval['end'] = $time;
            $statuses[$lastStatus][] = $interval;
        }

        return $statuses;
    }

    /**
     * @return 'critical'|'warning'|'ok'|'unknown'
     */
    private static function statusOf(float|int|null $value): string
    {
        return match (true) {
            $value === null => 'unknown',
            $value < 75 => 'critical',
            (float) $value === 100.0 => 'ok',
            $value > 74 => 'warning',
            default => 'unknown',
        };
    }
}
