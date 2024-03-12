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

namespace Core\Broker\Application\Repository;

use Core\Broker\Domain\Model\BrokerOutput;
use Core\Broker\Domain\Model\BrokerOutputField;
use Core\Broker\Domain\Model\Type;

interface ReadBrokerOutputRepositoryInterface
{
    /**
     * Find parameters of an output by type.
     * Result key is the parameter fieldname, in case of grouped fields the groupname is used as a key.
     *
     * @param int $typeId
     *
     * @throws \Throwable
     *
     * @return array<string,BrokerOutputField|array<string,BrokerOutputField>>
     */
    public function findParametersByType(int $typeId): array;

    /**
     * Find an output stream type by its ID.
     *
     * @param int $typeId
     *
     * @throws \Throwable
     *
     * @return ?Type
     */
    public function findType(int $typeId): ?Type;

    /**
     * Find a broker output configuration by its ID and its broker ID.
     *
     * @param int $outputId
     * @param int $brokerId
     *
     * @throws \Throwable
     *
     * @return null|BrokerOutput
     */
    public function findByIdAndBrokerId(int $outputId, int $brokerId): ?BrokerOutput;
}
