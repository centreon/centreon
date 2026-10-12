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

use App\MonitoringConfiguration\Application\Command\DeployHostServicesCommand;
use App\MonitoringConfiguration\Application\EventHandler\DeployHostServicesEventHandler;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Event\HostServicesDeploymentRequested;
use App\Security\Domain\Aggregate\UserId;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Tests\App\Shared\Double\CommandBusSpy;

final class DeployHostServicesEventHandlerTest extends TestCase
{
    private CommandBusSpy $commandBus;

    private LoggerInterface&MockObject $logger;

    private DeployHostServicesEventHandler $handler;

    protected function setUp(): void
    {
        $this->commandBus = new CommandBusSpy();
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->handler = new DeployHostServicesEventHandler($this->commandBus, $this->logger);
    }

    public function testItDeploysTheServicesOfTheRequestedHost(): void
    {
        $this->logger->expects(self::never())->method('error');

        ($this->handler)(new HostServicesDeploymentRequested(new HostId(12), new UserId(7)));

        self::assertCount(1, $this->commandBus->executed);
        $command = $this->commandBus->executed[0];
        self::assertInstanceOf(DeployHostServicesCommand::class, $command);
        self::assertSame(12, $command->hostId->value);
        self::assertSame(7, $command->requestedBy->value);
    }

    /**
     * The event is delivered after the commit, so nothing can be rolled back and an escaping
     * exception would turn a created host into a 5xx — a failing logger included.
     */
    public function testItSwallowsAFailingLoggerToo(): void
    {
        $this->commandBus->throws = true;
        $this->logger->method('error')->willThrowException(new \RuntimeException('The logger failed.'));

        ($this->handler)(new HostServicesDeploymentRequested(new HostId(12), new UserId(7)));

        self::assertCount(1, $this->commandBus->executed);
    }

    public function testItLogsAndSwallowsADeploymentFailure(): void
    {
        $this->commandBus->throws = true;
        $this->logger->expects(self::once())
            ->method('error')
            ->with(self::isString(), self::callback(
                static fn (array $context): bool => $context['host_id'] === 12
                    && $context['exception'] instanceof \Throwable,
            ));

        ($this->handler)(new HostServicesDeploymentRequested(new HostId(12), new UserId(7)));

        self::assertCount(1, $this->commandBus->executed);
    }
}
