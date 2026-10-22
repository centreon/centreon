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

namespace App\Upgrade\Infrastructure\Legacy;

use App\Upgrade\Domain\Repository\ModuleRepository;
use CentreonLegacy\Core\Cache\CacheClearer;
use CentreonLegacy\ServiceProvider as LegacyServiceProvider;
use CentreonModule\Infrastructure\Service\CentreonModuleService;
use CentreonModule\Infrastructure\Source\ModuleSource;
use CentreonModule\ServiceProvider;
use Doctrine\DBAL\Connection;
use Pimple\Container;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The Extension Manager engine, the one module upgrade scripts are written for.
 */
final readonly class LegacyModuleRepository implements ModuleRepository
{
    public function __construct(
        #[Autowire(service: 'upgrade.legacy_container')]
        private Container $legacyContainer,
        #[Autowire(service: 'doctrine.dbal.default_connection')]
        private Connection $connection,
    ) {
    }

    public function updateAll(): void
    {
        /** @var CentreonModuleService $moduleService */
        $moduleService = $this->legacyContainer[ServiceProvider::CENTREON_MODULE];
        /** @var CacheClearer $cacheClearer */
        $cacheClearer = $this->legacyContainer[LegacyServiceProvider::CENTREON_LEGACY_CACHE_CLEARER];

        // UpdateCommandHandler clears them once, at the end.
        $cacheClearer->suspended(static fn (): array => $moduleService->updateAll(ModuleSource::TYPE));
    }

    /**
     * Its row only: the legacy engine would load its conf.php and read its pending migrations.
     */
    public function isInstalled(string $module): bool
    {
        return $this->connection->fetchOne('SELECT 1 FROM `modules_informations` WHERE `name` = ?', [$module]) !== false;
    }
}
