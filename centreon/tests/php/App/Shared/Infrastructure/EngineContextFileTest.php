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

namespace Tests\App\Shared\Infrastructure;

use App\Shared\Infrastructure\EngineContextFile;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

final class EngineContextFileTest extends TestCase
{
    private string $root;

    private string $engineDirectory;

    private string $webDirectory;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/engine_context_' . bin2hex(random_bytes(6));
        $this->engineDirectory = $this->root . '/centreon-engine';
        $this->webDirectory = $this->root . '/centreon';
        mkdir($this->webDirectory, 0770, true);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->root);
    }

    public function testDefaultPathIsTheOneOfTheEnginePackage(): void
    {
        $file = new EngineContextFile('', '/etc/centreon', false);

        self::assertSame('/etc/centreon-engine/engine-context.json', $file->getPath());
        self::assertFalse($file->isCreatedOnDemand());
    }

    public function testDefaultPathIsKeptWithoutTheFeatureFlagEvenIfTheEngineDirectoryIsMissing(): void
    {
        self::assertSame($this->defaultPath(), $this->file(isLocalEngineOptional: false)->getPath());
    }

    public function testDefaultPathIsKeptWithTheFeatureFlagWhenTheEngineDirectoryExists(): void
    {
        mkdir($this->engineDirectory);

        self::assertSame($this->defaultPath(), $this->file(isLocalEngineOptional: true)->getPath());
    }

    public function testFallsBackToTheWebDirectoryWithTheFeatureFlagAndNoEngineDirectory(): void
    {
        $file = new EngineContextFile('', $this->webDirectory . '/', true, $this->defaultPath());

        self::assertSame($this->webDirectory . '/engine-context.json', $file->getPath());
        self::assertTrue($file->isCreatedOnDemand());
    }

    public function testConfiguredPathIsUsedAsIs(): void
    {
        $configured = $this->root . '/missing/custom.json';

        self::assertSame($configured, $this->file(false, $configured)->getPath());
        self::assertSame($configured, $this->file(true, $configured)->getPath());
    }

    public function testCreatesTheFileWithARestrictiveMode(): void
    {
        $previousUmask = umask(0);

        try {
            $this->file(isLocalEngineOptional: true)->write('{"app_secret":"a","salt":"b"}');
        } finally {
            umask($previousUmask);
        }

        $path = $this->webDirectory . '/engine-context.json';
        self::assertSame('{"app_secret":"a","salt":"b"}', file_get_contents($path));
        self::assertSame(0640, fileperms($path) & 0777);
    }

    public function testKeepsThePermissionsOfAnExistingFile(): void
    {
        mkdir($this->engineDirectory);
        touch($this->defaultPath());
        chmod($this->defaultPath(), 0660);

        foreach ([false, true] as $isLocalEngineOptional) {
            $this->file($isLocalEngineOptional)->write('content-' . (int) $isLocalEngineOptional);

            clearstatcache();
            self::assertSame('content-' . (int) $isLocalEngineOptional, file_get_contents($this->defaultPath()));
            self::assertSame(0660, fileperms($this->defaultPath()) & 0777);
        }
    }

    public function testFailsWhenTheFileCannotBeCreated(): void
    {
        $file = $this->file(true, $this->root . '/missing/engine-context.json');

        $this->expectException(IOException::class);
        $file->write('content');
    }

    private function defaultPath(): string
    {
        return $this->engineDirectory . '/engine-context.json';
    }

    private function file(bool $isLocalEngineOptional, string $configuredPath = ''): EngineContextFile
    {
        return new EngineContextFile($configuredPath, $this->webDirectory, $isLocalEngineOptional, $this->defaultPath());
    }
}
