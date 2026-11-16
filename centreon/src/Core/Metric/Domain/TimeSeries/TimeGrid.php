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
 * Timestamps of a graph: multiples of the step, the value at T averaging the interval ]T - step, T],
 * laid out as rrdtool does (first row one step after the start rounded down, last row at the end
 * rounded up).
 */
final class TimeGrid
{
    /** Maximum number of points of a graph over its period. */
    public const MAX_POINTS = 500;

    /**
     * @param int $first first timestamp (multiple of $step)
     * @param int $last last timestamp (multiple of $step, >= $first)
     * @param int $step seconds between two timestamps
     */
    private function __construct(
        private readonly int $first,
        private readonly int $last,
        private readonly int $step,
    ) {
    }

    /**
     * @param int $start start of the period (timestamp)
     * @param int $end end of the period (timestamp)
     * @param int $minimumStep shortest step, in seconds: the check interval of the service
     *
     * @throws \InvalidArgumentException
     */
    public static function forPeriod(int $start, int $end, int $minimumStep): self
    {
        if ($end <= $start) {
            throw new \InvalidArgumentException('The end of the period must be after its start');
        }
        $step = self::computeStep($start, $end, $minimumStep);
        $first = intdiv($start, $step) * $step + $step;
        $last = intdiv($end + $step - 1, $step) * $step;

        return new self($first, max($first, $last), $step);
    }

    /**
     * About MAX_POINTS points over the period, never less than $minimumStep seconds apart.
     */
    public static function computeStep(int $start, int $end, int $minimumStep): int
    {
        return max(1, $minimumStep, (int) ceil(($end - $start) / self::MAX_POINTS));
    }

    public function getFirst(): int
    {
        return $this->first;
    }

    public function getLast(): int
    {
        return $this->last;
    }

    public function getStep(): int
    {
        return $this->step;
    }

    public function count(): int
    {
        return intdiv($this->last - $this->first, $this->step) + 1;
    }

    /**
     * @return list<int>
     */
    public function getTimestamps(): array
    {
        return range($this->first, $this->last, $this->step);
    }

    /**
     * @return int|null position of $timestamp in the grid, null when it is not one of its timestamps
     */
    public function indexOf(int $timestamp): ?int
    {
        if ($timestamp < $this->first || $timestamp > $this->last || ($timestamp - $this->first) % $this->step !== 0) {
            return null;
        }

        return intdiv($timestamp - $this->first, $this->step);
    }
}
