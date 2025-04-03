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
    public const BROKER_DIRECTIVE = '/usr/lib64/centreon-engine/libopentelemetry.so /etc/centreon-engine/otl_server.json';
    public const MAX_LENGTH = 255;

    /** @var _TelegrafParameters */
    private array $parameters;

    /**
     * @param array<string,mixed> $parameters
     *
     * @throws AssertionFailedException
     */
    public function __construct(
        array $parameters
    ){
        /** @var _TelegrafParameters $parameters */
        if (isset($parameters['connection_mode'])) {
            $connectionMode = $parameters['connection_mode'];
        } else {
            $connectionMode = (empty($parameters['otel_public_certificate'])
                || empty($parameters['otel_private_key'])
                || empty($parameters['conf_certificate'])
                || empty($parameters['conf_private_key']))
                    ? ConnectionModeEnum::NO_TLS
                    : ConnectionModeEnum::SECURE;
        }

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
            $this->validateOptionalCertificate($parameters['otel_public_certificate'], 'configuration.otel_public_certificate');
            $this->validateOptionalCertificate($parameters['otel_private_key'], 'configuration.otel_private_key');
            $this->validateOptionalCertificate($parameters['conf_certificate'], 'configuration.conf_certificate');
            $this->validateOptionalCertificate($parameters['conf_private_key'], 'configuration.conf_private_key');
            $this->validateOptionalCertificate($parameters['otel_ca_certificate'], 'configuration.otel_ca_certificate');
        }

        unset($parameters['connection_mode']);

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
