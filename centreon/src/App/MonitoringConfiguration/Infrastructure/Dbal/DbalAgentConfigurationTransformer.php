<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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

namespace App\MonitoringConfiguration\Infrastructure\Dbal;

use App\MonitoringConfiguration\Domain\Aggregate\AgentConfiguration\AgentConfiguration;
use App\MonitoringConfiguration\Domain\Aggregate\AgentConfiguration\AgentConfigurationId;
use App\MonitoringConfiguration\Domain\Aggregate\AgentConfiguration\AgentConfigurationName;
use App\MonitoringConfiguration\Domain\Aggregate\AgentConfiguration\AgentConfigurationTypeEnum;
use App\MonitoringConfiguration\Domain\Aggregate\AgentConfiguration\CmaConfigurationParameters;
use App\MonitoringConfiguration\Domain\Aggregate\AgentConfiguration\ConnectionModeEnum;
use App\MonitoringConfiguration\Domain\Aggregate\AgentConfiguration\TelegrafConfigurationParameters;
use App\Shared\Infrastructure\TransformerInterface;

/**
 * @phpstan-import-type RowTypeAlias from DbalAgentConfigurationRepository
 *
 * @implements TransformerInterface<RowTypeAlias, AgentConfiguration>
 */
final readonly class DbalAgentConfigurationTransformer implements TransformerInterface
{
    /**
     * @param RowTypeAlias $data
     */
    public function transform(mixed $data): AgentConfiguration
    {
        /** @var array<string,mixed> $configuration */
        $configuration = json_decode(
            json: $data['ac_configuration'],
            associative: true,
            flags: JSON_THROW_ON_ERROR,
        );

        $type = AgentConfigurationTypeEnum::from($data['ac_type']);
        $connectionMode = ConnectionModeEnum::from($data['ac_connection_mode']);

        return new AgentConfiguration(
            id: new AgentConfigurationId($data['ac_id']),
            name: new AgentConfigurationName($data['ac_name']),
            type: $type,
            connectionMode: $connectionMode,
            configuration: match ($type) {
                AgentConfigurationTypeEnum::TELEGRAF => new TelegrafConfigurationParameters($configuration),
                AgentConfigurationTypeEnum::CMA => new CmaConfigurationParameters($configuration, true),
            },
        );
    }
}
