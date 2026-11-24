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

namespace App\Upgrade\Domain\Repository;

interface ModuleRepository
{
    public function updateAll(): void;

    public function isInstalled(string $module): bool;

    /**
     * Whether an upgrade script directory newer than the installed version, and not newer than the code version, is still to run.
     */
    public function hasPendingLegacyUpgrade(string $module): bool;

    /**
     * @return list<string> the names of the installed modules
     */
    public function findInstalledModules(): array;

    /**
     * 0 when its migrations never ran.
     */
    public function countExecutedMigrations(string $module): int;
}
