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

namespace Tests\App\Upgrade\Infrastructure\FileSystem;

use App\Upgrade\Infrastructure\FileSystem\FileSystemUpdateRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Filesystem\Filesystem;

final class FileSystemUpdateRepositoryTest extends TestCase
{
    private string $tmpDir;

    private Filesystem $filesystem;

    private FileSystemUpdateRepository $repository;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->tmpDir = sys_get_temp_dir() . '/centreon-test-' . uniqid();
        $this->filesystem->mkdir($this->tmpDir . '/php');

        $this->repository = new FileSystemUpdateRepository(
            $this->tmpDir,
            $this->filesystem,
            new NullLogger(),
        );
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->tmpDir);
    }

    public function testReturnsUpdatesNewerThanCurrentVersionInOrder(): void
    {
        $this->createUpdateFile('24.10.1');
        $this->createUpdateFile('24.10.3');
        $this->createUpdateFile('24.10.2');

        $result = $this->repository->findOrderedAvailableUpdates('24.10.0');

        self::assertSame(['24.10.1', '24.10.2', '24.10.3'], $result);
    }

    public function testExcludesVersionsEqualOrOlderThanCurrent(): void
    {
        $this->createUpdateFile('24.10.0');
        $this->createUpdateFile('24.04.1');
        $this->createUpdateFile('24.10.1');

        $result = $this->repository->findOrderedAvailableUpdates('24.10.0');

        self::assertSame(['24.10.1'], $result);
    }

    public function testReturnsEmptyArrayWhenNoUpdatesAvailable(): void
    {
        $result = $this->repository->findOrderedAvailableUpdates('24.10.0');

        self::assertSame([], $result);
    }

    public function testReturnsEmptyArrayWhenPhpDirectoryDoesNotExist(): void
    {
        $this->filesystem->remove($this->tmpDir . '/php');

        $result = $this->repository->findOrderedAvailableUpdates('24.10.0');

        self::assertSame([], $result);
    }

    public function testIgnoresNonMatchingFiles(): void
    {
        $this->createUpdateFile('24.10.1');
        touch($this->tmpDir . '/php/README.md');
        touch($this->tmpDir . '/php/some-script.php');

        $result = $this->repository->findOrderedAvailableUpdates('24.10.0');

        self::assertSame(['24.10.1'], $result);
    }

    private function createUpdateFile(string $version): void
    {
        touch($this->tmpDir . '/php/Update-' . $version . '.php');
    }
}
