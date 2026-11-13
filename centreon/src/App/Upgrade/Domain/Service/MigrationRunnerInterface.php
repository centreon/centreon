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

namespace App\Upgrade\Domain\Service;

use App\Upgrade\Domain\Exception\MigrationFailedException;
use App\Upgrade\Domain\Exception\MigrationSetRefusedException;
use App\Upgrade\Domain\Exception\RollbackPlanChangedException;
use App\Upgrade\Domain\Exception\RollbackTargetNotFoundException;
use App\Upgrade\Domain\Model\MigrationOutcome;
use App\Upgrade\Domain\Model\MigrationSet;
use App\Upgrade\Domain\Model\MigrationSetStatus\MigrationSetStatus;

interface MigrationRunnerInterface
{
    /**
     * Stops at the first failure.
     *
     * @throws MigrationSetRefusedException when the set cannot be read or breaks the conventions
     * @throws MigrationFailedException when a migration fails or is skipped
     * @throws \LogicException
     *
     * @return MigrationOutcome the executed versions, and the warnings of their migrations
     */
    public function migrate(MigrationSet $set): MigrationOutcome;

    /**
     * Read-only: the executed migrations versioned after the target, which stays applied ("first" reverts them all).
     *
     * @throws MigrationSetRefusedException
     * @throws RollbackTargetNotFoundException
     * @throws \LogicException
     *
     * @return list<string> the versions to revert, in the order they would be reverted
     */
    public function planRollback(MigrationSet $set, string $versionOrAlias): array;

    /**
     * Reverts the migrations planRollback() listed, refusing if they changed meanwhile.
     *
     * @param list<string> $expected the versions planRollback() returned, as confirmed
     *
     * @throws MigrationSetRefusedException
     * @throws RollbackPlanChangedException when the migrations to revert are not the expected ones
     * @throws MigrationFailedException when a migration fails or is skipped
     * @throws RollbackTargetNotFoundException
     * @throws \LogicException
     *
     * @return MigrationOutcome the reverted versions, in the order they are reverted, and the warnings of their migrations
     */
    public function rollback(MigrationSet $set, string $versionOrAlias, array $expected): MigrationOutcome;

    /**
     * Read-only; refused like migrate().
     *
     * @throws MigrationSetRefusedException
     * @throws \LogicException
     */
    public function status(MigrationSet $set): MigrationSetStatus;

    /**
     * @return list<MigrationSet> the Centreon Web sets in execution order, then every module set not ignored
     */
    public function sets(): array;
}
