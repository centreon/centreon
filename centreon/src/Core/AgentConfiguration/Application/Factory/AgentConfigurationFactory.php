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
use Core\AgentConfiguration\Domain\Model\AgentConfiguration;
use Core\AgentConfiguration\Domain\Model\ConfigurationParameters\TelegrafConfigurationParameters;
use Core\AgentConfiguration\Domain\Model\NewAgentConfiguration;
use Core\AgentConfiguration\Domain\Model\Type;
/**
 * @phpstan-import-type _TelegrafParameters from TelegrafConfigurationParameters
 */
class AgentConfigurationFactory
{
    /**
     * @param string $name
     * @param Type $type
     * @param array<string,mixed> $parameters
     *
     * @return NewAgentConfiguration
     */
    public function createNewAgentConfiguration(
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
                default => throw new \Exception('This error should never happen')
            }
        );
    }

    /**
     * @param int $id
     * @param string $name
     * @param Type $type
     * @param array<string,mixed> $parameters
     *
     * @return AgentConfiguration
     */
    public function createAgentConfiguration(
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
            configuration: match ($type->value) {
                Type::TELEGRAF->value => new TelegrafConfigurationParameters($parameters),
                default => throw new \Exception('This error should never happen')
            }
        );
    }

    // /**
    //  * @param Acc $agentConfigurationc
    //  * @param string $name
    //  * @param int $updatedBy
    //  * @param array<string,mixed> $parameters
    //  * @param null|string $description
    //  *
    //  * @return Acc
    //  */
    // public function updateAcc(
    //     Acc $agentConfigurationc,
    //     string $name,
    //     int $updatedBy,
    //     array $parameters,
    //     ?string $description = null,
    // ): Acc
    // {
    //     return new Acc(
    //         id: $agentConfigurationc->getId(),
    //         name: $name,
    //         type: $agentConfigurationc->getType(),
    //         createdBy: $agentConfigurationc->getCreatedBy(),
    //         updatedBy: $updatedBy,
    //         createdAt: $agentConfigurationc->getCreatedAt(),
    //         updatedAt: new \DateTimeImmutable(),
    //         description: $description,
    //         parameters: match ($agentConfigurationc->getType()) {
    //             Type::VMWARE_V6 => VmWareV6Parameters::update($this->encryption, $agentConfigurationc->getParameters(), $parameters),
    //         }
    //     );
    // }
}
