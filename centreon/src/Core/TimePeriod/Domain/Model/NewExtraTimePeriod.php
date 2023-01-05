<?php

/*
 * Copyright 2005 - 2022 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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

class NewExtraTimePeriod
{
    public function __construct(private string $dayRange, private TimeRange $timeRange)
    {
    }

    /**
     * @param string $dayRange
     */
    public function setDayRange(string $dayRange): void
    {
        $this->dayRange = $dayRange;
    }

    /**
     * @return string
     */
    public function getDayRange(): string
    {
        return $this->dayRange;
    }

    /**
     * @param TimeRange $timeRange
     */
    public function setTimeRange(TimeRange $timeRange): void
    {
        $this->timeRange = $timeRange;
    }

    /**
     * @return TimeRange
     */
    public function getTimeRange(): TimeRange
    {
        return $this->timeRange;
    }
}
