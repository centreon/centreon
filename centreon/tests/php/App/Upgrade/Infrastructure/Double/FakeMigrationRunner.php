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

use App\Upgrade\Domain\Exception\MigrationFailedException;
use App\Upgrade\Domain\Model\MigrationOutcome;
use App\Upgrade\Domain\Model\MigrationSet;
use App\Upgrade\Domain\Model\MigrationSetStatus\MigrationSetStatus;
use App\Upgrade\Domain\Service\MigrationRunnerInterface;

final class FakeMigrationRunner implements MigrationRunnerInterface
{
    /** @var list<string> names of the sets whose migration, rollback plan, rollback or status fails */
    public array $failingSets = [];

    /** @var list<MigrationSet> */
    public array $sets = [];

    /** Thrown by every migrate() and rollback() call, when set. */
    public ?\Throwable $failure = null;

    /** @var array<string, list<string>> what the migrations of each set warn of, by set name */
    public array $warnings = [];

    /** Thrown by every planRollback() call, when set. */
    public ?\Throwable $planFailure = null;

    /** @var list<list<string>> the expected migrations each rollback was given */
    public array $confirmedPlans = [];

    /** @var list<MigrationSet> the sets whose status was computed */
    public array $statusRequests = [];

    /**
     * @param \ArrayObject<int, string> $journal ordered calls, shareable with other doubles to assert sequencing
     */
    public function __construct(
        public \ArrayObject $journal = new \ArrayObject(),
    ) {
    }

    public function migrate(MigrationSet $set): MigrationOutcome
    {
        $this->journal[] = 'migrate:' . $set->name;
        if ($this->failure instanceof \Throwable) {
            throw $this->failure;
        }

        if (in_array($set->name, $this->failingSets, true)) {
            throw MigrationFailedException::failed($set, $set->name . '\\Version1', new \RuntimeException(sprintf('Migration of "%s" failed', $set->name)));
        }

        return new MigrationOutcome([$set->name . '\Version1'], $this->warnings[$set->name] ?? []);
    }

    public function planRollback(MigrationSet $set, string $versionOrAlias): array
    {
        $this->journal[] = 'plan-rollback:' . $set->name . ':' . $versionOrAlias;
        if ($this->planFailure instanceof \Throwable) {
            throw $this->planFailure;
        }

        if (in_array($set->name, $this->failingSets, true)) {
            throw new \RuntimeException(sprintf('Rollback plan of "%s" failed', $set->name));
        }

        return [$set->name . '\Version1'];
    }

    public function rollback(MigrationSet $set, string $versionOrAlias, array $expected): MigrationOutcome
    {
        $this->journal[] = 'rollback:' . $set->name . ':' . $versionOrAlias;
        $this->confirmedPlans[] = $expected;
        if ($this->failure instanceof \Throwable) {
            throw $this->failure;
        }

        if (in_array($set->name, $this->failingSets, true)) {
            throw MigrationFailedException::failed($set, $set->name . '\\Version1', new \RuntimeException(sprintf('Rollback of "%s" failed', $set->name)));
        }

        return new MigrationOutcome([$set->name . '\Version1'], $this->warnings[$set->name] ?? []);
    }

    public function status(MigrationSet $set): MigrationSetStatus
    {
        $this->statusRequests[] = $set;

        if (in_array($set->name, $this->failingSets, true)) {
            // As a migration file that does not parse, which is not even an Exception.
            throw new \ParseError(sprintf('Status of "%s" failed', $set->name));
        }

        return MigrationSetStatus::read($set, [], [$set->name . '\Version1']);
    }

    public function sets(): array
    {
        return $this->sets;
    }
}
