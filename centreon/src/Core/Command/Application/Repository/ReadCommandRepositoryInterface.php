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

namespace Core\Command\Application\Repository;

use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Command\Domain\Model\Command;
use Core\Command\Domain\Model\CommandType;

interface ReadCommandRepositoryInterface
{
    /**
     * Determine if a command exists by its ID.
     *
     * @param int $commandId
     *
     * @return bool
     */
    public function exists(int $commandId): bool;

    /**
     * Determine if a command exists by its ID and type.
     *
     * @param int $commandId
     * @param CommandType $commandType
     *
     * @return bool
     */
    public function existsByIdAndCommandType(int $commandId, CommandType $commandType): bool;

    /**
     * Search for all commands based on request parameters and command types.
     *
     * @param RequestParametersInterface $requestParameters
     * @param CommandType[] $commandTypes
     *
     * @return Command[]
     */
    public function findByRequestParameterAndTypes(
        RequestParametersInterface $requestParameters,
        array $commandTypes
    ): array;
}
