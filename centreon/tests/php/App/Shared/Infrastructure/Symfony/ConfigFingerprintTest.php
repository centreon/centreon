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

final class ConfigFingerprintTest extends TestCase
{
    use TemporaryConfigDirectory;
    private const FIXED_MTIME = 1700000000;

    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = $this->createTemporaryDirectory(
            'config/routes',
            'config/packages',
            'config.new/routes',
            'config.new/packages',
            'config.new/services'
        );
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tmpDir);
    }

    public function testFingerprintIsAShortHexadecimalDigestForAnEmptyConfigDir(): void
    {
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}$/', ConfigFingerprint::ofLegacyConfigDir($this->legacyConfigDir()));
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}$/', ConfigFingerprint::ofSharedConfigDir($this->sharedConfigDir()));
    }

    public function testFingerprintIsStableWhileNothingChanges(): void
    {
        $this->writeConfigFile('config/routes/a.yaml', "resource: a\n");
        $this->writeConfigFile('config/routes/z.yaml', "resource: z\n");

        self::assertSame(
            ConfigFingerprint::ofLegacyConfigDir($this->legacyConfigDir()),
            ConfigFingerprint::ofLegacyConfigDir($this->legacyConfigDir())
        );
    }

    public function testFingerprintChangesWhenAFileIsAdded(): void
    {
        $this->writeConfigFile('config/routes/a.yaml', "resource: a\n");
        $before = ConfigFingerprint::ofLegacyConfigDir($this->legacyConfigDir());

        $this->writeConfigFile('config/routes/b.yaml', "resource: b\n");

        self::assertNotSame($before, ConfigFingerprint::ofLegacyConfigDir($this->legacyConfigDir()));
    }

    public function testFingerprintChangesWhenAFileIsRemoved(): void
    {
        $this->writeConfigFile('config/routes/a.yaml', "resource: a\n");
        $this->writeConfigFile('config/routes/b.yaml', "resource: b\n");
        $before = ConfigFingerprint::ofLegacyConfigDir($this->legacyConfigDir());

        unlink($this->tmpDir . '/config/routes/b.yaml');

        self::assertNotSame($before, ConfigFingerprint::ofLegacyConfigDir($this->legacyConfigDir()));
    }

    /**
     * Both contents are the same length, so only the modification time can tell them apart.
     */
    public function testFingerprintChangesWhenContentChangesWithoutSizeChange(): void
    {
        $this->writeConfigFile('config/packages/framework.yaml', "framework:\n    secret: aaa\n");
        $before = ConfigFingerprint::ofLegacyConfigDir($this->legacyConfigDir());

        $this->writeConfigFile('config/packages/framework.yaml', "framework:\n    secret: bbb\n", self::FIXED_MTIME + 60);

        self::assertNotSame($before, ConfigFingerprint::ofLegacyConfigDir($this->legacyConfigDir()));
    }

    /**
     * The modification time is preserved, as `cp -p` or `rsync -a` would, so only the file
     * size can tell both contents apart.
     */
    public function testFingerprintChangesWhenSizeChangesWithoutMtimeChange(): void
    {
        $this->writeConfigFile('config/packages/framework.yaml', "framework:\n    secret: aaa\n");
        $before = ConfigFingerprint::ofLegacyConfigDir($this->legacyConfigDir());

        $this->writeConfigFile('config/packages/framework.yaml', "framework:\n    secret: aaa_longer\n");

        self::assertNotSame($before, ConfigFingerprint::ofLegacyConfigDir($this->legacyConfigDir()));
    }

    public function testLegacyFingerprintCoversNestedDirectoriesAndBundles(): void
    {
        $this->writeConfigFile('config/routes/a.yaml', "resource: a\n");
        $fingerprints = [ConfigFingerprint::ofLegacyConfigDir($this->legacyConfigDir())];

        $this->writeConfigFile('config/routes/Centreon/module.yaml', "resource: module\n");
        $fingerprints[] = ConfigFingerprint::ofLegacyConfigDir($this->legacyConfigDir());

        $this->writeConfigFile('config/packages/prod/cache.yaml', "framework:\n    cache: ~\n");
        $fingerprints[] = ConfigFingerprint::ofLegacyConfigDir($this->legacyConfigDir());

        $this->writeConfigFile('config/bundles.php', "<?php\n\nreturn [];\n");
        $fingerprints[] = ConfigFingerprint::ofLegacyConfigDir($this->legacyConfigDir());

        self::assertSame($fingerprints, array_unique($fingerprints));
    }

    public function testSharedFingerprintCoversNestedDirectoriesServicesAndBundles(): void
    {
        $this->writeConfigFile('config.new/routes/a.yaml', "resource: a\n");
        $fingerprints = [ConfigFingerprint::ofSharedConfigDir($this->sharedConfigDir())];

        $this->writeConfigFile('config.new/packages/prod/cache.yaml', "framework:\n    cache: ~\n");
        $fingerprints[] = ConfigFingerprint::ofSharedConfigDir($this->sharedConfigDir());

        $this->writeConfigFile('config.new/services/handlers.php', "<?php\n\nreturn static function (): void {};\n");
        $fingerprints[] = ConfigFingerprint::ofSharedConfigDir($this->sharedConfigDir());

        $this->writeConfigFile('config.new/services/prod/handlers.php', "<?php\n\nreturn static function (): void {};\n");
        $fingerprints[] = ConfigFingerprint::ofSharedConfigDir($this->sharedConfigDir());

        $this->writeConfigFile('config.new/bundles.php', "<?php\n\nreturn [];\n");
        $fingerprints[] = ConfigFingerprint::ofSharedConfigDir($this->sharedConfigDir());

        self::assertSame($fingerprints, array_unique($fingerprints));
    }

    /**
     * The legacy kernel imports yaml files only, so a module dropping a php file next to them
     * must not be part of its fingerprint.
     */
    public function testLegacyFingerprintIgnoresFilesThatAreNotImported(): void
    {
        $this->writeConfigFile('config/routes/a.yaml', "resource: a\n");
        $before = ConfigFingerprint::ofLegacyConfigDir($this->legacyConfigDir());

        $this->writeConfigFile('config/routes/a.yaml.bak', "resource: a\n");
        $this->writeConfigFile('config/packages/notes.md', "# notes\n");

        self::assertSame($before, ConfigFingerprint::ofLegacyConfigDir($this->legacyConfigDir()));
    }

    public function testLegacyAndSharedFingerprintsAreComputedFromTheirOwnDirectory(): void
    {
        $this->writeConfigFile('config/routes/a.yaml', "resource: a\n");
        $legacyBefore = ConfigFingerprint::ofLegacyConfigDir($this->legacyConfigDir());
        $sharedBefore = ConfigFingerprint::ofSharedConfigDir($this->sharedConfigDir());

        $this->writeConfigFile('config.new/routes/a.yaml', "resource: a\n");

        self::assertSame($legacyBefore, ConfigFingerprint::ofLegacyConfigDir($this->legacyConfigDir()));
        self::assertNotSame($sharedBefore, ConfigFingerprint::ofSharedConfigDir($this->sharedConfigDir()));
    }

    private function legacyConfigDir(): string
    {
        return $this->tmpDir . '/config';
    }

    private function sharedConfigDir(): string
    {
        return $this->tmpDir . '/config.new';
    }

    private function writeConfigFile(string $relativePath, string $content, int $mtime = self::FIXED_MTIME): void
    {
        $path = $this->tmpDir . '/' . $relativePath;
        $directory = \dirname($path);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($path, $content);
        touch($path, $mtime);
    }
}
