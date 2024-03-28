<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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

namespace Core\Metric\Infrastructure\Repository;

use Core\Metric\Domain\Model\Metric;

class DbMetricFactory
{
    /**
     * @param array{
     *    id: int,
     *    name: string,
     *    unit_name: string,
     *    current_value: float|null,
     *    warn: float|null,
     *    warn_low: float|null,
     *    crit: float|null,
     *    crit_low: float|null
     *  } $record
     *
     * @return Metric
     */
    public static function createFromRecord(array $record): Metric
    {
        return (new Metric($record['id'], $record['name']))
            ->setUnit($record['unit_name'])
            ->setCurrentValue($record['current_value'])
            ->setWarningHighThreshold($record['warn'])
            ->setWarningLowThreshold($record['warn_low'])
            ->setCriticalHighThreshold($record['crit'])
            ->setCriticalLowThreshold($record['crit_low']);
    }
}
