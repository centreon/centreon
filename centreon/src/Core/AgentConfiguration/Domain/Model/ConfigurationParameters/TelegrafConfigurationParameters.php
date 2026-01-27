<?php

/*
 * Copyright 2005 - 2026 Centreon (https://www.centreon.com/)
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
use Core\AgentConfiguration\Domain\Model\ConfigurationParametersInterface;

/**
 * @phpstan-type _TelegrafParameters array{
 *	    otel_public_certificate: ?string,
 *	    otel_ca_certificate: ?string,
 *	    otel_private_key: ?string,
 *	    conf_server_port: int,
 *	    conf_certificate: ?string,
 *	    conf_private_key: ?string
 *  }
 */
class TelegrafConfigurationParameters implements ConfigurationParametersInterface
{
    public const BROKER_DIRECTIVE = '/usr/lib64/centreon-engine/libopentelemetry.so '
        . '/etc/centreon-engine/otl_server.json';
    public const MAX_LENGTH = 255;
    public const CERTIFICATE_BASE_PATH = '/etc/pki/';

    /** @var array<string> */
    private const FORBIDDEN_DIRECTORIES = [
        '/tmp', '/root', '/proc', '/mnt', '/run', '/snap', '/sys', '/boot',
    ];

    /**
     * @param array<string,mixed> $parameters
     *
     * @throws AssertionException
     */
    public function __construct(private array $parameters)
    {
        Assertion::range($this->parameters['conf_server_port'], 0, 65535, 'configuration.conf_server_port');

        $this->parameters['otel_public_certificate'] = $this->validateCertificatePath(
            $this->parameters['otel_public_certificate'],
            'configuration.otel_public_certificate'
        );
        $this->parameters['otel_private_key'] = $this->validateCertificatePath(
            $this->parameters['otel_private_key'],
            'configuration.otel_private_key'
        );
        $this->parameters['conf_certificate'] = $this->validateCertificatePath(
            $this->parameters['conf_certificate'],
            'configuration.conf_certificate'
        );
        $this->parameters['conf_private_key'] = $this->validateCertificatePath(
            $this->parameters['conf_private_key'],
            'configuration.conf_private_key'
        );
        $this->parameters['otel_ca_certificate'] = $this->validateCertificatePath(
            $this->parameters['otel_ca_certificate'],
            'configuration.otel_ca_certificate'
        );
    }

    /**
     * @inheritDoc
     *
     * @return _TelegrafParameters
     */
    public function getData(): array
    {
        /** @var _TelegrafParameters */
        return $this->parameters;
    }

    public function getBrokerDirective(): ?string
    {
        return self::BROKER_DIRECTIVE;
    }

    /**
     * Prepends a prefix to a certificate path if it's a relative path.
     *
     * @param string $path
     *
     * @return string
     */
    private function prependPrefix(string $path): string
    {
        if (str_starts_with($path, '/')) {
            return $path;
        }

        return self::CERTIFICATE_BASE_PATH . ltrim($path, '/');
    }

    private function validateCertificatePath(?string $path, string $field): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        $this->assertPathSecurity($path, $field);
        $normalizedPath = $this->prependPrefix($path);
        Assertion::maxLength($normalizedPath, self::MAX_LENGTH, $field);

        return $normalizedPath;
    }

    /**
     * Validates that a certificate path is safe and not in a forbidden directory.
     *
     * @param string $path
     * @param string $field Used for error reporting
     *
     * @throws AssertionException
     */
    private function assertPathSecurity(string $path, string $field): void
    {
        // Reject relative path patterns
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
            if (! str_starts_with($path, self::CERTIFICATE_BASE_PATH) && $path !== '/etc/pki') {
                throw new AssertionException(
                    sprintf('[%s] The path "%s" can only be in /etc/pki/ directory', $field, $path),
                );
            }
        }
    }
}
