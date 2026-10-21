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

use App\Upgrade\Infrastructure\Legacy\LegacyModuleRepository;
use CentreonLegacy\Core\Cache\CacheClearer;
use CentreonLegacy\ServiceProvider as LegacyServiceProvider;
use CentreonModule\Infrastructure\Entity\Module;
use CentreonModule\Infrastructure\Service\CentreonModuleService;
use CentreonModule\ServiceProvider;
use PHPUnit\Framework\TestCase;
use Pimple\Container;

final class LegacyModuleRepositoryTest extends TestCase
{
    public function testUpdatesTheOutdatedModulesWithoutClearingTheCaches(): void
    {
        $cacheClearer = new class () extends CacheClearer {
            /** @var list<string> */
            public array $commands = [];

            protected function run(array $command): void
            {
                $this->commands[] = implode(' ', $command);
            }
        };
        $moduleService = $this->createMock(CentreonModuleService::class);
        // Each module upgrader asks for a clear: the platform update does it once, at its end.
        $moduleService->expects(self::once())->method('updateAll')->with('module')->willReturnCallback(
            static function () use ($cacheClearer): array {
                $cacheClearer->clear();

                return [];
            },
        );

        $container = new Container();
        $container[ServiceProvider::CENTREON_MODULE] = $moduleService;
        $container[LegacyServiceProvider::CENTREON_LEGACY_CACHE_CLEARER] = $cacheClearer;

        (new LegacyModuleRepository($container))->updateAll();

        self::assertSame([], $cacheClearer->commands);
    }

    public function testAModuleIsInstalledWhenTheExtensionManagerSaysSo(): void
    {
        $installed = new Module();
        $installed->setInstalled(true);

        $moduleService = $this->createMock(CentreonModuleService::class);
        $moduleService->method('getDetail')->willReturnMap([
            ['centreon-open-tickets', 'module', $installed],
            ['centreon-bam-server', 'module', new Module()],
            ['centreon-unknown', 'module', null],
        ]);
        $container = new Container();
        $container[ServiceProvider::CENTREON_MODULE] = $moduleService;
        $repository = new LegacyModuleRepository($container);

        self::assertTrue($repository->isInstalled('centreon-open-tickets'));
        self::assertFalse($repository->isInstalled('centreon-bam-server'));
        self::assertFalse($repository->isInstalled('centreon-unknown'));
    }
}
