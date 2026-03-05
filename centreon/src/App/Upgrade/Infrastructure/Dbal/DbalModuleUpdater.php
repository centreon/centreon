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

namespace App\Upgrade\Infrastructure\Dbal;

use App\Upgrade\Application\ModuleUpdater;
use Psr\Log\LoggerInterface;

/**
 * @todo Implement clean module/widget update logic using DBAL.
 *       This requires a full analysis of what CentreonModuleService::update() does
 *       and rewriting it without any legacy dependency.
 *       Tracked in the upgrade migration plan.
 */
final readonly class DbalModuleUpdater implements ModuleUpdater
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function updateModules(): void
    {
        $this->logger->warning('Module update not yet implemented in App\Upgrade — skipping.');
    }

    public function updateWidgets(): void
    {
        $this->logger->warning('Widget update not yet implemented in App\Upgrade — skipping.');
    }
}
