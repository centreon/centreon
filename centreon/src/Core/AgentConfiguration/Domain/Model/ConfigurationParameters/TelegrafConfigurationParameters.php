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
 * @phpstan-type _TelegrafParameters array{
 *      connection_mode?: ConnectionModeEnum,
 *	    otel_public_certificate: string,
 *	    otel_ca_certificate: string|null,
 *	    otel_private_key: string,
 *	    conf_server_port: int,
 *	    conf_certificate: string,
 *	    conf_private_key: string
 *  }
 */
class TelegrafConfigurationParameters implements ConfigurationParametersInterface
{
    public const BROKER_DIRECTIVE = '/usr/lib64/centreon-engine/libopentelemetry.so '
        . '/etc/centreon-engine/otl_server.json';
    public const MAX_LENGTH = 255;
    public const CERTIFICATE_BASE_PATH = '/etc/pki/';

    /** @var _TelegrafParameters */
    private array $parameters;

    /**
     * @param array<string,mixed> $parameters
     *
     * @throws AssertionFailedException
     */
    public function __construct(array $parameters, ConnectionModeEnum $connectionMode){
        $parameters = $this->normalizeCertificatePaths($parameters);

        Assertion::range($parameters['conf_server_port'], 0, 65535, 'configuration.conf_server_port');

        if ($connectionMode === ConnectionModeEnum::SECURE) {
            $this->validateCertificate($parameters['otel_public_certificate'], 'configuration.otel_public_certificate');
            $this->validateCertificate($parameters['otel_private_key'], 'configuration.otel_private_key');
            $this->validateCertificate($parameters['conf_certificate'], 'configuration.conf_certificate');
            $this->validateCertificate($parameters['conf_private_key'], 'configuration.conf_private_key');
            if ($parameters['otel_ca_certificate'] !== null) {
                $this->validateCertificate($parameters['otel_ca_certificate'], 'configuration.otel_ca_certificate');
            }
        } else {
            $this->validateOptionalCertificate(
                $parameters['otel_public_certificate'],
                'configuration.otel_public_certificate'
            );
            $this->validateOptionalCertificate($parameters['otel_private_key'], 'configuration.otel_private_key');
            $this->validateOptionalCertificate($parameters['conf_certificate'], 'configuration.conf_certificate');
            $this->validateOptionalCertificate($parameters['conf_private_key'], 'configuration.conf_private_key');
            $this->validateOptionalCertificate($parameters['otel_ca_certificate'], 'configuration.otel_ca_certificate');
        }

        $this->parameters = $parameters;
    }

    /**
     * @inheritDoc
     *
     * @return _TelegrafParameters
     */
    public function getData(): array
    {
        return $this->parameters;
    }

    public function getBrokerDirective(): ?string
    {
        return self::BROKER_DIRECTIVE;
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

    /**
     * Validates a certificate.
     *
     * @param mixed $certificate
     * @param string $field
     *
     * @throws AssertionFailedException
     */
    private function validateCertificate($certificate, string $field): void
    {
        Assertion::notEmptyString($certificate, $field);
        Assertion::maxLength($certificate, self::MAX_LENGTH, $field);
    }

    /**
     * Validates an optional certificate.
     *
     * @param mixed $certificate
     * @param string $field
     *
     * @throws AssertionFailedException
     */
    private function validateOptionalCertificate($certificate, string $field): void
    {
        if ($certificate !== null && $certificate !== '') {
            Assertion::maxLength($certificate, self::MAX_LENGTH, $field);
        }
    }
}
