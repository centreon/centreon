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
 * @phpstan-type _TelegrafParameters array{
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
        Assertion::range($parameters['conf_server_port'], 0, 65535, 'configuration.conf_server_port');

        Assertion::notEmptyString($parameters['otel_public_certificate'], 'configuration.otel_public_certificate');
        Assertion::notEmptyString($parameters['otel_private_key'], 'configuration.otel_private_key');
        Assertion::notEmptyString($parameters['conf_certificate'], 'configuration.conf_certificate');
        Assertion::notEmptyString($parameters['conf_private_key'], 'configuration.conf_private_key');
        if ($parameters['otel_ca_certificate'] !== null) {
            Assertion::notEmptyString($parameters['otel_ca_certificate'], 'configuration.otel_ca_certificate');
        }

        Assertion::maxLength($parameters['otel_public_certificate'], self::MAX_LENGTH, 'configuration.otel_public_certificate');
        Assertion::maxLength($parameters['otel_private_key'], self::MAX_LENGTH, 'configuration.otel_private_key');
        Assertion::maxLength($parameters['conf_certificate'], self::MAX_LENGTH, 'configuration.conf_certificate');
        Assertion::maxLength($parameters['conf_private_key'], self::MAX_LENGTH, 'configuration.conf_private_key');
        if ($parameters['otel_ca_certificate'] !== null) {
            Assertion::maxLength($parameters['otel_ca_certificate'], self::MAX_LENGTH, 'configuration.otel_ca_certificate');
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
}
