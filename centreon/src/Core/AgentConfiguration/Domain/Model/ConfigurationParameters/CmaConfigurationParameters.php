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

namespace Core\AgentConfiguration\Domain\Model\ConfigurationParameters;

use Centreon\Domain\Common\Assertion\Assertion;
use Centreon\Domain\Common\Assertion\AssertionException;
use Core\AgentConfiguration\Domain\Model\AgentConfiguration;
use Core\AgentConfiguration\Domain\Model\ConfigurationParametersInterface;

/**
 * @phpstan-type _CmaParameters array{
 *	    agent_initiated: bool,
 *		otel_public_certificate: ?string,
 *		otel_private_key: ?string,
 *		otel_ca_certificate: ?string,
 *      tokens: array<array{name:string,creator_id:int}>,
 *      port: int,
 *      poller_initiated: bool,
 *		hosts: array<array{
 *			id: int,
 *			address: string,
 *			port: int,
 *			poller_ca_certificate: ?string,
 *			poller_ca_name: ?string,
 *			token: null|array{name:string,creator_id:int}
 *		}>
 *  }
 */
class CmaConfigurationParameters implements ConfigurationParametersInterface
{
    public const BROKER_MODULE_DIRECTIVE = '/usr/lib64/centreon-engine/libopentelemetry.so '
        . '/etc/centreon-engine/otl_server.json';
    public const MAX_LENGTH = 255;
    public const DEFAULT_CHECK_INTERVAL = 60;
    public const DEFAULT_EXPORT_PERIOD = 60;
    public const CERTIFICATE_BASE_PATH = '/etc/pki/';

    /** @var array<string> Forbidden base directories for certificates */
    private const FORBIDDEN_DIRECTORIES = [
        '/tmp',
        '/root',
        '/proc',
        '/mnt',
        '/run',
        '/snap',
        '/sys',
        '/boot',
    ];

    /** @var _CmaParameters */
    private array $parameters;

    /**
     * @param array<string,mixed> $parameters
     * @param bool $fromReadRepository
     *
     * @throws AssertionException
     */
    public function __construct(array $parameters, bool $fromReadRepository = false)
    {
        /** @var _CmaParameters $parameters */
        $parameters = $this->normalizeCertificatePaths($parameters);

        if ($parameters['agent_initiated'] === false) {
            $parameters['otel_public_certificate'] = null;
            $parameters['otel_private_key'] = null;
            $parameters['otel_ca_certificate'] = null;
            $parameters['tokens'] = [];
        } else {
            $this->validateOptionalCertificate(
                $parameters['otel_public_certificate'],
                'configuration.otel_public_certificate'
            );
            $this->validateOptionalCertificate(
                $parameters['otel_private_key'],
                'configuration.otel_private_key'
            );
            $this->validateOptionalCertificate(
                $parameters['otel_ca_certificate'],
                'configuration.otel_ca_certificate'
            );

            // $fromReadRepository allow configurations created before tokens was mandatoryto be read even without tokens
            if (! $fromReadRepository) {
                Assertion::notEmpty($parameters['tokens'], 'configuration.tokens');
                foreach ($parameters['tokens'] as $token) {
                    Assertion::notEmptyString($token['name']);
                }
            }
            if (! isset($parameters['port'])) {
                $parameters['port'] = AgentConfiguration::DEFAULT_PORT;
            }
            Assertion::range($parameters['port'] ?? AgentConfiguration::DEFAULT_PORT, 0, 65535, 'configuration.port');
        }

        if ($parameters['poller_initiated'] === false) {
            $parameters['hosts'] = [];
        } else {
            foreach ($parameters['hosts'] as $host) {
                Assertion::positiveInt($host['id'], 'configuration.hosts[].id');
                Assertion::ipOrDomain($host['address'], 'configuration.hosts[].address');
                Assertion::range($host['port'], 0, 65535, 'configuration.hosts[].port');
                $this->validateOptionalCertificate(
                    $host['poller_ca_certificate'],
                    'configuration.hosts[].poller_ca_certificate'
                );
                $this->validateOptionalCertificate(
                    $host['poller_ca_name'],
                    'configuration.hosts[].poller_ca_name'
                );

                // $fromReadRepository allow configurations created before tokens was mandatoryto be read even without tokens
                if (! $fromReadRepository) {
                    Assertion::notNull($host['token'], 'configuration.hosts[].token');
                    Assertion::notEmptyString($host['token']['name'] ?? '');
                    Assertion::positiveInt($host['token']['creator_id'] ?? 0);
                }
            }
        }

        /** @var _CmaParameters $parameters */
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
                        $parameters[$key][$hostIndex]['poller_ca_certificate'] = $this->prependPrefix(
                            $host['poller_ca_certificate']
                        );
                    }
                }
            }
        }

        return $parameters;
    }

    /**
     * Prepends a prefix to a certificate path if it's a relative path.
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

        // Prepend the default certificate base path
        return str_starts_with($path, '/')
            ? $path
            : self::CERTIFICATE_BASE_PATH . ltrim($path, '/');
    }

    /**
     * Validates an optional certificate.
     *
     * @param ?string $certificate
     * @param string $field Used for error reporting
     *
     * @throws AssertionException
     */
    private function validateOptionalCertificate(?string $certificate, string $field): void
    {
        if ($certificate !== null && $certificate !== '') {
            Assertion::maxLength($certificate, self::MAX_LENGTH, $field);
            $this->validateCertificatePath($certificate, $field);
        }
    }

    /**
     * Validates that a certificate path is safe and not in a forbidden directory.
     *
     * @param string $path
     * @param string $field Used for error reporting
     *
     * @throws AssertionException
     */
    private function validateCertificatePath(string $path, string $field): void
    {
        // Reject relative paths
        if (
            str_contains($path, '../')
            || str_contains($path, '//')
            || str_contains($path, './')
            || $path === '.'
            || $path === '..'
        ) {
            throw new AssertionException(
                sprintf('[%s] The path "%s" contains invalid relative path patterns', $field, $path),
            );
        }

        // Reject hidden directories
        if (preg_match('#/\\.#', $path) || (str_starts_with($path, '.') && ! str_starts_with($path, './'))) {
            throw new AssertionException(
                sprintf('[%s] The path "%s" cannot be in a hidden directory', $field, $path),
            );
        }

        // Reject forbidden directories
        foreach (self::FORBIDDEN_DIRECTORIES as $forbiddenDirectory) {
            if (str_starts_with($path, $forbiddenDirectory . '/') || $path === $forbiddenDirectory) {
                throw new AssertionException(
                    sprintf('[%s] The path "%s" cannot be in directory %s', $field, $path, $forbiddenDirectory),
                );
            }
        }

        // Reject forbidden directories : /etc but not /etc/pki
        if (str_starts_with($path, '/etc/')) {
            if (! str_starts_with($path, '/etc/pki/') && $path !== '/etc/pki') {
                throw new AssertionException(
                    sprintf('[%s] The path "%s" can only be in /etc/pki/ directory', $field, $path),
                );
            }
        }
    }
}
