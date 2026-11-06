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

use App\Upgrade\Domain\MigrationSet;

interface MigrationRunner
{
    /**
     * Stops at the first failure.
     *
     * @throws \RuntimeException when the set has executed migrations that are no longer available
     *
     * @return list<string> the executed versions
     */
    public function migrate(MigrationSet $set): array;

    /**
     * Read-only: the migrations executed after the given version (kept applied), or alias ("prev", "first").
     *
     * @throws \RuntimeException when the target is unknown or not executed
     *
     * @return list<string> the versions to revert, in the order they would be reverted
     */
    public function planRollback(MigrationSet $set, string $versionOrAlias): array;

    /**
     * Reverts the migrations planRollback() listed, refusing if they changed meanwhile.
     *
     * @param list<string> $expected the versions planRollback() returned, as confirmed
     *
     * @throws \RuntimeException when the target is unknown or not executed, or the migrations to revert are not the expected ones
     *
     * @return list<string> the reverted versions, in the order they are reverted
     */
    public function rollback(MigrationSet $set, string $versionOrAlias, array $expected): array;

    /**
     * Read-only, with the checks of migrate().
     *
     * @throws \RuntimeException when the set has executed migrations that are no longer available, or a migration breaks
     *                           the conventions
     */
    public function status(MigrationSet $set): MigrationSetStatus;

    /**
     * @return list<MigrationSet> the Centreon Web sets in execution order, then every module shipping migrations
     */
    public function sets(): array;
}
