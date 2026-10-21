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

namespace Tests\App\Upgrade\Infrastructure;

use App\Upgrade\Application\CacheClearer;
use App\Upgrade\Domain\MigrationSet;
use App\Upgrade\Domain\Repository\ModuleRepository;
use App\Upgrade\Infrastructure\Doctrine\MigrationSetRegistry;
use App\Upgrade\Infrastructure\Legacy\LegacyCacheClearer;
use App\Upgrade\Infrastructure\Legacy\LegacyModuleRepository;
use Centreon\ServiceProvider as CentreonServiceProvider;
use CentreonModule\ServiceProvider;
use Pimple\Container;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The platform update runs from the new kernel, which reaches the legacy module engine.
 */
final class UpgradeWiringTest extends KernelTestCase
{
    public function testTheModulesAreUpgradedWithTheExtensionManagerEngine(): void
    {
        self::assertInstanceOf(LegacyModuleRepository::class, self::getContainer()->get(ModuleRepository::class));
        self::assertInstanceOf(LegacyCacheClearer::class, self::getContainer()->get(CacheClearer::class));

        // Its services need the legacy configuration, which bin/console.new loads: see MigrationLauncherTest.
        $legacyContainer = self::getContainer()->get('upgrade.legacy_container');
        self::assertInstanceOf(Container::class, $legacyContainer);
        self::assertTrue($legacyContainer->offsetExists(ServiceProvider::CENTREON_MODULE));
        self::assertTrue($legacyContainer->offsetExists(CentreonServiceProvider::CENTREON_DB_MANAGER), 'LegacyModuleRepository::isInstalled reads modules_informations through it');
    }

    public function testThePlatformVersionIsARelease(): void
    {
        $version = self::getContainer()->getParameter('centreon.version');
        self::assertIsString($version);
        self::assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $version);
    }

    public function testTheCoreMigrationsAreFound(): void
    {
        /** @var MigrationSetRegistry $registry */
        $registry = self::getContainer()->get(MigrationSetRegistry::class);

        // A missing directory is a set without migration: they would be skipped silently.
        foreach (MigrationSet::coreSets() as $set) {
            self::assertDirectoryExists($registry->configuration($set)->directory);
        }
    }
}
