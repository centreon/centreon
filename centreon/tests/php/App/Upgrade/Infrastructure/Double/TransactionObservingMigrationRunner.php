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

namespace Tests\App\Upgrade\Infrastructure\Double;

use App\Upgrade\Domain\Model\MigrationOutcome;
use App\Upgrade\Domain\Model\MigrationSet;
use App\Upgrade\Domain\Model\MigrationSetStatus\MigrationSetStatus;
use App\Upgrade\Domain\Service\MigrationRunnerInterface;
use Doctrine\DBAL\Connection;

/**
 * Records the transaction nesting level of a connection each time it migrates or rolls back.
 */
final class TransactionObservingMigrationRunner implements MigrationRunnerInterface
{
    /** @var list<int> */
    public array $nestingLevels = [];

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function migrate(MigrationSet $set): MigrationOutcome
    {
        $this->nestingLevels[] = $this->connection->getTransactionNestingLevel();

        return new MigrationOutcome([]);
    }

    public function planRollback(MigrationSet $set, string $versionOrAlias): array
    {
        return [];
    }

    public function rollback(MigrationSet $set, string $versionOrAlias, array $expected): MigrationOutcome
    {
        $this->nestingLevels[] = $this->connection->getTransactionNestingLevel();

        return new MigrationOutcome([]);
    }

    public function status(MigrationSet $set): MigrationSetStatus
    {
        return MigrationSetStatus::read($set, [], []);
    }

    public function sets(): array
    {
        return [];
    }
}
