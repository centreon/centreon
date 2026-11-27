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

namespace Tests\App\Upgrade\Infrastructure\Legacy;

use App\Upgrade\Infrastructure\Legacy\LegacyCacheClearer;
use CentreonLegacy\Core\Cache\CacheClearer;
use CentreonLegacy\ServiceProvider as LegacyServiceProvider;
use PHPUnit\Framework\TestCase;
use Pimple\Container;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

final class LegacyCacheClearerTest extends TestCase
{
    public function testClearsBothKernelCachesAsTheModuleUpgradesDo(): void
    {
        $cacheClearer = $this->cacheClearer(failing: false);

        (new LegacyCacheClearer($this->container($cacheClearer)))->clear();

        self::assertSame(['bin/console cache:clear', 'bin/console.new cache:clear'], $cacheClearer->commands);
    }

    public function testFailsWhenTheLegacyKernelCacheCannotBeCleared(): void
    {
        $this->expectException(ProcessFailedException::class);

        (new LegacyCacheClearer($this->container($this->cacheClearer(failing: true))))->clear();
    }

    private function container(CacheClearer $cacheClearer): Container
    {
        $container = new Container();
        $container[LegacyServiceProvider::CENTREON_LEGACY_CACHE_CLEARER] = $cacheClearer;

        return $container;
    }

    /**
     * @return CacheClearer&object{commands: list<string>}
     */
    private function cacheClearer(bool $failing): CacheClearer
    {
        return new class ($failing) extends CacheClearer {
            /** @var list<string> */
            public array $commands = [];

            public function __construct(private readonly bool $failing)
            {
            }

            protected function run(array $command): void
            {
                $this->commands[] = implode(' ', $command);
                if ($this->failing) {
                    $process = new Process(['false']);
                    $process->run();

                    throw new ProcessFailedException($process);
                }
            }
        };
    }
}
