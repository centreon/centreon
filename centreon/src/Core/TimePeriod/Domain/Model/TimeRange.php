<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

namespace Core\TimePeriod\Domain\Model;

use Centreon\Domain\Common\Assertion\Assertion;
use Core\TimePeriod\Domain\Exception\TimeRangeException;

/**
 * Value object.
 */
class TimeRange implements \Stringable
{
    /**
     * @var string TIME_RANGE_FULL_DAY_ALIAS The time range for the entire day in DOC
     */
    private const TIME_RANGE_FULL_DAY_ALIAS = '00:00-00:00';

    /** @var string Comma-delimited time range (00:00-12:00) for a particular day of the week. */
    private string $timeRange;

    /**
     * @param string $timeRange
     *
     * @throws \Assert\AssertionFailedException
     * @throws TimeRangeException
     */
    public function __construct(string $timeRange)
    {
        Assertion::minLength($timeRange, 11, 'TimeRange::timeRange');

        if (! $this->isValidTimeRangeFormat($timeRange)) {
            throw TimeRangeException::badTimeRangeFormat($timeRange);
        }
        if (! $this->areTimeRangesOrderedWithoutOverlap($timeRange)) {
            throw TimeRangeException::orderTimeIntervalsNotConsistent();
        }
        $this->timeRange = $timeRange;
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->timeRange;
    }

    /**
     * @return array<array{start: string, end: string}>
     */
    public function getRanges(): array
    {
        return $this->extractRange($this->timeRange);
    }

    /**
     * Check the format of the time range(s).
     * 00:00-12:00,13:00-14:00,...
     *
     * @param string $timeRange
     *
     * @return bool Return false if the time range(s) is wrong formatted
     */
    private function isValidTimeRangeFormat(string $timeRange): bool
    {

        return (bool) preg_match(
            "/^((?'time_range'(?'time'(([[0-1][0-9]|2[0-3]):[0-5][0-9]))-((?&time)|24:00))(,(?&time_range))*)$/",
            $timeRange
        );
    }

    /**
     * We check whether the time intervals are consistent in the defined order.
     *  - The end time of a time range cannot be greater than or equal to the start time of a time range.
     *  - The start of a new time range cannot be less than or equal to the end of the previous time range.
     *
     * @param string $timeRanges Time ranges (00:00-12:00,13:00-14:00,...)
     *
     * @return bool
     */
    private function areTimeRangesOrderedWithoutOverlap(string $timeRanges): bool
    {
        $previousEndTime = null;
        if ($timeRanges === self::TIME_RANGE_FULL_DAY_ALIAS) {
            return true;
        }
        foreach (explode(',', $timeRanges) as $timeRange) {
            [$start, $end] = explode('-', $timeRange);
            // The start of a new time range cannot be less than or equal to the end of the previous time range
            if ($previousEndTime !== null && strtotime($previousEndTime) >= strtotime($start)) {
                return false;
            }
            // The end time of a time range cannot be greater than or equal to the start time of a time range
            if (strtotime($start) >= strtotime($end)) {
                return false;
            }
            $previousEndTime = $end;
        }

        return true;
    }

    /**
     * @param string $rule
     *
     * @return array<array{start: string, end: string}>
     */
    private function extractRange(string $rule): array
    {
        return $this->extractRanges($rule);
    }

    /**
     * @param string $rule
     *
     * @return array<array{start: string, end: string}>
     */
    private function extractRanges(string $rule): array
    {
        $timePeriodRanges = explode(',', trim($rule));

        $timeRanges = [];
        foreach ($timePeriodRanges as $timePeriodRange) {
            [$start, $end] = explode('-', trim($timePeriodRange));
            $timeRanges[] = ['start' => $start, 'end' => $end];
        }

        return $timeRanges;
    }
}
