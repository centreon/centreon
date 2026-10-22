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

use App\Upgrade\Application\CacheClearer;
use CentreonLegacy\Core\Cache\CacheClearer as LegacyCacheClearerService;
use CentreonLegacy\ServiceProvider as LegacyServiceProvider;
use Pimple\Container;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The one the module upgrades use: the upgrade fails as they do when the legacy kernel cache cannot be cleared.
 */
final readonly class LegacyCacheClearer implements CacheClearer
{
    public function __construct(
        #[Autowire(service: 'upgrade.legacy_container')]
        private Container $legacyContainer,
    ) {
    }

    public function clear(): void
    {
        /** @var LegacyCacheClearerService $cacheClearer */
        $cacheClearer = $this->legacyContainer[LegacyServiceProvider::CENTREON_LEGACY_CACHE_CLEARER];
        $cacheClearer->clear();
    }
}
