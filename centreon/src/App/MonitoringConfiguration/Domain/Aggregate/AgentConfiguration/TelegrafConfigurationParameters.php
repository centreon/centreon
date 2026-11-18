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

class TelegrafConfigurationParameters extends AbstractConfigurationParameters
{
    public const BROKER_DIRECTIVE = '/usr/lib64/centreon-engine/libopentelemetry.so /etc/centreon-engine/otl_server.json';

    /**
     * @throws \InvalidArgumentException
     */
    public function __construct(array $parameters)
    {
        parent::__construct($parameters);

        /** @var mixed $confServerPort */
        $confServerPort = $this->parameters['conf_server_port'] ?? null;
        Assert::integer($confServerPort, '[configuration.conf_server_port] Port must be an integer.');
        Assert::range($confServerPort, 1, 65535, '[configuration.conf_server_port] Port must be between 1 and 65535. Got: %s');

        $this->normalizeCertificateParam('otel_public_certificate', 'configuration.otel_public_certificate');
        $this->normalizeCertificateParam('otel_private_key', 'configuration.otel_private_key');
        $this->normalizeCertificateParam('conf_certificate', 'configuration.conf_certificate');
        $this->normalizeCertificateParam('conf_private_key', 'configuration.conf_private_key');
        $this->normalizeCertificateParam('otel_ca_certificate', 'configuration.otel_ca_certificate');
    }

    public function getData(): array
    {
        return $this->parameters;
    }

    public function getBrokerDirective(): ?string
    {
        return self::BROKER_DIRECTIVE;
    }
}
