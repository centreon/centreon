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

namespace Tests\App\MonitoringConfiguration\Infrastructure\Double;

use App\MonitoringConfiguration\Domain\Aggregate\BrokerConfiguration\BrokerConfiguration;
use App\MonitoringConfiguration\Domain\Aggregate\BrokerConfiguration\BrokerConfigurationId;
use App\MonitoringConfiguration\Domain\Repository\BrokerConfigurationRepository;
use App\Shared\Domain\Aggregate\AggregateRoot;

final class FakeBrokerConfigurationRepository implements BrokerConfigurationRepository
{
    /** @var array<int, BrokerConfiguration> */
    public array $brokerConfigurations = [];

    public ?string $centralBbdoServerAuthorizationToken = null;

    public function add(BrokerConfiguration $brokerConfiguration): void
    {
        do {
            $brokerConfigurationId = mt_rand();
        } while (isset($this->brokerConfigurations[$brokerConfigurationId]));

        $reflection = new \ReflectionProperty(AggregateRoot::class, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($brokerConfiguration, new BrokerConfigurationId($brokerConfigurationId));

        $this->brokerConfigurations[$brokerConfigurationId] = $brokerConfiguration;
    }

    public function getCentralBbdoServerAuthorizationToken(): string
    {
        if ($this->centralBbdoServerAuthorizationToken === null) {
            throw new \RuntimeException('No BBDO server authorization token found on the central broker');
        }

        return $this->centralBbdoServerAuthorizationToken;
    }
}
