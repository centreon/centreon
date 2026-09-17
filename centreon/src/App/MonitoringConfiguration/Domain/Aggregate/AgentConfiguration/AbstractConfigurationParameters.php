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

abstract class AbstractConfigurationParameters
{
    protected const MAX_LENGTH = 255;
    protected const CERTIFICATE_BASE_PATH = '/etc/pki/';

    /** @var array<string> */
    protected const FORBIDDEN_DIRECTORIES = [
        '/tmp', '/root', '/proc', '/mnt', '/run', '/snap', '/sys', '/boot',
    ];

    /**
     * @param array<string,mixed> $parameters
     */
    public function __construct(protected array $parameters)
    {
    }

    /**
     * @return array<string,mixed>
     */
    abstract public function getData(): array;

    abstract public function getBrokerDirective(): ?string;

    /**
     * Reads $this->parameters[$key], validates it as a certificate path and writes the normalised value back.
     *
     * @param string $key key in $this->parameters to read and overwrite
     * @param string $field name of the configuration parameter, used as a label in validation error messages
     */
    protected function normalizeCertificateParam(string $key, string $field): void
    {
        $this->parameters[$key] = $this->validateCertificatePath(
            is_string($this->parameters[$key]) ? $this->parameters[$key] : null,
            $field
        );
    }

    /**
     * @param string $path
     * @param string $field Name of the configuration parameter (e.g. 'ca_certificate', 'server_certificate'),
     *                      used as a label in validation error messages.
     */
    protected function validateCertificatePath(?string $path, string $field): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        $this->assertPathSecurity($path, $field);
        $normalizedPath = $this->prependPrefix($path);
        Assert::maxLength($normalizedPath, self::MAX_LENGTH, $field);

        return $normalizedPath;
    }

    protected function prependPrefix(string $path): string
    {
        if (str_starts_with($path, '/')) {
            return $path;
        }

        return self::CERTIFICATE_BASE_PATH . ltrim($path, '/');
    }

    /**
     * @param string $field Name of the configuration parameter (e.g. 'ca_certificate', 'server_certificate'),
     *                      used as a label in validation error messages.
     * @throws \InvalidArgumentException
     */
    protected function assertPathSecurity(string $path, string $field): void
    {
        // Reject relative path patterns
        Assert::false(
            str_contains($path, '../')
            || str_contains($path, '//')
            || str_contains($path, './')
            || $path === '.'
            || $path === '..',
            sprintf('[%s] The path "%s" contains invalid relative path patterns', $field, $path)
        );

        // Reject hidden directories
        Assert::false(
            (bool) preg_match('#/\\.#', $path)
            || (str_starts_with($path, '.') && ! str_starts_with($path, './')),
            sprintf('[%s] The path "%s" cannot be in a hidden directory', $field, $path)
        );

        // Reject forbidden directories
        foreach (static::FORBIDDEN_DIRECTORIES as $forbiddenDirectory) {
            Assert::false(
                str_starts_with($path, $forbiddenDirectory . '/') || $path === $forbiddenDirectory,
                sprintf('[%s] The path "%s" cannot be in directory %s', $field, $path, $forbiddenDirectory)
            );
        }

        // Reject /etc except /etc/pki
        if ($path === '/etc' || str_starts_with($path, '/etc/')) {
            $basePath = rtrim(self::CERTIFICATE_BASE_PATH, '/');
            Assert::true(
                $path === $basePath || str_starts_with($path, $basePath . '/'),
                sprintf('[%s] The path "%s" can only be in /etc/pki/ directory', $field, $path)
            );
        }
    }
}
