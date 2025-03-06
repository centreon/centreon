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

/**
 * @phpstan-type _CmaParameters array{
 *	    is_reverse: bool,
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
    public const CERTIFICATE_BASE_PATH = '/etc/pki/';

    /** @var _CmaParameters */
    private array $parameters;

    /**
     * @param array<string,mixed> $parameters
     *
     * @throws AssertionFailedException
     */
    public function __construct(
        array $parameters
    ){
        $parameters = $this->normalizeCertificatePaths($parameters);

        /** @var _CmaParameters $parameters */
        Assertion::notEmptyString($parameters['otel_public_certificate'], 'configuration.otel_public_certificate');
        Assertion::maxLength($parameters['otel_public_certificate'], self::MAX_LENGTH, 'configuration.otel_public_certificate');
        Assertion::notEmptyString($parameters['otel_private_key'], 'configuration.otel_private_key');
        Assertion::maxLength($parameters['otel_private_key'], self::MAX_LENGTH, 'configuration.otel_private_key');
        if ($parameters['otel_ca_certificate'] !== null) {
            Assertion::notEmptyString($parameters['otel_ca_certificate'], 'configuration.otel_ca_certificate');
            Assertion::maxLength($parameters['otel_ca_certificate'], self::MAX_LENGTH, 'configuration.otel_ca_certificate');
        }

        if ($parameters['is_reverse'] === false && $parameters['hosts'] !== []) {
            $parameters['hosts'] = [];
        }

        foreach ($parameters['hosts'] as $host) {
            Assertion::ipOrDomain($host['address'], 'configuration.hosts[].address');
            Assertion::range($host['port'], 0, 65535, 'configuration.hosts[].port');
            if ($host['poller_ca_certificate'] !== null) {
                Assertion::notEmptyString($host['poller_ca_certificate'], 'configurationhosts[].poller_ca_certificate');
                Assertion::maxLength($host['poller_ca_certificate'], self::MAX_LENGTH, 'configuration.hosts[].poller_ca_certificate');
            }
            if ($host['poller_ca_name'] !== null) {
                Assertion::notEmptyString($host['poller_ca_name'], 'configuration.hosts[].poller_ca_name');
                Assertion::maxLength($host['poller_ca_name'], self::MAX_LENGTH, 'configuration.hosts[].poller_ca_name');
            }
        }

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

    public function getBrokerDirective(): ?string
    {
        return self::BROKER_MODULE_DIRECTIVE;
    }

    /**
     * Normalizes the certificate paths in the given parameters array.
     *
     * @param array<string,mixed> $parameters
     *
     * @return array<string, mixed>
     */
    private function normalizeCertificatePaths(array $parameters): array
    {
        foreach ($parameters as $key => $value) {
            if (
                (
                    str_ends_with($key, '_certificate')
                    || str_ends_with($key, '_key')
                )
                && (is_string($value) || is_null($value))
            ) {
                $parameters[$key] = $this->prependPrefix($value);
            }

            if ($key === 'hosts' && is_array($value)) {
                foreach ($value as $hostIndex => $host) {
                    if (isset($host['poller_ca_certificate']) && is_string($host['poller_ca_certificate'])) {
                        $parameters[$key][$hostIndex]['poller_ca_certificate'] = $this->prependPrefix($host['poller_ca_certificate']);
                    }
                }
            }
        }

        return $parameters;
    }

    /**
     * Prepends a prefix to a certificate path.
     *
     * @param ?string $path
     *
     * @return ?string
     */
    private function prependPrefix(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return $path;
        }

        return str_starts_with($path, self::CERTIFICATE_BASE_PATH)
            ? $path
            : self::CERTIFICATE_BASE_PATH . ltrim($path, '/');
    }
}
