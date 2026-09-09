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

namespace Tests\App\MonitoringConfiguration\Domain\Aggregate\BrokerConfiguration;

use App\MonitoringConfiguration\Domain\Aggregate\BrokerConfiguration\BrokerConfigFileName;
use App\MonitoringConfiguration\Domain\Aggregate\BrokerConfiguration\BrokerConfigKey;
use App\MonitoringConfiguration\Domain\Aggregate\BrokerConfiguration\BrokerConfigName;
use App\MonitoringConfiguration\Domain\Aggregate\BrokerConfiguration\BrokerConfiguration;
use App\MonitoringConfiguration\Domain\Aggregate\BrokerConfiguration\BrokerFlowGroupEnum;
use App\MonitoringConfiguration\Domain\Aggregate\BrokerConfiguration\BrokerInputOutput;
use App\MonitoringConfiguration\Domain\Aggregate\BrokerConfiguration\BrokerLog;
use App\MonitoringConfiguration\Domain\Aggregate\BrokerConfiguration\BrokerLoggerEnum;
use App\MonitoringConfiguration\Domain\Aggregate\BrokerConfiguration\BrokerLogLevelEnum;
use App\MonitoringConfiguration\Domain\Aggregate\BrokerConfiguration\BrokerParameter;
use App\MonitoringConfiguration\Domain\Aggregate\BrokerConfiguration\BrokerStreamTypeEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\Shared\Domain\Collection;
use PHPUnit\Framework\TestCase;

final class BrokerConfigurationTest extends TestCase
{
    public function testItBuildsWithAtLeastOneFlowAndOneLogger(): void
    {
        $config = $this->buildConfiguration(
            flows: [$this->aFlow()],
            logs: [$this->aLog()],
        );

        self::assertCount(1, $config->flows);
        self::assertCount(1, $config->logs);
    }

    public function testItRejectsAnEmptyLoggerCollection(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('A broker configuration must define at least one logger.');

        $this->buildConfiguration(
            flows: [$this->aFlow()],
            logs: [],
        );
    }

    public function testItRejectsAnEmptyFlowCollection(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('A broker configuration must define at least one flow.');

        $this->buildConfiguration(
            flows: [],
            logs: [$this->aLog()],
        );
    }

    /**
     * @param BrokerInputOutput[] $flows
     * @param BrokerLog[] $logs
     */
    private function buildConfiguration(array $flows, array $logs): BrokerConfiguration
    {
        return new BrokerConfiguration(
            brokerConfigurationId: null,
            pollerId: new PollerId(1),
            name: new BrokerConfigName('poller-module'),
            fileName: new BrokerConfigFileName('poller-module.json'),
            isActivated: true,
            daemon: false,
            configWriteTimestamp: false,
            configWriteThreadId: false,
            eventQueueMaxSize: 100000,
            commandFile: '',
            cacheDirectory: '/var/lib/centreon-engine',
            logDirectory: '/var/log/centreon-broker',
            statsActivate: true,
            flows: new Collection($flows, BrokerInputOutput::class),
            logs: new Collection($logs, BrokerLog::class),
        );
    }

    private function aFlow(): BrokerInputOutput
    {
        return new BrokerInputOutput(
            group: BrokerFlowGroupEnum::Output,
            groupId: 0,
            type: BrokerStreamTypeEnum::Ipv4,
            parameters: new Collection(
                [new BrokerParameter(BrokerConfigKey::NAME, 'central-module-master-output', 0)],
                BrokerParameter::class,
            ),
        );
    }

    private function aLog(): BrokerLog
    {
        return new BrokerLog(BrokerLoggerEnum::Core, BrokerLogLevelEnum::Info);
    }
}
