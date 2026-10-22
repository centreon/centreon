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
use CentreonModule\Infrastructure\Service\CentreonModuleService;
use CentreonModule\ServiceProvider;
use Doctrine\DBAL\DriverManager;
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

        (new LegacyModuleRepository($container, DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true])))->updateAll();

        self::assertSame([], $cacheClearer->commands);
    }

    public function testAModuleIsInstalledWhenItHasARowInModulesInformations(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $connection->executeStatement('CREATE TABLE modules_informations (id INTEGER PRIMARY KEY, name VARCHAR(255))');
        $connection->insert('modules_informations', ['name' => 'centreon-open-tickets']);

        $repository = new LegacyModuleRepository(new Container(), $connection);

        self::assertTrue($repository->isInstalled('centreon-open-tickets'));
        self::assertFalse($repository->isInstalled('centreon-bam-server'));
    }
}
