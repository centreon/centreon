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

namespace Tests\App\Shared\Infrastructure\Symfony;

use App\Shared\Infrastructure\Symfony\ConfigFingerprint;
use PHPUnit\Framework\TestCase;
use Tests\App\Double\TemporaryConfigDirectory;

/**
 * Covers the file set the shared kernel imports from config.new. The legacy kernel keeps its
 * own copy of this computation, exercised by {@see \Tests\App\KernelConfigFingerprintTest}.
 */
final class ConfigFingerprintTest extends TestCase
{
    use TemporaryConfigDirectory;
    private const MTIME = 1700000000;

    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = $this->createTemporaryDirectory('routes', 'packages', 'services');
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tmpDir);
    }

    public function testFingerprintIsAShortHexadecimalDigestForAnEmptyConfigDir(): void
    {
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}$/', ConfigFingerprint::ofConfigDir($this->tmpDir));
    }

    public function testFingerprintIsStableWhileNothingChanges(): void
    {
        $this->writeFile($this->tmpDir . '/routes/a.yaml', "resource: a\n");
        $this->writeFile($this->tmpDir . '/routes/z.yaml', "resource: z\n");

        self::assertSame(
            ConfigFingerprint::ofConfigDir($this->tmpDir),
            ConfigFingerprint::ofConfigDir($this->tmpDir)
        );
    }

    public function testFingerprintChangesWhenAFileIsAdded(): void
    {
        $this->writeFile($this->tmpDir . '/routes/a.yaml', "resource: a\n");
        $before = ConfigFingerprint::ofConfigDir($this->tmpDir);

        $this->writeFile($this->tmpDir . '/routes/b.yaml', "resource: b\n");

        self::assertNotSame($before, ConfigFingerprint::ofConfigDir($this->tmpDir));
    }

    public function testFingerprintChangesWhenAFileIsRemoved(): void
    {
        $this->writeFile($this->tmpDir . '/routes/a.yaml', "resource: a\n");
        $this->writeFile($this->tmpDir . '/routes/b.yaml', "resource: b\n");
        $before = ConfigFingerprint::ofConfigDir($this->tmpDir);

        unlink($this->tmpDir . '/routes/b.yaml');

        self::assertNotSame($before, ConfigFingerprint::ofConfigDir($this->tmpDir));
    }

    /**
     * Both contents have the same length, so only the modification time can tell them apart.
     */
    public function testFingerprintChangesWhenContentChangesWithoutSizeChange(): void
    {
        $this->writeFile($this->tmpDir . '/packages/framework.yaml', "framework:\n    secret: aaa\n");
        $before = ConfigFingerprint::ofConfigDir($this->tmpDir);

        $this->writeFile($this->tmpDir . '/packages/framework.yaml', "framework:\n    secret: bbb\n", self::MTIME + 60);

        self::assertNotSame($before, ConfigFingerprint::ofConfigDir($this->tmpDir));
    }

    /**
     * The modification time is preserved, as `cp -p` or `rsync -a` would, so only the file
     * size can tell both contents apart.
     */
    public function testFingerprintChangesWhenSizeChangesWithoutMtimeChange(): void
    {
        $this->writeFile($this->tmpDir . '/packages/framework.yaml', "framework:\n    secret: aaa\n");
        $before = ConfigFingerprint::ofConfigDir($this->tmpDir);

        $this->writeFile($this->tmpDir . '/packages/framework.yaml', "framework:\n    secret: aaa_longer\n");

        self::assertNotSame($before, ConfigFingerprint::ofConfigDir($this->tmpDir));
    }

    public function testFingerprintCoversNestedDirectoriesServicesAndBundles(): void
    {
        $this->writeFile($this->tmpDir . '/routes/a.yaml', "resource: a\n");
        $fingerprints = [ConfigFingerprint::ofConfigDir($this->tmpDir)];

        $this->writeFile($this->tmpDir . '/packages/prod/cache.yaml', "framework:\n    cache: ~\n");
        $fingerprints[] = ConfigFingerprint::ofConfigDir($this->tmpDir);

        $this->writeFile($this->tmpDir . '/services/handlers.php', "<?php\n\nreturn static function (): void {};\n");
        $fingerprints[] = ConfigFingerprint::ofConfigDir($this->tmpDir);

        $this->writeFile($this->tmpDir . '/services/prod/handlers.php', "<?php\n\nreturn static function (): void {};\n");
        $fingerprints[] = ConfigFingerprint::ofConfigDir($this->tmpDir);

        $this->writeFile($this->tmpDir . '/bundles.php', "<?php\n\nreturn [];\n");
        $fingerprints[] = ConfigFingerprint::ofConfigDir($this->tmpDir);

        self::assertSame($fingerprints, array_unique($fingerprints));
    }

    public function testFingerprintIgnoresFilesThatAreNotImported(): void
    {
        $this->writeFile($this->tmpDir . '/services/handlers.php', "<?php\n\nreturn static function (): void {};\n");
        $before = ConfigFingerprint::ofConfigDir($this->tmpDir);

        $this->writeFile($this->tmpDir . '/services/handlers.php.bak', "<?php\n\nreturn static function (): void {};\n");
        $this->writeFile($this->tmpDir . '/packages/notes.md', "# notes\n");

        self::assertSame($before, ConfigFingerprint::ofConfigDir($this->tmpDir));
    }
}
