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
 * Functions that reduce a series to one value, as the rrdtool VDEF functions (rrd_graph.c, vdef_calc):
 * unknown values are NAN; LAST and FIRST keep infinite values, the others only use finite ones.
 */
final class Consolidation
{
    public const FUNCTIONS = [
        'AVERAGE', 'MINIMUM', 'MAXIMUM', 'LAST', 'FIRST', 'TOTAL', 'STDEV', 'PERCENT', 'PERCENTNAN',
    ];

    /**
     * @param string $function one of FUNCTIONS
     * @param list<float> $values
     * @param int $step seconds between two values (TOTAL)
     * @param float|null $parameter percentile of PERCENT and PERCENTNAN
     *
     * @throws \InvalidArgumentException
     */
    public static function compute(string $function, array $values, int $step, ?float $parameter = null): float
    {
        return match ($function) {
            'AVERAGE' => self::average($values),
            'MINIMUM' => self::extremum($values, false),
            'MAXIMUM' => self::extremum($values, true),
            'LAST' => self::firstKnown(array_reverse($values)),
            'FIRST' => self::firstKnown($values),
            'TOTAL' => self::total($values, $step),
            'STDEV' => self::standardDeviation($values),
            'PERCENT' => self::percent($values, $parameter ?? throw new \InvalidArgumentException('PERCENT needs a percentile'), false),
            'PERCENTNAN' => self::percent($values, $parameter ?? throw new \InvalidArgumentException('PERCENTNAN needs a percentile'), true),
            default => throw new \InvalidArgumentException(sprintf('Unknown consolidation function "%s"', $function)),
        };
    }

    /**
     * @param list<float> $values
     */
    private static function average(array $values): float
    {
        $finite = self::finite($values);

        return $finite === [] ? NAN : array_sum($finite) / count($finite);
    }

    /**
     * @param list<float> $values
     */
    private static function total(array $values, int $step): float
    {
        $finite = self::finite($values);

        return $finite === [] ? NAN : array_sum($finite) * $step;
    }

    /**
     * Starts from the first known value (which may be infinite), then compares the finite ones.
     *
     * @param list<float> $values
     */
    private static function extremum(array $values, bool $isMaximum): float
    {
        $result = NAN;
        foreach ($values as $value) {
            if (is_nan($result)) {
                $result = $value;
            } elseif (is_finite($value) && ($isMaximum ? $value > $result : $value < $result)) {
                $result = $value;
            }
        }

        return $result;
    }

    /**
     * @param list<float> $values
     */
    private static function firstKnown(array $values): float
    {
        foreach ($values as $value) {
            if (! is_nan($value)) {
                return $value;
            }
        }

        return NAN;
    }

    /**
     * Population standard deviation.
     *
     * @param list<float> $values
     */
    private static function standardDeviation(array $values): float
    {
        $finite = self::finite($values);
        if ($finite === []) {
            return NAN;
        }
        $count = count($finite);
        $average = array_sum($finite) / $count;
        $squares = array_sum(array_map(static fn (float $value): float => $value * $value, $finite));

        return sqrt(max(0.0, $squares / $count - $average * $average));
    }

    /**
     * PERCENT sorts unknown values first; PERCENTNAN ignores them.
     *
     * @param list<float> $values
     */
    private static function percent(array $values, float $percentile, bool $ignoreUnknown): float
    {
        if ($ignoreUnknown) {
            $values = array_values(array_filter($values, static fn (float $value): bool => ! is_nan($value)));
        }
        if ($values === []) {
            return NAN;
        }
        usort($values, static function (float $left, float $right): int {
            if (is_nan($left) || is_nan($right)) {
                return (int) is_nan($right) - (int) is_nan($left);
            }

            return $left <=> $right;
        });
        $position = (int) round($percentile * (count($values) - 1) / 100);

        return $values[max(0, min(count($values) - 1, $position))];
    }

    /**
     * @param list<float> $values
     *
     * @return list<float>
     */
    private static function finite(array $values): array
    {
        return array_values(array_filter($values, static fn (float $value): bool => is_finite($value)));
    }
}
