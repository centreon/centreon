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

namespace Tests\App\MonitoringConfiguration\Infrastructure\Legacy;

use App\MonitoringConfiguration\Domain\Exception\GorgoneNodesSyncFailedException;
use App\MonitoringConfiguration\Infrastructure\Legacy\LegacyGorgoneNodesSynchronizer;
use App\Shared\Infrastructure\Legacy\LegacyContainer;
use Centreon\Domain\Gorgone\Command\NodesSync;
use Centreon\Domain\Gorgone\GorgoneException;
use Centreon\Domain\Gorgone\Interfaces\GorgoneServiceInterface;
use Centreon\Domain\Gorgone\Interfaces\ResponseInterface;
use PHPUnit\Framework\TestCase;

/**
 * The caller absorbs a failed sync, so nothing downstream would notice this adapter resolving
 * the wrong service or sending the wrong command. These tests are the only guard.
 */
final class LegacyGorgoneNodesSynchronizerTest extends TestCase
{
    public function testItDoesNotTouchTheLegacyContainerBeforeItIsUsed(): void
    {
        $container = $this->createMock(LegacyContainer::class);
        $container->expects(self::never())->method('get');

        new LegacyGorgoneNodesSynchronizer($container);
    }

    public function testItSendsANodesSyncThroughTheLegacyGorgoneClient(): void
    {
        $gorgoneService = $this->createMock(GorgoneServiceInterface::class);
        $gorgoneService->expects(self::once())
            ->method('send')
            ->with(self::isInstanceOf(NodesSync::class))
            ->willReturn($this->createMock(ResponseInterface::class));

        $this->buildSynchronizer($gorgoneService)->synchronize();
    }

    public function testItWrapsAGorgoneFailureAndKeepsTheCause(): void
    {
        $cause = new GorgoneException('Error when connecting to the Gorgone server');

        $gorgoneService = $this->createMock(GorgoneServiceInterface::class);
        $gorgoneService->method('send')->willThrowException($cause);

        try {
            $this->buildSynchronizer($gorgoneService)->synchronize();
            self::fail('Expected ' . GorgoneNodesSyncFailedException::class);
        } catch (GorgoneNodesSyncFailedException $exception) {
            self::assertSame($cause, $exception->getPrevious());
        }
    }

    public function testItLetsAWiringErrorPropagateUnwrapped(): void
    {
        // Absorbed by the caller if it were wrapped, so a mistyped service id would degrade
        // every poller creation in silence.
        $container = $this->createMock(LegacyContainer::class);
        $container->method('get')->willThrowException(new \RuntimeException('Service not found'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Service not found');

        (new LegacyGorgoneNodesSynchronizer($container))->synchronize();
    }

    private function buildSynchronizer(GorgoneServiceInterface $gorgoneService): LegacyGorgoneNodesSynchronizer
    {
        $container = $this->createMock(LegacyContainer::class);
        $container->expects(self::once())
            ->method('get')
            ->with(GorgoneServiceInterface::class)
            ->willReturn($gorgoneService);

        return new LegacyGorgoneNodesSynchronizer($container);
    }
}
