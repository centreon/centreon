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

use Core\Broker\Domain\Model\BrokerInputOutput;
use Core\Broker\Domain\Model\BrokerInputOutputField;
use Core\Broker\Domain\Model\NewBrokerInputOutput;

interface WriteBrokerInputOutputRepositoryInterface
{
    /**
     * Add an input or output to a broker configuration.
     *
     * @param NewBrokerInputOutput $inputOutput
     * @param int $brokerId
     * @param array<string,BrokerInputOutputField|array<string,BrokerInputOutputField>> $parameters
     *
     * @throws \Throwable
     *
     * @return int
     */
    public function add(NewBrokerInputOutput $inputOutput, int $brokerId, array $parameters): int;

    /**
     * Delete a broker input or output configuration.
     *
     * @param int $brokerId
     * @param string $tag
     * @param int $inputOutputId
     *
     * @throws \Throwable
     */
    public function delete(int $brokerId, string $tag, int $inputOutputId): void;

    /**
     * Update a broker input or output configuration.
     *
     * @param BrokerInputOutput $inputOutput
     * @param int $brokerId
     * @param array<string,BrokerInputOutputField|array<string,BrokerInputOutputField>> $fields
     *
     * @throws \Throwable
     */
    public function update(BrokerInputOutput $inputOutput, int $brokerId, array $fields): void;
}
