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

namespace Core\TimePeriod\Application\UseCase\AddTimePeriod;

use Core\TimePeriod\Domain\Model\{
    Day,
    NewExtraTimePeriod,
    NewTimePeriod,
    Template,
    TimeRange
};

final class NewTimePeriodFactory
{
    /**
     * @param AddTimePeriodRequest $dto
     *
     * @throws \Assert\AssertionFailedException
     * @throws \Throwable
     *
     * @return NewTimePeriod
     */
    public static function create(AddTimePeriodRequest $dto): NewTimePeriod
    {
        $newTimePeriod = new NewTimePeriod($dto->name, $dto->alias);
        $newTimePeriod->setDays(
            array_map(function (array $day): Day {
                return new Day(
                    $day['day'],
                    new TimeRange($day['time_range']),
                );
            }, $dto->days)
        );
        $newTimePeriod->setTemplates($dto->templates);
        $newTimePeriod->setExtraTimePeriods(
            array_map(function (array $exception): NewExtraTimePeriod {
                return new NewExtraTimePeriod(
                    $exception['day_range'],
                    new TimeRange($exception['time_range'])
                );
            }, $dto->exceptions)
        );

        return $newTimePeriod;
    }
}
