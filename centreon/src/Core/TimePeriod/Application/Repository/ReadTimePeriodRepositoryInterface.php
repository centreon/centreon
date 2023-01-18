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

namespace Core\TimePeriod\Application\Repository;

use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\TimePeriod\Domain\Model\TimePeriod;

interface ReadTimePeriodRepositoryInterface
{
    /**
     * Find All time period.
     *
     * @param RequestParametersInterface $requestParameters
     *
     * @throws \Throwable
     *
     * @return TimePeriod[]
     */
    public function findByRequestParameter(RequestParametersInterface $requestParameters): array;

    /**
     * @param int $timePeriodId
     *
     * @throws \Throwable
     *
     * @return TimePeriod|null
     */
    public function findById(int $timePeriodId): ?TimePeriod;

    /**
     * @param int $timePeriodId
     *
     * @throws \Throwable
     *
     * @return bool
     */
    public function exists(int $timePeriodId): bool;

    /**
     * @param string $timePeriodName
     * @param int|null $timePeriodId
     *
     * @return bool
     */
    public function nameAlreadyExists(string $timePeriodName, ?int $timePeriodId = null): bool;
}
