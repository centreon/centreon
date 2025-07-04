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

namespace Tests\Core\TimePeriod\Application\UseCase;

use Core\TimePeriod\Domain\Model\Day;
use Core\TimePeriod\Domain\Model\ExtraTimePeriod;
use Core\TimePeriod\Domain\Model\Template;
use Core\TimePeriod\Domain\Model\TimePeriod;

class ExtractResponse
{
    /**
     * @param TimePeriod $timePeriod
     * @return array
     */
    public static function daysToArray(TimePeriod $timePeriod): array
    {
        return array_map(fn (Day $day) => [
            'day' => $day->getDay(),
            'time_range' => (string) $day->getTimeRange(),
        ], $timePeriod->getDays());
    }

    /**
     * @param TimePeriod $timePeriod
     * @return array
     */
    public static function templatesToArray(TimePeriod $timePeriod): array
    {
        return array_map(fn (Template $template) => [
            'id' => $template->getId(),
            'alias' => $template->getAlias(),
        ], $timePeriod->getTemplates());
    }

    /**
     * @param TimePeriod $timePeriod
     * @return array
     */
    public static function exceptionsToArray(TimePeriod $timePeriod): array
    {
        return array_map(fn (ExtraTimePeriod $exception) => [
            'id' => $exception->getId(),
            'day_range' => $exception->getDayRange(),
            'time_range' => (string) $exception->getTimeRange(),
        ], $timePeriod->getExtraTimePeriods());
    }
}
