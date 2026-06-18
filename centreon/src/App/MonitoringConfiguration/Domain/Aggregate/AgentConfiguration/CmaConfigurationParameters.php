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

use App\Shared\Domain\Assert\Assert as CentreonAssert;
use Webmozart\Assert\Assert;

class CmaConfigurationParameters extends AbstractConfigurationParameters
{
    public const BROKER_MODULE_DIRECTIVE = '/usr/lib64/centreon-engine/libopentelemetry.so /etc/centreon-engine/otl_server.json';
    public const DEFAULT_CHECK_INTERVAL = 60;
    public const DEFAULT_EXPORT_PERIOD = 60;

    // Top-level parameter keys
    public const PARAM_AGENT_INITIATED = 'agent_initiated';
    public const PARAM_OTEL_PUBLIC_CERTIFICATE = 'otel_public_certificate';
    public const PARAM_OTEL_PRIVATE_KEY = 'otel_private_key';
    public const PARAM_OTEL_CA_CERTIFICATE = 'otel_ca_certificate';
    public const PARAM_TOKENS = 'tokens';
    public const PARAM_PORT = 'port';
    public const PARAM_POLLER_INITIATED = 'poller_initiated';
    public const PARAM_HOSTS = 'hosts';

    // Host sub-keys
    public const HOST_ID = 'id';
    public const HOST_ADDRESS = 'address';
    public const HOST_PORT = 'port';
    public const HOST_POLLER_CA_CERTIFICATE = 'poller_ca_certificate';
    public const HOST_TOKEN = 'token';

    // Token sub-keys
    public const TOKEN_NAME = 'name';
    public const TOKEN_CREATOR_ID = 'creator_id';

    public function __construct(array $parameters, private readonly bool $fromReadRepository = false)
    {
        parent::__construct($parameters);

        Assert::true(
            $this->parameters[self::PARAM_AGENT_INITIATED] === true
            || $this->parameters[self::PARAM_POLLER_INITIATED] === true,
            '[configuration] At least one connection mode must be enabled.'
        );

        $this->applyAgentInitiatedParameters();
        $this->applyPollerInitiatedParameters();
    }

    public function getData(): array
    {
        return $this->parameters;
    }

    public function getBrokerDirective(): ?string
    {
        return self::BROKER_MODULE_DIRECTIVE;
    }

    private function applyAgentInitiatedParameters(): void
    {
        if ($this->parameters[self::PARAM_AGENT_INITIATED] === false) {
            $this->parameters[self::PARAM_OTEL_PUBLIC_CERTIFICATE] = null;
            $this->parameters[self::PARAM_OTEL_PRIVATE_KEY] = null;
            $this->parameters[self::PARAM_OTEL_CA_CERTIFICATE] = null;
            $this->parameters[self::PARAM_TOKENS] = [];

            return;
        }

        $this->normalizeCertificateParam(self::PARAM_OTEL_PUBLIC_CERTIFICATE, 'configuration.otel_public_certificate');
        $this->normalizeCertificateParam(self::PARAM_OTEL_PRIVATE_KEY, 'configuration.otel_private_key');
        $this->normalizeCertificateParam(self::PARAM_OTEL_CA_CERTIFICATE, 'configuration.otel_ca_certificate');

        if (! $this->fromReadRepository) {
            $this->validateTokens();
        }

        $this->normalizePort();
    }

    private function normalizePort(): void
    {
        if (! isset($this->parameters[self::PARAM_PORT])) {
            $this->parameters[self::PARAM_PORT] = AgentConfiguration::DEFAULT_PORT;
        }
        $portValue = $this->parameters[self::PARAM_PORT] ?? null;
        $port = is_int($portValue) ? $portValue : AgentConfiguration::DEFAULT_PORT;
        Assert::range($port, 1, 65535, '[configuration.port] Port must be between 1 and 65535. Got: %s');
    }

    private function validateTokens(): void
    {
        /** @var array<mixed> $tokens */
        $tokens = $this->parameters[self::PARAM_TOKENS];
        Assert::notEmpty($tokens, sprintf('[configuration.%s] Tokens cannot be empty.', self::PARAM_TOKENS));
        foreach ($tokens as $token) {
            Assert::isArray($token, sprintf('[configuration.%s[]] Token entry must be an array.', self::PARAM_TOKENS));
            Assert::keyExists($token, self::TOKEN_NAME, sprintf('[configuration.%s[].%s] Missing token name.', self::PARAM_TOKENS, self::TOKEN_NAME));
            /** @var array<string, mixed> $token */
            Assert::stringNotEmpty(
                is_string($token[self::TOKEN_NAME]) ? $token[self::TOKEN_NAME] : '',
                sprintf('[configuration.%s[].%s] Token name cannot be empty.', self::PARAM_TOKENS, self::TOKEN_NAME)
            );
        }
    }

    private function applyPollerInitiatedParameters(): void
    {
        if ($this->parameters[self::PARAM_POLLER_INITIATED] === false) {
            $this->parameters[self::PARAM_HOSTS] = [];

            return;
        }

        /** @var array<int|string, array{id: mixed, address: mixed, port: mixed, poller_ca_certificate: mixed, token: mixed}> $hosts */
        $hosts = $this->parameters[self::PARAM_HOSTS];
        foreach ($hosts as $key => $host) {
            $hosts[$key] = $this->validateHost($host);
        }
        $this->parameters[self::PARAM_HOSTS] = $hosts;
    }

    /**
     * @param array{id: mixed, address: mixed, port: mixed, poller_ca_certificate: mixed, token: mixed} $host
     *
     * @return array{id: mixed, address: mixed, port: mixed, poller_ca_certificate: mixed, token: mixed}
     */
    private function validateHost(array $host): array
    {
        Assert::positiveInteger($host[self::HOST_ID], '[configuration.hosts[].id] Host id must be a positive integer. Got: %s');

        /** @var string $hostAddress */
        $hostAddress = $host[self::HOST_ADDRESS];
        CentreonAssert::ipOrHostname($hostAddress, 'configuration.hosts[].address');

        Assert::range($host[self::HOST_PORT], 1, 65535, '[configuration.hosts[].port] Port must be between 1 and 65535. Got: %s');

        $host[self::HOST_POLLER_CA_CERTIFICATE] = $this->validateCertificatePath(
            is_string($host[self::HOST_POLLER_CA_CERTIFICATE]) ? $host[self::HOST_POLLER_CA_CERTIFICATE] : null,
            'configuration.hosts[].poller_ca_certificate'
        );

        if (! $this->fromReadRepository) {
            Assert::notNull($host[self::HOST_TOKEN], '[configuration.hosts[].token] Token cannot be null.');
            /** @var array<string, mixed> $hostToken */
            $hostToken = $host[self::HOST_TOKEN];
            $this->validateHostToken($hostToken);
        }

        return $host;
    }

    /**
     * @param array<string, mixed> $hostToken
     */
    private function validateHostToken(array $hostToken): void
    {
        $tokenName = is_string($hostToken[self::TOKEN_NAME]) ? $hostToken[self::TOKEN_NAME] : '';
        Assert::stringNotEmpty($tokenName, '[configuration.hosts[].token.name] Token name cannot be empty.');

        $tokenCreatorId = is_int($hostToken[self::TOKEN_CREATOR_ID]) ? $hostToken[self::TOKEN_CREATOR_ID] : 0;
        Assert::positiveInteger(
            $tokenCreatorId,
            '[configuration.hosts[].token.creator_id] Creator id must be a positive integer. Got: %s'
        );
    }
}
