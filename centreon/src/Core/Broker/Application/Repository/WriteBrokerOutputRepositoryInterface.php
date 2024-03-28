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

use Core\Broker\Domain\Model\Broker;
use Core\Broker\Domain\Model\BrokerOutput;
use Core\Broker\Domain\Model\BrokerOutputField;
use Core\Broker\Domain\Model\NewBrokerOutput;

interface WriteBrokerOutputRepositoryInterface
{
    /**
     * Add an output to a broker configuration.
     *
     * @param NewBrokerOutput $output
     * @param int $brokerId
     * @param array<string,BrokerOutputField|array<string,BrokerOutputField>> $parameters
     *
     * @throws \Throwable
     *
     * @return int
     */
    public function add(NewBrokerOutput $output, int $brokerId, array $parameters): int;

    /**
     * Delete a broker output configuration.
     *
     * @param int $brokerId
     * @param int $outputId
     *
     * @throws \Throwable
     */
    public function delete(int $brokerId, int $outputId): void;

    /**
     * Update a broker output configuration.
     *
     * @param BrokerOutput $output
     * @param int $brokerId
     * @param array<string,BrokerOutputField|array<string,BrokerOutputField>> $outputFields
     *
     * @throws \Throwable
     */
    public function update(BrokerOutput $output, int $brokerId, array $outputFields): void;
}
