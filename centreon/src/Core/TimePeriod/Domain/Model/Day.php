<?php

/*
 * Copyright 2005 - 2022 Centreon (https://www.centreon.com/)
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

class Day
{
    /** @var int ISO 8601 numeric representation of the day of the week (1 for monday) */
    private int $day;

    private TimeRange $timeRange;

    /**
     * @param int $day
     * @param TimeRange $timeRange
     *
     * @throws \Assert\AssertionFailedException
     */
    public function __construct(int $day, TimeRange $timeRange)
    {
        Assertion::min($day, 1, 'TimePeriodDay::day');
        Assertion::max($day, 7, 'TimePeriodDay::day');
        $this->day = $day;
        $this->timeRange = $timeRange;
    }

    /**
     * @return int
     */
    public function getDay(): int
    {
        return $this->day;
    }

    /**
     * @return TimeRange
     */
    public function getTimeRange(): TimeRange
    {
        return $this->timeRange;
    }
}
