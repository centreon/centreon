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

namespace Core\AgentConfiguration\Domain\Model\ConfigurationParameters;

use Assert\AssertionFailedException;
use Centreon\Domain\Common\Assertion\Assertion;
use Core\AgentConfiguration\Domain\Model\ConfigurationParametersInterface;
use Core\AgentConfiguration\Domain\Model\ConnectionModeEnum;

/**
 * @phpstan-type _CmaParameters array{
 *	    is_reverse: bool,
 *		connection_mode?: ConnectionModeEnum,
 *		otel_public_certificate: string,
 *		otel_private_key: string,
 *		otel_ca_certificate: ?string,
 *		hosts: array<array{
 *			address: string,
 *			port: int,
 *			poller_ca_certificate: ?string,
 *			poller_ca_name: ?string,
 *		}>
 *  }
 */
class CmaConfigurationParameters implements ConfigurationParametersInterface
{
    public const BROKER_MODULE_DIRECTIVE = '/usr/lib64/centreon-engine/libopentelemetry.so /etc/centreon-engine/otl_server.json';
    public const MAX_LENGTH = 255;
    public const DEFAULT_CHECK_INTERVAL = 60;
    public const DEFAULT_EXPORT_PERIOD = 60;

    /** @var _CmaParameters */
    private array $parameters;

    /**
     * @param array<string,mixed> $parameters
     *
     * @throws AssertionFailedException
     */
    public function __construct(array $parameters)
    {
        if (isset($parameters['connection_mode'])) {
            $connectionMode = $parameters['connection_mode'];
        } else {
            $connectionMode = (empty($parameters['otel_public_certificate']) || empty($parameters['otel_private_key']))
                ? ConnectionModeEnum::NO_TLS
                : ConnectionModeEnum::SECURE;
        }

        /** @var _CmaParameters $parameters */
        if ($connectionMode === ConnectionModeEnum::SECURE) {
            $this->validateCertificate($parameters['otel_public_certificate'], 'configuration.otel_public_certificate');
            $this->validateCertificate($parameters['otel_private_key'], 'configuration.otel_private_key');
            if ($parameters['otel_ca_certificate'] !== null) {
                $this->validateCertificate($parameters['otel_ca_certificate'], 'configuration.otel_ca_certificate');
            }
        } else {
            $this->validateOptionalCertificate($parameters['otel_public_certificate'], 'configuration.otel_public_certificate');
            $this->validateOptionalCertificate($parameters['otel_private_key'], 'configuration.otel_private_key');
            $this->validateOptionalCertificate($parameters['otel_ca_certificate'], 'configuration.otel_ca_certificate');
        }

        if (! $parameters['is_reverse'] && ! empty($parameters['hosts'])) {
            $parameters['hosts'] = [];
        }

        foreach ($parameters['hosts'] as $host) {
            Assertion::ipOrDomain($host['address'], 'configuration.hosts[].address');
            Assertion::range($host['port'], 0, 65535, 'configuration.hosts[].port');
            $this->validateHostCertificate($host['poller_ca_certificate'], $connectionMode, 'configuration.hosts[].poller_ca_certificate');
            $this->validateOptionalCertificate($host['poller_ca_name'], 'configuration.hosts[].poller_ca_name');
        }

        unset($parameters['connection_mode']);

        $this->parameters = $parameters;
    }

    /**
     * @inheritDoc
     *
     * @return _CmaParameters
     */
    public function getData(): array
    {
        return $this->parameters;
    }

    /**
     * @inheritDoc
     */
    public function getBrokerDirective(): ?string
    {
        return self::BROKER_MODULE_DIRECTIVE;
    }

    /**
     * Validates a certificate.
     *
     * @param ?string $certificate
     * @param string $field Used for error reporting
     * 
     * @throws AssertionFailedException
     */
    private function validateCertificate(?string $certificate, string $field): void
    {
        Assertion::notNull($certificate, $field);
        Assertion::notEmptyString($certificate, $field);
        Assertion::maxLength((string) $certificate, self::MAX_LENGTH, $field);
    }

    /**
     * Validates an optional certificate.
     *
     * @param ?string $certificate
     * @param string $field Used for error reporting
     * 
     * @throws AssertionFailedException
     */
    private function validateOptionalCertificate(?string $certificate, string $field): void
    {
        if ($certificate !== null && $certificate !== '') {
            Assertion::maxLength($certificate, self::MAX_LENGTH, $field);
        }
    }

    /**
     * Validates the host certificate.
     *
     * @param string|null $certificate
     * @param ConnectionModeEnum $connectionMode
     * @param string $field Used for error reporting
     *
     * @throws AssertionFailedException
     */
    private function validateHostCertificate(?string $certificate, ConnectionModeEnum $connectionMode, string $field): void
    {
        if ($certificate !== null && is_string($certificate)) {
            if ($connectionMode === ConnectionModeEnum::SECURE) {
                Assertion::notEmptyString($certificate, $field);
            }
            Assertion::maxLength($certificate, self::MAX_LENGTH, $field);
        }
    }
}
