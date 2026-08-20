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

use PHPUnit\Framework\TestCase;
use Tests\App\Double\FakeKernel;

final class KernelConfigFingerprintTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/centreon-kernel-fp-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir . '/config/routes', 0755, true);
        mkdir($this->tmpDir . '/config/packages', 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tmpDir);
    }

    public function testCacheDirEndsWithHexFingerprint(): void
    {
        file_put_contents($this->tmpDir . '/config/routes/test.yaml', "resource: test\n");

        $kernel = new FakeKernel($this->tmpDir);
        $cacheDir = $kernel->getCacheDir();

        self::assertMatchesRegularExpression('#/[0-9a-f]{8}$#', $cacheDir);
    }

    public function testFingerprintIsMemoized(): void
    {
        file_put_contents($this->tmpDir . '/config/routes/test.yaml', "resource: test\n");

        $kernel = new FakeKernel($this->tmpDir);

        self::assertSame($kernel->getCacheDir(), $kernel->getCacheDir());
    }

    public function testFingerprintChangesWhenFileAdded(): void
    {
        file_put_contents($this->tmpDir . '/config/routes/a.yaml', "resource: a\n");
        $kernel1 = new FakeKernel($this->tmpDir);
        $fingerprint1 = $kernel1->getCacheDir();

        file_put_contents($this->tmpDir . '/config/routes/b.yaml', "resource: b\n");
        $kernel2 = new FakeKernel($this->tmpDir);
        $fingerprint2 = $kernel2->getCacheDir();

        self::assertNotSame($fingerprint1, $fingerprint2);
    }

    public function testFingerprintChangesWhenFileContentChanges(): void
    {
        $file = $this->tmpDir . '/config/packages/framework.yaml';
        file_put_contents($file, "framework:\n    secret: aaa\n");
        $kernel1 = new FakeKernel($this->tmpDir);
        $fingerprint1 = $kernel1->getCacheDir();

        sleep(1);
        file_put_contents($file, "framework:\n    secret: bbb_extra\n");
        $kernel2 = new FakeKernel($this->tmpDir);
        $fingerprint2 = $kernel2->getCacheDir();

        self::assertNotSame($fingerprint1, $fingerprint2);
    }

    public function testFingerprintChangesWhenFileRemoved(): void
    {
        file_put_contents($this->tmpDir . '/config/routes/a.yaml', "resource: a\n");
        file_put_contents($this->tmpDir . '/config/routes/b.yaml', "resource: b\n");
        $kernel1 = new FakeKernel($this->tmpDir);
        $fingerprint1 = $kernel1->getCacheDir();

        unlink($this->tmpDir . '/config/routes/b.yaml');
        $kernel2 = new FakeKernel($this->tmpDir);
        $fingerprint2 = $kernel2->getCacheDir();

        self::assertNotSame($fingerprint1, $fingerprint2);
    }

    public function testFingerprintWithEmptyConfigDir(): void
    {
        $kernel = new FakeKernel($this->tmpDir);
        $cacheDir = $kernel->getCacheDir();

        self::assertMatchesRegularExpression('#/[0-9a-f]{8}$#', $cacheDir);
    }

    public function testFingerprintIncludesBundlesPhp(): void
    {
        file_put_contents($this->tmpDir . '/config/routes/a.yaml', "resource: a\n");
        $kernel1 = new FakeKernel($this->tmpDir);
        $fingerprint1 = $kernel1->getCacheDir();

        file_put_contents($this->tmpDir . '/config/bundles.php', "<?php\nreturn [];\n");
        $kernel2 = new FakeKernel($this->tmpDir);
        $fingerprint2 = $kernel2->getCacheDir();

        self::assertNotSame($fingerprint1, $fingerprint2);
    }

    public function testFingerprintIncludesSubdirectoryFiles(): void
    {
        file_put_contents($this->tmpDir . '/config/routes/a.yaml', "resource: a\n");
        $kernel1 = new FakeKernel($this->tmpDir);
        $fingerprint1 = $kernel1->getCacheDir();

        mkdir($this->tmpDir . '/config/packages/prod', 0755, true);
        file_put_contents($this->tmpDir . '/config/packages/prod/cache.yaml', "framework:\n    cache: true\n");
        $kernel2 = new FakeKernel($this->tmpDir);
        $fingerprint2 = $kernel2->getCacheDir();

        self::assertNotSame($fingerprint1, $fingerprint2);
    }

    public function testFingerprintIsDeterministicRegardlessOfFileOrder(): void
    {
        file_put_contents($this->tmpDir . '/config/routes/z.yaml', "resource: z\n");
        file_put_contents($this->tmpDir . '/config/routes/a.yaml', "resource: a\n");
        $kernel1 = new FakeKernel($this->tmpDir);

        $kernel2 = new FakeKernel($this->tmpDir);

        self::assertSame($kernel1->getCacheDir(), $kernel2->getCacheDir());
    }

    private function removeDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            assert($item instanceof \SplFileInfo);
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($path);
    }
}
