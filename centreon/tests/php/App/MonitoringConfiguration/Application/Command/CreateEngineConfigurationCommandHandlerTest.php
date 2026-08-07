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

namespace Tests\App\MonitoringConfiguration\Application\Command;

use App\MonitoringConfiguration\Application\Command\CreateEngineConfigurationCommand;
use App\MonitoringConfiguration\Application\Command\CreateEngineConfigurationCommandHandler;
use App\MonitoringConfiguration\Domain\Aggregate\EngineConfiguration\BrokerOptions;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use PHPUnit\Framework\TestCase;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeEngineConfigurationRepository;

final class CreateEngineConfigurationCommandHandlerTest extends TestCase
{
    public function testItCreatesDefaultEngineConfiguration(): void
    {
        $repository = new FakeEngineConfigurationRepository();
        $handler = new CreateEngineConfigurationCommandHandler($repository);

        $handler(new CreateEngineConfigurationCommand(
            pollerId: new PollerId(42),
            pollerName: 'My Poller',
        ));

        self::assertCount(1, $repository->engineConfigurations);

        $cfg = array_values($repository->engineConfigurations)[0];
        self::assertSame(42, $cfg->pollerId->value);
        self::assertSame('My Poller', $cfg->name);
    }

    public function testItSetsNameAsNagiosName(): void
    {
        $repository = new FakeEngineConfigurationRepository();
        $handler = new CreateEngineConfigurationCommandHandler($repository);

        $handler(new CreateEngineConfigurationCommand(
            pollerId: new PollerId(1),
            pollerName: 'Production',
        ));

        $cfg = array_values($repository->engineConfigurations)[0];
        self::assertSame('Production', $cfg->name);
    }

    public function testItSlugifiesPollerNameForBrokerModuleCfgFile(): void
    {
        $repository = new FakeEngineConfigurationRepository();
        $handler = new CreateEngineConfigurationCommandHandler($repository);

        $handler(new CreateEngineConfigurationCommand(
            pollerId: new PollerId(1),
            pollerName: 'My Remote Poller',
        ));

        $cfg = array_values($repository->engineConfigurations)[0];
        self::assertSame('/etc/centreon-broker/my-remote-poller-module.json', $cfg->broker->brokerModuleCfgFile);
    }

    public function testItSetsCheckServiceFreshnessToTrue(): void
    {
        $repository = new FakeEngineConfigurationRepository();
        $handler = new CreateEngineConfigurationCommandHandler($repository);

        $handler(new CreateEngineConfigurationCommand(
            pollerId: new PollerId(1),
            pollerName: 'Test',
        ));

        $cfg = array_values($repository->engineConfigurations)[0];
        self::assertTrue($cfg->freshnessAndFlap->checkServiceFreshness);
    }

    public function testItSetsBrokerModulePath(): void
    {
        $repository = new FakeEngineConfigurationRepository();
        $handler = new CreateEngineConfigurationCommandHandler($repository);

        $handler(new CreateEngineConfigurationCommand(
            pollerId: new PollerId(1),
            pollerName: 'Test',
        ));

        $cfg = array_values($repository->engineConfigurations)[0];
        self::assertSame(BrokerOptions::MODULE_PATH, $cfg->broker->brokerModule);
    }

    public function testItSetsEngineNativeLogLevels(): void
    {
        $repository = new FakeEngineConfigurationRepository();
        $handler = new CreateEngineConfigurationCommandHandler($repository);

        $handler(new CreateEngineConfigurationCommand(
            pollerId: new PollerId(1),
            pollerName: 'Test',
        ));

        $cfg = array_values($repository->engineConfigurations)[0];
        $logger = $cfg->logging->loggerConfiguration;

        self::assertSame('info', $logger->configLevel->value);
        self::assertSame('info', $logger->eventsLevel->value);
        self::assertSame('info', $logger->checksLevel->value);
        self::assertSame('info', $logger->processLevel->value);
        self::assertSame('info', $logger->externalCommandLevel->value);

        self::assertSame('err', $logger->functionsLevel->value);
        self::assertSame('err', $logger->notificationsLevel->value);
        self::assertSame('err', $logger->eventbrokerLevel->value);
        self::assertSame('err', $logger->commandsLevel->value);
        self::assertSame('err', $logger->downtimesLevel->value);
        self::assertSame('err', $logger->commentsLevel->value);
        self::assertSame('err', $logger->macrosLevel->value);
        self::assertSame('err', $logger->runtimeLevel->value);
        self::assertSame('err', $logger->otlLevel->value);
    }
}
