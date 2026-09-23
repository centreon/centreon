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

use App\MonitoringConfiguration\Application\EventHandler\DeployHostServicesEventHandler;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Event\HostServicesDeploymentRequested;
use App\Security\Domain\Aggregate\UserId;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeServiceDeployer;

final class DeployHostServicesEventHandlerTest extends TestCase
{
    private FakeServiceDeployer $serviceDeployer;

    private LoggerInterface&MockObject $logger;

    private DeployHostServicesEventHandler $handler;

    protected function setUp(): void
    {
        $this->serviceDeployer = new FakeServiceDeployer();
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->handler = new DeployHostServicesEventHandler($this->serviceDeployer, $this->logger);
    }

    public function testItDeploysTheServicesOfTheRequestedHost(): void
    {
        $this->logger->expects(self::never())->method('error');

        ($this->handler)(new HostServicesDeploymentRequested(new HostId(12), new UserId(7)));

        self::assertSame([['hostId' => 12, 'requestedBy' => 7]], $this->serviceDeployer->deployCalls);
    }

    public function testItLogsAndSwallowsADeploymentFailure(): void
    {
        $this->serviceDeployer->deployThrows = true;
        $this->logger->expects(self::once())
            ->method('error')
            ->with(self::isString(), self::callback(
                static fn (array $context): bool => $context['host_id'] === 12
                    && $context['exception'] instanceof \Throwable,
            ));

        ($this->handler)(new HostServicesDeploymentRequested(new HostId(12), new UserId(7)));

        self::assertCount(1, $this->serviceDeployer->deployCalls);
    }
}
