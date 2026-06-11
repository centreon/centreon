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

namespace Tests\Centreon\Infrastructure\MonitoringServer\Repository;

use Centreon\Domain\Gorgone\GorgoneTransport;
use Centreon\Domain\Gorgone\Interfaces\CommandRepositoryInterface;
use Centreon\Infrastructure\MonitoringServer\Repository\PollerCommandRepositoryFile;
use Centreon\Infrastructure\MonitoringServer\Repository\PollerCommandRepositoryGorgone;
use Centreon\Infrastructure\MonitoringServer\Repository\PollerCommandRepositorySelector;
use PHPUnit\Framework\TestCase;

class PollerCommandRepositorySelectorTest extends TestCase
{
    private string $centcoreDir;

    protected function setUp(): void
    {
        $this->centcoreDir = sys_get_temp_dir() . '/cs-centcore-' . uniqid('', true);
        mkdir($this->centcoreDir, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->centcoreDir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->centcoreDir);
    }

    private function selector(bool $useGorgone, CommandRepositoryInterface $commandRepository): PollerCommandRepositorySelector
    {
        $transport = $this->createMock(GorgoneTransport::class);
        $transport->method('useGorgone')->willReturn($useGorgone);

        return new PollerCommandRepositorySelector(
            $transport,
            new PollerCommandRepositoryFile($this->centcoreDir),
            new PollerCommandRepositoryGorgone($commandRepository),
        );
    }

    public function testRoutesToCentcoreFileWhenTransportIsLegacy(): void
    {
        $commandRepository = $this->createMock(CommandRepositoryInterface::class);
        $commandRepository->expects($this->never())->method('send');

        $this->selector(false, $commandRepository)->reloadEngine(5);

        $files = glob($this->centcoreDir . '/*-externalcommand.cmd') ?: [];
        $this->assertCount(1, $files, 'a centcore command file should have been written');
        $this->assertSame("RELOAD:5\n", file_get_contents($files[0]));
    }

    public function testRoutesToGorgoneWhenTransportIsGorgone(): void
    {
        $commandRepository = $this->createMock(CommandRepositoryInterface::class);
        $commandRepository->expects($this->once())->method('send')->willReturn('token');

        $this->selector(true, $commandRepository)->reloadEngine(5);

        $this->assertSame([], glob($this->centcoreDir . '/*') ?: [], 'no centcore file should be written in Gorgone mode');
    }

    public function testReloadBrokerRoutesToCentcoreFileWhenTransportIsLegacy(): void
    {
        $commandRepository = $this->createMock(CommandRepositoryInterface::class);
        $commandRepository->expects($this->never())->method('send');

        $this->selector(false, $commandRepository)->reloadBroker(5);

        $files = glob($this->centcoreDir . '/*-externalcommand.cmd') ?: [];
        $this->assertCount(1, $files, 'a centcore command file should have been written');
        $this->assertSame("RELOADBROKER:5\n", file_get_contents($files[0]));
    }

    public function testReloadBrokerRoutesToGorgoneWhenTransportIsGorgone(): void
    {
        $commandRepository = $this->createMock(CommandRepositoryInterface::class);
        $commandRepository->expects($this->once())->method('send')->willReturn('token');

        $this->selector(true, $commandRepository)->reloadBroker(5);

        $this->assertSame([], glob($this->centcoreDir . '/*') ?: [], 'no centcore file should be written in Gorgone mode');
    }
}
