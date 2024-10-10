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
 *		otlp_certificate: string,
 *		otlp_ca_certificate: string,
 *		otlp_private_key: string,
 *		poller_ca_certificate: ?string,
 *		poller_ca_name: ?string,
 *		hosts: array<array{
 *			address: string,
 *			port: int,
 *			certificate: string,
 *			key: string
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
    public function __construct(
        array $parameters
    ){
        /** @var _CmaParameters $parameters */
        Assertion::notEmptyString($parameters['otlp_certificate'], 'configuration.otlp_certificate');
        Assertion::notEmptyString($parameters['otlp_ca_certificate'], 'configuration.otlp_ca_certificate');
        Assertion::notEmptyString($parameters['otlp_private_key'], 'configuration.otlp_private_key');
        if ($parameters['poller_ca_certificate'] !== null) {
            Assertion::notEmptyString($parameters['poller_ca_certificate'], 'configuration.poller_ca_certificate');
        }
        if ($parameters['poller_ca_name'] !== null) {
            Assertion::notEmptyString($parameters['poller_ca_name'], 'configuration.poller_ca_name');
        }

        Assertion::maxLength($parameters['otlp_certificate'], self::MAX_LENGTH, 'configuration.otlp_certificate');
        Assertion::maxLength($parameters['otlp_ca_certificate'], self::MAX_LENGTH, 'configuration.otlp_ca_certificate');
        Assertion::maxLength($parameters['otlp_private_key'], self::MAX_LENGTH, 'configuration.otlp_private_key');
        if ($parameters['poller_ca_certificate'] !== null) {
            Assertion::maxLength($parameters['poller_ca_certificate'], self::MAX_LENGTH, 'configuration.poller_ca_certificate');
        }
        if ($parameters['poller_ca_name'] !== null) {
            Assertion::maxLength($parameters['poller_ca_name'], self::MAX_LENGTH, 'configuration.poller_ca_name');
        }

        if ($parameters['is_reverse'] === false && $parameters['hosts'] !== []) {
            $parameters['hosts'] = [];
        }

        foreach ($parameters['hosts'] as $host) {
            Assertion::ipOrDomain($host['address'], 'configuration.hosts[].address');
            Assertion::range($host['port'], 0, 65535, 'configuration.hosts[].port');
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
}
