<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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

use Core\AgentConfiguration\Application\Repository\ReadAgentConfigurationRepositoryInterface;
use Core\AgentConfiguration\Domain\Model\AgentConfiguration as ModelAgentConfiguration;
use Core\AgentConfiguration\Domain\Model\ConfigurationParameters\CmaConfigurationParameters;
use Core\AgentConfiguration\Domain\Model\ConfigurationParameters\TelegrafConfigurationParameters;
use Core\AgentConfiguration\Domain\Model\ConnectionModeEnum;
use Core\AgentConfiguration\Domain\Model\Type;

/**
 * @phpstan-import-type _TelegrafParameters from TelegrafConfigurationParameters
 * @phpstan-import-type _CmaParameters from CmaConfigurationParameters
 */
class AgentConfiguration extends AbstractObjectJSON
{
    public function __construct(
        private readonly Backend $backend,
        private readonly ReadAgentConfigurationRepositoryInterface $readAgentConfigurationRepository,
    ) {
        $this->generate_filename = 'otl_server.json';
    }

    public function generateFromPollerId(int $pollerId): void
    {
        $this->generate($pollerId);
    }

    private function generate(int $pollerId): void
    {
        $agentConfiguration = $this->readAgentConfigurationRepository->findByPollerId($pollerId);
        $configuration = [];
        if ($agentConfiguration !== null) {
            match ($agentConfiguration->getType()) {
                Type::TELEGRAF => $configuration = $this->formatTelegraphConfiguration(
                    $agentConfiguration->getConfiguration()->getData(),
                    $agentConfiguration->getConnectionMode()
                ),
                Type::CMA => $configuration = $this->formatCmaConfiguration(
                    $agentConfiguration->getConfiguration()->getData(),
                    $agentConfiguration->getConnectionMode()
                ),
                default => throw new \Exception('The type of the agent configuration not exists')
            };
        }

        $this->generateFile($configuration, false);
        $this->writeFile($this->backend->getPath());
    }

    /**
     * Format the configuration for OpenTelemetry.
     *
     * The configuration is based on the data from the AgentConfiguration table.
     * It returns an array with the configuration for the OpenTelemetry HTTP server.
     *
     * @param _TelegrafParameters|_CmaParameters $data The data from the AgentConfiguration table.
     * @return array<string, array<string, string>> The configuration for the OpenTelemetry HTTP server.
     */
    private function formatOtelConfiguration(array $data, ConnectionModeEnum $connectionMode): array
    {
        return [
            'host' => ModelAgentConfiguration::DEFAULT_HOST,
            'port' => ModelAgentConfiguration::DEFAULT_PORT,
            'encryption' => ConnectionModeEnum::SECURE === $connectionMode,
            'public_cert' => ! empty($data['otel_public_certificate'])
                ? $data['otel_public_certificate']
                : '',
            'private_key' => ! empty($data['otel_private_key'])
                ? $data['otel_private_key']
                : '',
            'ca_certificate' => ! empty($data['otel_ca_certificate'])
                ? $data['otel_ca_certificate']
                : '',
        ];
    }

    /**
     * Format the configuration for Centreon Monitoring Agent.
     *
     * @param _CmaParameters $data
     *
     * @return array
     */
    private function formatCmaConfiguration(array $data, ConnectionModeEnum $connectionMode): array
    {
        $configuration = [
            'otel_server' => $this->formatOtelConfiguration($data, $connectionMode),
            'centreon_agent' => [
                'check_interval' => CmaConfigurationParameters::DEFAULT_CHECK_INTERVAL,
                'export_period' => CmaConfigurationParameters::DEFAULT_EXPORT_PERIOD,
            ]
        ];

        if ($data['is_reverse']) {
            $configuration['centreon_agent']['reverse_connections'] = array_map(
                static fn(array $host): array => [
                    'host' => $host['address'],
                    'port' => $host['port'],
                    'encryption' => $configuration['otel_server']['encryption'],
                    'ca_certificate' => $host['poller_ca_certificate'] !== null
                        ? $host['poller_ca_certificate']
                        : '',
                    'ca_name' => $host['poller_ca_name'],
                ],
                $data['hosts']
            );
        }

        return $configuration;
    }

    /**
     * Format the configuration for Telegraf.
     *
     * The configuration is based on the data from the AgentConfiguration table.
     * It returns an array with the configuration for the Telegraf HTTP server.
     *
     * @param _TelegrafParameters $data The data from the AgentConfiguration table.
     * @return array<string, array<string, mixed>> The configuration for the Telegraf HTTP server.
     */
    private function formatTelegraphConfiguration(array $data, ConnectionModeEnum $connectionMode): array
    {
        $otelConfiguration = $this->formatOtelConfiguration($data, $connectionMode);

        return [
            'otel_server' => $otelConfiguration,
            'telegraf_conf_server' => [
                'http_server' => [
                    'port' => $data['conf_server_port'],
                    'encryption' => $otelConfiguration['encryption'],
                    'public_cert' => $data['conf_certificate'] !== null
                        ? $data['conf_certificate']
                        : '',
                    'private_key' => $data['conf_private_key'] !== null
                        ? $data['conf_private_key']
                        : '',
                ]
            ]
        ];
    }
}
