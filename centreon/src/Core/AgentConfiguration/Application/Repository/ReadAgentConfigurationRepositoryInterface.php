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

use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\AgentConfiguration\Domain\Model\AgentConfiguration;
use Core\AgentConfiguration\Domain\Model\Poller;
use Core\AgentConfiguration\Domain\Model\Type;
use Core\Common\Domain\TrimmedString;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

interface ReadAgentConfigurationRepositoryInterface
{
    /**
     * Determine if an Agent Configuration (AC) exists by its name.
     *
     * @param TrimmedString $name
     *
     * @throws \Throwable
     *
     * @return bool
     */
    public function existsByName(TrimmedString $name): bool;

    /**
     * Find an Agent Configuration (AC).
     *
     * @param int $agentConfigurationId
     *
     * @throws \Throwable
     *
     * @return ?AgentConfiguration
     */
    public function find(int $agentConfigurationId): ?AgentConfiguration;

    /**
     * Find all the pollers associated with any AC of the specified type.
     *
     * @param Type $type
     *
     * @throws \Throwable
     *
     * @return Poller[]
     */
    public function findPollersByType(Type $type): array;

    // /**
    //  * Find pollers NOT associated with any AC of the specified type.
    //  *
    //  * @param Type $type
    //  * @param null|RequestParametersInterface $requestParameters
    //  *
    //  * @throws \Throwable
    //  *
    //  * @return Poller[]
    //  */
    // public function findAvailablePollersByType(
    //     Type $type,
    //     ?RequestParametersInterface $requestParameters = null
    // ): array;

    // /**
    //  * Find pollers NOT associated with any AC of the specified type (with ACL).
    //  *
    //  * @param Type $type
    //  * @param AccessGroup[] $agentConfigurationcessGroups
    //  * @param null|RequestParametersInterface $requestParameters
    //  *
    //  * @throws \Throwable
    //  *
    //  * @return Poller[]
    //  */
    // public function findAvailablePollersByTypeAndAccessGroup(
    //     Type $type,
    //     array $agentConfigurationcessGroups,
    //     ?RequestParametersInterface $requestParameters = null
    // ): array;

    /**
     * Find all the pollers associated with an AC ID.
     *
     * @param int $agentConfigurationId
     *
     * @throws \Throwable
     *
     * @return Poller[]
     */
    public function findPollersByAcId(int $agentConfigurationId): array;

    /**
     * Return poller IDs that have the specified module directive defined in their engine configuration.
     *
     * @param string $module
     *
     * @throws \Throwable
     *
     * @return int[]
     */
    public function findPollersWithBrokerModuleDirective(string $module): array;

    /**
     * Return all the agent configurations.
     *
     * @throws \Throwable
     *
     * @return AgentConfiguration[]
     */
    public function findAllByRequestParameters(RequestParametersInterface $requestParameters): array;

    /**
     * Return all the agent configurations based on request parameters and ACL.
     *
     * @param RequestParametersInterface $requestParameters
     * @param AccessGroup[] $accessGroups
     *
     * @throws \Throwable
     *
     * @return AgentConfiguration[]
     */
    public function findAllByRequestParametersAndAccessGroups(
        RequestParametersInterface $requestParameters,
        array $accessGroups
    ): array;
}
