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
use Centreon\Infrastructure\Service\CentreonDBManagerService;
use Centreon\ServiceProvider as CentreonServiceProvider;
use CentreonLegacy\Core\Cache\CacheClearer;
use CentreonLegacy\ServiceProvider as LegacyServiceProvider;
use CentreonModule\Domain\Repository\ModulesInformationsRepository;
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

    public function testAModuleIsInstalledWhenItHasARowInModulesInformations(): void
    {
        $modulesInformations = $this->createMock(ModulesInformationsRepository::class);
        $modulesInformations->method('findIdByName')->willReturnMap([['centreon-open-tickets', 3], ['centreon-bam-server', null]]);
        $dbManager = $this->createMock(CentreonDBManagerService::class);
        $dbManager->method('getRepository')->with(ModulesInformationsRepository::class)->willReturn($modulesInformations);
        $container = new Container();
        $container[CentreonServiceProvider::CENTREON_DB_MANAGER] = $dbManager;
        $repository = new LegacyModuleRepository($container);

        self::assertTrue($repository->isInstalled('centreon-open-tickets'));
        self::assertFalse($repository->isInstalled('centreon-bam-server'));
    }
}
