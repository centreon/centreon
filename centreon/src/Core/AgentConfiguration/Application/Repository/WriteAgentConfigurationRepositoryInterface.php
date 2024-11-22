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

namespace Core\AgentConfiguration\Application\Repository;

use Core\AgentConfiguration\Domain\Model\AgentConfiguration;
use Core\AgentConfiguration\Domain\Model\NewAgentConfiguration;

interface WriteAgentConfigurationRepositoryInterface
{
    /**
     * Create a new agent configuration (AC).
     *
     * @param NewAgentConfiguration $agentConfiguration
     *
     * @throws \Throwable
     *
     * @return int
     */
    public function add(NewAgentConfiguration $agentConfiguration): int;

    /**
     * Update an agent configuration (AC).
     *
     * @param AgentConfiguration $agentConfiguration
     *
     * @throws \Throwable
     */
    public function update(AgentConfiguration $agentConfiguration): void;

    /**
     * Delete an agent configuration.
     *
     * @param int $id
     *
     * @throws \Throwable
     */
    public function delete(int $id): void;

    /**
     * Link listed poller to the agent configuration (AC).
     *
     * @param int $agentConfigurationId
     * @param int[] $pollerIds
     *
     * @throws \Throwable
     */
    public function linkToPollers(int $agentConfigurationId, array $pollerIds): void;

    /**
     * Unlink all pollers from the agent configuration (AC).
     *
     * @param int $agentConfigurationId
     *
     * @throws \Throwable
     */
    public function removePollers(int $agentConfigurationId): void;

    /**
     * Unlink a specific poller from the agent configuration (AC).
     *
     * @param int $agentConfigurationId
     * @param int $pollerId
     *
     * @throws \Throwable
     */
    public function removePoller(int $agentConfigurationId, int $pollerId): void;

    /**
     * Add the broker directive to pollers engine configurations.
     *
     * @param string $module
     * @param int[] $pollerIds
     *
     * @throws \Throwable
     */
    public function addBrokerDirective(string $module, array $pollerIds): void;
}
