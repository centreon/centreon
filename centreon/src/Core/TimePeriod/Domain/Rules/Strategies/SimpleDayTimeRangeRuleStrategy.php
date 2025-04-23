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

namespace Core\TimePeriod\Domain\Rules\Strategies;

use Core\TimePeriod\Domain\Rules\TimePeriodRuleStrategyInterface;
use DateTimeInterface;

class SimpleDayTimeRangeRuleStrategy implements TimePeriodRuleStrategyInterface
{
    /**
     * @param DateTimeInterface $dateTime
     * @param int $dayRule
     * @param array<array{start: string, end: string}> $ranges
     *
     * @return bool
     */
    public function isIncluded(DateTimeInterface $dateTime, int $dayRule, array $ranges): bool
    {
        $day = (int) $dateTime->format('N');
        if ($day !== $dayRule) {
            return false;
        }

        $time = $dateTime->format('H:i');
        foreach ($ranges as $range) {
            if ($time >= $range['start'] && $time <= $range['end']) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param mixed $data
     *
     * @return bool
     */
    public function supports(mixed $data): bool
    {
        return is_array($data);
    }
}
