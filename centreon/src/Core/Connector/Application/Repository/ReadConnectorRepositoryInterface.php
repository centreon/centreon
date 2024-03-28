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

namespace Core\Connector\Application\Repository;

use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Command\Domain\Model\CommandType;
use Core\Connector\Domain\Model\Connector;

interface ReadConnectorRepositoryInterface
{
    /**
     * Determine if a connector exists by its ID.
     *
     * @param int $id
     *
     * @throws \Throwable
     *
     * @return bool
     */
    public function exists(int $id): bool;

    /**
     * Search for all connectors based on request parameters and filter their commands on command types.
     *
     * @param RequestParametersInterface $requestParameters
     * @param CommandType[] $commandTypes
     *
     * @throws \Throwable
     *
     * @return Connector[]
     */
    public function findByRequestParametersAndCommandTypes(
        RequestParametersInterface $requestParameters,
        array $commandTypes
    ): array;
}
