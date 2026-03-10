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

namespace App\MonitoringConfiguration\Domain\Aggregate\AgentConfiguration;

use Webmozart\Assert\Assert;

class CmaConfigurationParameters extends AbstractConfigurationParameters
{
    public const BROKER_MODULE_DIRECTIVE = '/usr/lib64/centreon-engine/libopentelemetry.so /etc/centreon-engine/otl_server.json';
    public const DEFAULT_CHECK_INTERVAL = 60;
    public const DEFAULT_EXPORT_PERIOD = 60;

    public function __construct(array $parameters, private readonly bool $fromReadRepository = false)
    {
        parent::__construct($parameters);

        /** @var bool $agentInitiated */
        $agentInitiated = $this->parameters['agent_initiated'];
        if ($agentInitiated === false) {
            $this->parameters['otel_public_certificate'] = null;
            $this->parameters['otel_private_key'] = null;
            $this->parameters['otel_ca_certificate'] = null;
            $this->parameters['tokens'] = [];
        } else {
            $this->parameters['otel_public_certificate'] = $this->validateCertificatePath(
                is_string($this->parameters['otel_public_certificate']) ? $this->parameters['otel_public_certificate'] : null,
                'configuration.otel_public_certificate'
            );
            $this->parameters['otel_private_key'] = $this->validateCertificatePath(
                is_string($this->parameters['otel_private_key']) ? $this->parameters['otel_private_key'] : null,
                'configuration.otel_private_key'
            );
            $this->parameters['otel_ca_certificate'] = $this->validateCertificatePath(
                is_string($this->parameters['otel_ca_certificate']) ? $this->parameters['otel_ca_certificate'] : null,
                'configuration.otel_ca_certificate'
            );

            if (! $this->fromReadRepository) {
                /** @var array<mixed> $tokens */
                $tokens = $this->parameters['tokens'];
                Assert::notEmpty($tokens, '[configuration.tokens] Tokens cannot be empty.');
                foreach ($tokens as $token) {
                    /** @var array<string, mixed> $token */
                    Assert::stringNotEmpty(
                        is_string($token['name']) ? $token['name'] : '',
                        '[configuration.tokens[].name] Token name cannot be empty.'
                    );
                }
            }
            if (! isset($this->parameters['port'])) {
                $this->parameters['port'] = AgentConfiguration::DEFAULT_PORT;
            }
            $portValue = $this->parameters['port'] ?? null;
            $port = is_int($portValue) ? $portValue : AgentConfiguration::DEFAULT_PORT;
            Assert::range($port, 0, 65535, '[configuration.port] Port must be between 0 and 65535. Got: %s');
        }

        /** @var bool $pollerInitiated */
        $pollerInitiated = $this->parameters['poller_initiated'];
        if ($pollerInitiated === false) {
            $this->parameters['hosts'] = [];
        } else {
            /** @var array<int|string, array{id: mixed, address: mixed, port: mixed, poller_ca_certificate: mixed, token: mixed}> $hosts */
            $hosts = $this->parameters['hosts'];
            foreach ($hosts as $key => $host) {
                Assert::positiveInteger($host['id'], '[configuration.hosts[].id] Host id must be a positive integer. Got: %s');
                /** @var string $hostAddress */
                $hostAddress = $host['address'];
                Assert::true(
                    filter_var($hostAddress, FILTER_VALIDATE_IP) !== false
                    || filter_var($hostAddress, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false,
                    sprintf('[configuration.hosts[].address] "%s" is not a valid IP or domain.', $hostAddress)
                );
                Assert::range($host['port'], 0, 65535, '[configuration.hosts[].port] Port must be between 0 and 65535. Got: %s');
                $hosts[$key]['poller_ca_certificate'] = $this->validateCertificatePath(
                    is_string($host['poller_ca_certificate']) ? $host['poller_ca_certificate'] : null,
                    'configuration.hosts[].poller_ca_certificate'
                );

                if (! $this->fromReadRepository) {
                    Assert::notNull($host['token'], '[configuration.hosts[].token] Token cannot be null.');
                    /** @var array<string, mixed> $hostToken */
                    $hostToken = $host['token'];
                    $tokenName = is_string($hostToken['name']) ? $hostToken['name'] : '';
                    Assert::stringNotEmpty(
                        $tokenName,
                        '[configuration.hosts[].token.name] Token name cannot be empty.'
                    );
                    $tokenCreatorId = is_int($hostToken['creator_id']) ? $hostToken['creator_id'] : 0;
                    Assert::positiveInteger(
                        $tokenCreatorId,
                        '[configuration.hosts[].token.creator_id] Creator id must be a positive integer. Got: %s'
                    );
                }
            }
            $this->parameters['hosts'] = $hosts;
        }
    }

    public function getData(): array
    {
        return $this->parameters;
    }

    public function getBrokerDirective(): ?string
    {
        return self::BROKER_MODULE_DIRECTIVE;
    }
}
