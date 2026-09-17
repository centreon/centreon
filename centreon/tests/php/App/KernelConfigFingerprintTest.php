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

namespace Tests\App;

use App\Shared\Infrastructure\Symfony\Kernel as SharedKernel;
use PHPUnit\Framework\TestCase;
use Tests\App\Double\FakeKernel;
use Tests\App\Double\TemporaryConfigDirectory;

/**
 * Covers the file set the legacy kernel imports from its config directory, and the fact that
 * both kernels key their cache directory on it. The shared kernel is final and resolves its
 * project directory from its own location, so the file set it imports is covered by
 * {@see Shared\Infrastructure\Symfony\ConfigFingerprintTest}.
 */
final class KernelConfigFingerprintTest extends TestCase
{
    use TemporaryConfigDirectory;
    private const MTIME = 1700000000;

    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = $this->createTemporaryDirectory('config/routes', 'config/packages');
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tmpDir);
    }

    /**
     * The cache directory prefix comes from _CENTREON_CACHEDIR_, which the test bootstrap
     * overrides, so only the /symfony/<fingerprint> suffix is asserted here.
     */
    public function testLegacyCacheDirEndsWithHexFingerprint(): void
    {
        $this->writeFile($this->tmpDir . '/config/routes/test.yaml', "resource: test\n");

        self::assertMatchesRegularExpression('#/symfony/[0-9a-f]{8}$#', (new FakeKernel($this->tmpDir))->getCacheDir());
    }

    public function testSharedCacheDirEndsWithHexFingerprint(): void
    {
        $kernel = new SharedKernel('test', false);

        self::assertMatchesRegularExpression('#^/var/cache/centreon/symfony\.new/[0-9a-f]{8}$#', $kernel->getCacheDir());
    }

    public function testFingerprintIsComputedOnlyOncePerKernel(): void
    {
        $this->writeFile($this->tmpDir . '/config/routes/a.yaml', "resource: a\n");
        $kernel = new FakeKernel($this->tmpDir);
        $cacheDir = $kernel->getCacheDir();

        $this->writeFile($this->tmpDir . '/config/routes/b.yaml', "resource: b\n");

        self::assertSame($cacheDir, $kernel->getCacheDir());
    }

    public function testFingerprintIsStableWhileNothingChanges(): void
    {
        $this->writeFile($this->tmpDir . '/config/routes/z.yaml', "resource: z\n");
        $this->writeFile($this->tmpDir . '/config/routes/a.yaml', "resource: a\n");

        self::assertSame(
            (new FakeKernel($this->tmpDir))->getCacheDir(),
            (new FakeKernel($this->tmpDir))->getCacheDir()
        );
    }

    public function testFingerprintChangesWhenAFileIsAdded(): void
    {
        $this->writeFile($this->tmpDir . '/config/routes/a.yaml', "resource: a\n");
        $before = (new FakeKernel($this->tmpDir))->getCacheDir();

        $this->writeFile($this->tmpDir . '/config/routes/b.yaml', "resource: b\n");

        self::assertNotSame($before, (new FakeKernel($this->tmpDir))->getCacheDir());
    }

    public function testFingerprintChangesWhenAFileIsRemoved(): void
    {
        $this->writeFile($this->tmpDir . '/config/routes/a.yaml', "resource: a\n");
        $this->writeFile($this->tmpDir . '/config/routes/b.yaml', "resource: b\n");
        $before = (new FakeKernel($this->tmpDir))->getCacheDir();

        unlink($this->tmpDir . '/config/routes/b.yaml');

        self::assertNotSame($before, (new FakeKernel($this->tmpDir))->getCacheDir());
    }

    /**
     * Both contents have the same length, so only the modification time can tell them apart.
     */
    public function testFingerprintChangesWhenContentChangesWithoutSizeChange(): void
    {
        $file = $this->tmpDir . '/config/packages/framework.yaml';
        $this->writeFile($file, "framework:\n    secret: aaa\n");
        $before = (new FakeKernel($this->tmpDir))->getCacheDir();

        $this->writeFile($file, "framework:\n    secret: bbb\n", self::MTIME + 60);

        self::assertNotSame($before, (new FakeKernel($this->tmpDir))->getCacheDir());
    }

    /**
     * The modification time is preserved, as `cp -p` or `rsync -a` would, so only the file
     * size can tell both contents apart.
     */
    public function testFingerprintChangesWhenSizeChangesWithoutMtimeChange(): void
    {
        $file = $this->tmpDir . '/config/packages/framework.yaml';
        $this->writeFile($file, "framework:\n    secret: aaa\n");
        $before = (new FakeKernel($this->tmpDir))->getCacheDir();

        $this->writeFile($file, "framework:\n    secret: aaa_longer\n");

        self::assertNotSame($before, (new FakeKernel($this->tmpDir))->getCacheDir());
    }

    public function testFingerprintCoversNestedDirectoriesAndBundles(): void
    {
        $this->writeFile($this->tmpDir . '/config/routes/a.yaml', "resource: a\n");
        $cacheDirs = [(new FakeKernel($this->tmpDir))->getCacheDir()];

        $this->writeFile($this->tmpDir . '/config/routes/Centreon/module.yaml', "resource: module\n");
        $cacheDirs[] = (new FakeKernel($this->tmpDir))->getCacheDir();

        $this->writeFile($this->tmpDir . '/config/packages/prod/cache.yaml', "framework:\n    cache: ~\n");
        $cacheDirs[] = (new FakeKernel($this->tmpDir))->getCacheDir();

        $this->writeFile($this->tmpDir . '/config/bundles.php', "<?php\n\nreturn [];\n");
        $cacheDirs[] = (new FakeKernel($this->tmpDir))->getCacheDir();

        self::assertSame($cacheDirs, array_unique($cacheDirs));
    }

    public function testFingerprintIgnoresFilesThatAreNotImported(): void
    {
        $this->writeFile($this->tmpDir . '/config/routes/a.yaml', "resource: a\n");
        $before = (new FakeKernel($this->tmpDir))->getCacheDir();

        $this->writeFile($this->tmpDir . '/config/routes/a.yaml.bak', "resource: a\n");
        $this->writeFile($this->tmpDir . '/config/packages/notes.md', "# notes\n");

        self::assertSame($before, (new FakeKernel($this->tmpDir))->getCacheDir());
    }
}
