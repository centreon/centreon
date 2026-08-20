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
 * Checks that both kernels key their cache directory on the fingerprint of the configuration
 * directory they actually import. The fingerprint rules themselves are covered by
 * {@see Shared\Infrastructure\Symfony\ConfigFingerprintTest}.
 */
final class KernelConfigFingerprintTest extends TestCase
{
    use TemporaryConfigDirectory;

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
        file_put_contents($this->tmpDir . '/config/routes/test.yaml', "resource: test\n");

        $kernel = new FakeKernel($this->tmpDir);

        self::assertMatchesRegularExpression('#/symfony/[0-9a-f]{8}$#', $kernel->getCacheDir());
    }

    public function testSharedCacheDirEndsWithHexFingerprint(): void
    {
        $kernel = new SharedKernel('test', false);

        self::assertMatchesRegularExpression('#^/var/cache/centreon/symfony\.new/[0-9a-f]{8}$#', $kernel->getCacheDir());
    }

    public function testLegacyCacheDirChangesWhenAConfigFileIsAdded(): void
    {
        file_put_contents($this->tmpDir . '/config/routes/a.yaml', "resource: a\n");
        $before = (new FakeKernel($this->tmpDir))->getCacheDir();

        file_put_contents($this->tmpDir . '/config/routes/b.yaml', "resource: b\n");

        self::assertNotSame($before, (new FakeKernel($this->tmpDir))->getCacheDir());
    }

    public function testFingerprintIsComputedOnlyOncePerKernel(): void
    {
        file_put_contents($this->tmpDir . '/config/routes/a.yaml', "resource: a\n");
        $kernel = new FakeKernel($this->tmpDir);
        $cacheDir = $kernel->getCacheDir();

        file_put_contents($this->tmpDir . '/config/routes/b.yaml', "resource: b\n");

        self::assertSame($cacheDir, $kernel->getCacheDir());
    }
}
