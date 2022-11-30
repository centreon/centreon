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

namespace Core\TimePeriod\Application\UseCase\AddTimePeriod;

use Core\TimePeriod\Domain\Model\Day;
use Core\TimePeriod\Domain\Model\NewTimePeriodException;
use Core\TimePeriod\Domain\Model\NewTimePeriod;
use Core\TimePeriod\Domain\Model\Template;
use Core\TimePeriod\Domain\Model\TimeRange;

final class NewTimePeriodFactory
{
    /**
     * @param AddTimePeriodRequest $dto
     * @return NewTimePeriod
     * @throws \Assert\AssertionFailedException
     * @throws \Throwable
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
        $newTimePeriod->setTemplates(
            array_map(function (int $templateId): Template {
                return new Template($templateId, '');
            }, $dto->templates)
        );
        $newTimePeriod->setExceptions(
            array_map(function (array $exception): NewTimePeriodException {
                return new NewTimePeriodException(
                    $exception['day_range'],
                    $exception['time_range']
                );
            }, $dto->exceptions)
        );
        return $newTimePeriod;
    }
}
