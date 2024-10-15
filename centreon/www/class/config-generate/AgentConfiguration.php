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
                    $agentConfiguration->getConfiguration()->getData()
                ),
                Type::CMA => $configuration = $this->formatCmaConfiguration(
                    $agentConfiguration->getConfiguration()->getData()
                ),
                default => throw new \Exception('This error should never happen')
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
    private function formatOtelConfiguration(array $data): array
    {
        return [
            'host' => ModelAgentConfiguration::DEFAULT_HOST,
            'port' => ModelAgentConfiguration::DEFAULT_PORT,
            'encryption' => true,
            'public_cert' => '/etc/pki/' . $data['otel_public_certificate'] . '.crt',
            'private_key' => '/etc/pki/' . $data['otel_private_key'] . '.key',
            'ca_certificate' => $data['otel_ca_certificate'] !== null
                ? '/etc/pki/' . $data['otel_ca_certificate'] . '.crt'
                : null,
        ];
    }

    /**
     * Format the configuration for Centreon Monitoring Agent.
     *
     * @param _CmaParameters $data
     *
     * @return array
     */
    private function formatCmaConfiguration(array $data): array
    {
        $configuration = [
            'otel_server' => $this->formatOtelConfiguration($data),
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
                    'encryption' => true,
                    'ca_certificate' => $host['poller_ca_certificate'] !== null
                        ? '/etc/pki/' . $host['poller_ca_certificate'] . '.crt'
                        : null,
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
    private function formatTelegraphConfiguration(array $data): array
    {
        return [
            'otel_server' => $this->formatOtelConfiguration($data),
            'telegraf_conf_server' => [
                'http_server' => [
                    'port' => $data['conf_server_port'],
                    'encryption' => true,
                    'public_cert' => '/etc/pki/' . $data['conf_certificate'] .'.crt',
                    'private_key' => '/etc/pki/' . $data['conf_private_key'] .'.key',
                ]
            ]
        ];
    }
}