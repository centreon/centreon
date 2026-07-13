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

namespace App\Monitoring\Infrastructure\Dbal\TimePeriod;

use App\Monitoring\Domain\Aggregate\TimePeriod\TimePeriod;
use App\Monitoring\Domain\Aggregate\TimePeriod\TimePeriodId;
use App\Monitoring\Domain\Aggregate\TimePeriod\TimePeriodName;
use App\Shared\Infrastructure\TransformerInterface;

/**
 * @phpstan-import-type RowTypeAlias from DbalTimePeriodRepository
 * @implements TransformerInterface<RowTypeAlias, TimePeriod>
 */
final readonly class DbalTimePeriodTransformer implements TransformerInterface
{
    /**
     * @param RowTypeAlias $from $from
     */
    public function transform(mixed $from): TimePeriod
    {
        return new TimePeriod(
            new TimePeriodId($from['timeperiod_id']),
            new TimePeriodName($from['timeperiod_name']),
        );
    }
}
