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

namespace Tests\App\MonitoringConfiguration\Application\EventHandler;

use App\MonitoringConfiguration\Application\Command\DuplicateHostServicesCommand;
use App\MonitoringConfiguration\Application\EventHandler\DuplicateHostServicesEventHandler;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Event\HostServicesDuplicationRequested;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Tests\App\Shared\Double\CommandBusSpy;

final class DuplicateHostServicesEventHandlerTest extends TestCase
{
    private CommandBusSpy $commandBus;

    private LoggerInterface&MockObject $logger;

    private DuplicateHostServicesEventHandler $handler;

    protected function setUp(): void
    {
        $this->commandBus = new CommandBusSpy();
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->handler = new DuplicateHostServicesEventHandler($this->commandBus, $this->logger);
    }

    public function testItDuplicatesTheServicesOfTheDuplicatedHost(): void
    {
        $this->logger->expects(self::never())->method('error');

        ($this->handler)(new HostServicesDuplicationRequested(new HostId(5), new HostId(9)));

        self::assertCount(1, $this->commandBus->executed);
        $command = $this->commandBus->executed[0];
        self::assertInstanceOf(DuplicateHostServicesCommand::class, $command);
        self::assertSame(5, $command->sourceHostId->value);
        self::assertSame(9, $command->newHostId->value);
    }

    /**
     * The event is delivered after the commit, so nothing can be rolled back and an escaping
     * exception would turn a duplicated host into a 5xx — a failing logger included.
     */
    public function testItSwallowsAFailingLoggerToo(): void
    {
        $this->commandBus->throws = true;
        $this->logger->method('error')->willThrowException(new \RuntimeException('The logger failed.'));

        ($this->handler)(new HostServicesDuplicationRequested(new HostId(5), new HostId(9)));

        self::assertCount(1, $this->commandBus->executed);
    }

    public function testItLogsAndSwallowsADuplicationFailure(): void
    {
        $this->commandBus->throws = true;
        $this->logger->expects(self::once())->method('error');

        ($this->handler)(new HostServicesDuplicationRequested(new HostId(5), new HostId(9)));

        self::assertCount(1, $this->commandBus->executed);
    }
}
