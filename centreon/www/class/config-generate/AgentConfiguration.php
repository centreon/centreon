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
use Core\Host\Application\Repository\ReadHostRepositoryInterface;
use Core\Security\Token\Application\Repository\ReadTokenRepositoryInterface;
use Core\Security\Token\Domain\Model\JwtToken;
use Core\Security\Token\Domain\Model\Token;

/**
 * @phpstan-import-type _TelegrafParameters from TelegrafConfigurationParameters
 * @phpstan-import-type _CmaParameters from CmaConfigurationParameters
 */
class AgentConfiguration extends AbstractObjectJSON
{
    public function __construct(
        private readonly Backend $backend,
        private readonly ReadAgentConfigurationRepositoryInterface $readAgentConfigurationRepository,
        private readonly ReadTokenRepositoryInterface $readTokenRepository,
        private readonly ReadHostRepositoryInterface $readHostRepository,
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
                default => throw new Exception('The type of the agent configuration not exists')
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
     * @param _TelegrafParameters|_CmaParameters $data the data from the AgentConfiguration table
     * @return array<string, array<string, string>> the configuration for the OpenTelemetry HTTP server
     */
    private function formatOtelConfiguration(array $data, ConnectionModeEnum $connectionMode): array
    {
        return [
            'host' => ModelAgentConfiguration::DEFAULT_HOST,
            'port' => ModelAgentConfiguration::DEFAULT_PORT,
            'encryption' => match ($connectionMode) {
                ConnectionModeEnum::SECURE => 'full',
                ConnectionModeEnum::INSECURE => 'insecure',
                ConnectionModeEnum::NO_TLS => 'no',
                default => 'full',
            },
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
        $tokens = $data['tokens'] !== []
            ? $this->readTokenRepository->findByNames(array_map(
                static fn (array $token): string => $token['name'],
                $data['tokens']
            ))
            : [];

        $tokens = array_filter(
            $tokens,
            static fn (Token $token): bool =>  ! (
                $token->isRevoked()
                || ($token->getExpirationDate() !== null && $token->getExpirationdate() < new DateTimeImmutable())
            )
        );
        $configuration = [
            'otel_server' => $this->formatOtelConfiguration($data, $connectionMode),
            'centreon_agent' => [
                'check_interval' => CmaConfigurationParameters::DEFAULT_CHECK_INTERVAL,
                'export_period' => CmaConfigurationParameters::DEFAULT_EXPORT_PERIOD,
            ],
            'tokens' => array_map(
                static fn (JwtToken $token): array => [
                    'token' => $token->getToken(),
                    'encoding_key' => $token->getEncodingKey(),
                ],
                $tokens
            ),
        ];

        if ($data['is_reverse']) {
            $hostIds = array_map(static fn (array $host): int => $host['id'], $data['hosts']);
            $hosts = $this->readHostRepository->findByIds($hostIds);

            $tokenNames = array_filter(
                array_map(
                    static fn (array $host): ?string => $host['token'] !== null ? $host['token']['name'] : null,
                    $data['hosts']
                )
            );
            $tokens = $tokenNames !== []
                ? $this->readTokenRepository->findByNames($tokenNames)
                : [];

            $tokens = array_filter(
                $tokens,
                static fn (Token $token): bool =>  ! (
                    $token->isRevoked()
                    || ($token->getExpirationDate() !== null && $token->getExpirationDate() < new DateTimeImmutable())
                )
            );

            $configuration['centreon_agent']['reverse_connections'] = array_map(
                static fn (array $host): array => [
                    'host' => $host['address'],
                    'port' => $host['port'],
                    'encryption' =>  match ($connectionMode) {
                        ConnectionModeEnum::SECURE => 'full',
                        ConnectionModeEnum::INSECURE => 'insecure',
                        ConnectionModeEnum::NO_TLS => 'no',
                        default => 'full',
                    },
                    'ca_certificate' => $host['poller_ca_certificate'] ?? '',
                    'ca_name' => $host['poller_ca_name'],
                    'token' => isset($tokens[$host['token']['name']])
                        ? [
                            'token' => $tokens[$host['token']['name']]->getToken(),
                            'encoding_key' => $tokens[$host['token']['name']]->getEncodingKey(),
                        ]
                        : null,
                ],
                array_filter(
                    $data['hosts'],
                    static fn (array $host): bool => $hosts[$host['id']] ? true : false
                )
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
     * @param _TelegrafParameters $data the data from the AgentConfiguration table
     * @return array<string, array<string, mixed>> the configuration for the Telegraf HTTP server
     */
    private function formatTelegraphConfiguration(array $data, ConnectionModeEnum $connectionMode): array
    {
        $otelConfiguration = $this->formatOtelConfiguration($data, $connectionMode);

        return [
            'otel_server' => $otelConfiguration,
            'telegraf_conf_server' => [
                'http_server' => [
                    'port' => $data['conf_server_port'],
                    'encryption' =>  match ($connectionMode) {
                        ConnectionModeEnum::SECURE => 'full',
                        ConnectionModeEnum::INSECURE => 'insecure',
                        ConnectionModeEnum::NO_TLS => 'no',
                        default => 'full',
                    },
                    'public_cert' => $data['conf_certificate'] ?? '',
                    'private_key' => $data['conf_private_key'] ?? '',
                ],
            ],
        ];
    }
}
