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

namespace App\Upgrade\Application;

interface MigrationRunner
{
    /**
     * Stops at the first failure.
     *
     * @throws \RuntimeException when the set is unknown or has executed migrations that are no longer available
     *
     * @return list<string> the executed versions
     */
    public function migrate(string $set): array;

    /**
     * Reverts the migrations executed after the given version (kept applied), or alias ("prev", "first").
     *
     * @throws \RuntimeException when the set is unknown or the target is ahead of the current version
     *
     * @return list<string> the reverted versions
     */
    public function rollback(string $set, string $versionOrAlias): array;

    /**
     * @param string|null $set null to get the status of every known set
     *
     * @return list<MigrationSetStatus>
     */
    public function status(?string $set = null): array;
}
