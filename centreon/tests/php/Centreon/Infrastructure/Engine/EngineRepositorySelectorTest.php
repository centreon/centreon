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

namespace Tests\Centreon\Infrastructure\Engine;

use Centreon\Domain\Gorgone\GorgoneTransport;
use Centreon\Domain\Gorgone\Interfaces\CommandRepositoryInterface;
use Centreon\Infrastructure\Engine\EngineRepositoryFile;
use Centreon\Infrastructure\Engine\EngineRepositoryGorgone;
use Centreon\Infrastructure\Engine\EngineRepositorySelector;
use PHPUnit\Framework\TestCase;

class EngineRepositorySelectorTest extends TestCase
{
    private const COMMAND = 'EXTERNALCMD:1:[123] SCHEDULE_FORCED_HOST_CHECK;srv-01;123';

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

    private function selector(bool $useGorgone, CommandRepositoryInterface $commandRepository): EngineRepositorySelector
    {
        $transport = $this->createMock(GorgoneTransport::class);
        $transport->method('useGorgone')->willReturn($useGorgone);

        return new EngineRepositorySelector(
            $transport,
            new EngineRepositoryFile($this->centcoreDir),
            new EngineRepositoryGorgone($commandRepository),
        );
    }

    public function testRoutesToCentcoreFileWhenTransportIsLegacy(): void
    {
        $commandRepository = $this->createMock(CommandRepositoryInterface::class);
        $commandRepository->expects($this->never())->method('send');

        $this->selector(false, $commandRepository)->sendExternalCommand(self::COMMAND);

        $files = glob($this->centcoreDir . '/*.cmd') ?: [];
        $this->assertCount(1, $files, 'a centcore command file should have been written');
        $this->assertSame(self::COMMAND . "\n", file_get_contents($files[0]));
    }

    public function testRoutesToGorgoneWhenTransportIsGorgone(): void
    {
        $commandRepository = $this->createMock(CommandRepositoryInterface::class);
        $commandRepository->expects($this->once())->method('send')->willReturn('token');

        $this->selector(true, $commandRepository)->sendExternalCommand(self::COMMAND);

        $this->assertSame([], glob($this->centcoreDir . '/*') ?: [], 'no centcore file should be written in Gorgone mode');
    }
}
