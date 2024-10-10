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

namespace Core\AgentConfiguration\Application\Factory;

use Assert\AssertionFailedException;
use Core\AgentConfiguration\Domain\Model\AgentConfiguration;
use Core\AgentConfiguration\Domain\Model\ConfigurationParameters\CmaConfigurationParameters;
use Core\AgentConfiguration\Domain\Model\ConfigurationParameters\TelegrafConfigurationParameters;
use Core\AgentConfiguration\Domain\Model\NewAgentConfiguration;
use Core\AgentConfiguration\Domain\Model\Type;

class AgentConfigurationFactory
{
    /**
     * @param string $name
     * @param Type $type
     * @param array<string,mixed> $parameters
     *
     * @throws AssertionFailedException
     *
     * @return NewAgentConfiguration
     */
    public static function createNewAgentConfiguration(
        string $name,
        Type $type,
        array $parameters,
    ): NewAgentConfiguration
    {
        return new NewAgentConfiguration(
            name: $name,
            type: $type,
            configuration: match ($type) {
                Type::TELEGRAF => new TelegrafConfigurationParameters($parameters),
                Type::CMA => new CmaConfigurationParameters($parameters)
            }
        );
    }

    /**
     * @param int $id
     * @param string $name
     * @param Type $type
     * @param array<string,mixed> $parameters
     *
     * @throws AssertionFailedException
     *
     * @return AgentConfiguration
     */
    public static function createAgentConfiguration(
        int $id,
        string $name,
        Type $type,
        array $parameters,
    ): AgentConfiguration
    {
        return new AgentConfiguration(
            id: $id,
            name: $name,
            type: $type,
            configuration: match ($type) {
                Type::TELEGRAF => new TelegrafConfigurationParameters($parameters),
                Type::CMA => new CmaConfigurationParameters($parameters)
            }
        );
    }
}
