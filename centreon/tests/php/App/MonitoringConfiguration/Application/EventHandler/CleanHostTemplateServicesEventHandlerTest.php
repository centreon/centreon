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

use App\MonitoringConfiguration\Application\EventHandler\CleanHostTemplateServicesEventHandler;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Event\HostTemplateServicesCleanupRequested;
use App\Security\Domain\Aggregate\UserId;
use App\Shared\Domain\Collection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeServiceDeployer;

final class CleanHostTemplateServicesEventHandlerTest extends TestCase
{
    private FakeServiceDeployer $serviceDeployer;

    private LoggerInterface&MockObject $logger;

    private CleanHostTemplateServicesEventHandler $handler;

    protected function setUp(): void
    {
        $this->serviceDeployer = new FakeServiceDeployer();
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->handler = new CleanHostTemplateServicesEventHandler($this->serviceDeployer, $this->logger);
    }

    public function testItRemovesTheServicesOfTheRemovedTemplates(): void
    {
        $this->logger->expects(self::never())->method('error');

        ($this->handler)($this->event());

        self::assertSame(
            [['hostId' => 12, 'previousTemplateIds' => [3, 4], 'templateIds' => [4], 'requestedBy' => 7]],
            $this->serviceDeployer->removeCalls,
        );
    }

    public function testItLogsAndSwallowsACleanupFailure(): void
    {
        $this->serviceDeployer->removeThrows = true;
        $this->logger->expects(self::once())
            ->method('error')
            ->with(self::isString(), self::callback(
                static fn (array $context): bool => $context['host_id'] === 12
                    && $context['exception'] instanceof \Throwable,
            ));

        ($this->handler)($this->event());

        self::assertCount(1, $this->serviceDeployer->removeCalls);
    }

    /**
     * The event is delivered after the commit, so nothing can be rolled back and an escaping
     * exception would turn a saved host into a 5xx — a failing logger included.
     */
    public function testItSwallowsAFailingLoggerToo(): void
    {
        $this->serviceDeployer->removeThrows = true;
        $this->logger->method('error')->willThrowException(new \RuntimeException('The logger failed.'));

        ($this->handler)($this->event());

        self::assertCount(1, $this->serviceDeployer->removeCalls);
    }

    private function event(): HostTemplateServicesCleanupRequested
    {
        return new HostTemplateServicesCleanupRequested(
            new HostId(12),
            new Collection([new HostTemplateId(3), new HostTemplateId(4)], HostTemplateId::class),
            new Collection([new HostTemplateId(4)], HostTemplateId::class),
            new UserId(7),
        );
    }
}
