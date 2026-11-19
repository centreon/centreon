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

namespace App\Upgrade\Infrastructure\Doctrine;

use App\Upgrade\Domain\Exception\MigrationFailedException;
use App\Upgrade\Domain\Exception\MigrationSetRefusedException;
use App\Upgrade\Domain\Exception\RollbackPlanChangedException;
use App\Upgrade\Domain\Exception\RollbackTargetNotFoundException;
use App\Upgrade\Domain\Model\MigrationOutcome;
use App\Upgrade\Domain\Model\MigrationSet;
use App\Upgrade\Domain\Model\MigrationSetStatus\MigrationSetStatus;
use App\Upgrade\Domain\Service\MigrationRunnerInterface;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Exception\NoMigrationsFoundWithCriteria;
use Doctrine\Migrations\Exception\NoMigrationsToExecute;
use Doctrine\Migrations\Exception\UnknownMigrationVersion;
use Doctrine\Migrations\Metadata\AvailableMigration;
use Doctrine\Migrations\Metadata\ExecutedMigration;
use Doctrine\Migrations\Metadata\MigrationPlanList;
use Doctrine\Migrations\MigratorConfiguration;
use Doctrine\Migrations\Version\Direction;
use Doctrine\Migrations\Version\Version;

final readonly class MigrationRunner implements MigrationRunnerInterface
{
    public function __construct(
        private MigrationSetRegistry $registry,
        private MigrationFactoryProvider $factories,
    ) {
    }

    public function migrate(MigrationSet $set): MigrationOutcome
    {
        $configuration = $this->registry->configuration($set);
        if (! $this->hasDirectory($set, $configuration)) {
            return new MigrationOutcome([]);
        }

        $factory = $this->factories->create($set, $configuration, $set->migrationStep(), initialize: true);

        try {
            $latest = $factory->getVersionAliasResolver()->resolveVersionAlias('latest');
            $executed = $this->execute($set, $factory, $factory->getMigrationPlanCalculator()->getPlanUntilVersion($latest));

            return new MigrationOutcome($executed, $this->warnings($factory));
        } finally {
            $this->factories->release($factory);
        }
    }

    public function planRollback(MigrationSet $set, string $versionOrAlias): array
    {
        $factory = $this->rollbackFactory($set, initialize: false);

        try {
            return $this->versionNames($this->toRevert($set, $factory, $versionOrAlias));
        } finally {
            $this->factories->release($factory);
        }
    }

    public function rollback(MigrationSet $set, string $versionOrAlias, array $expected): MigrationOutcome
    {
        $factory = $this->rollbackFactory($set, initialize: true);

        try {
            $toRevert = $this->toRevert($set, $factory, $versionOrAlias);
            $planned = $this->versionNames($toRevert);
            if ($planned !== $expected) {
                throw new RollbackPlanChangedException($set, $planned);
            }

            $reverted = $this->execute($set, $factory, $factory->getMigrationPlanCalculator()->getPlanForVersions($toRevert, Direction::DOWN));

            return new MigrationOutcome($reverted, $this->warnings($factory));
        } finally {
            $this->factories->release($factory);
        }
    }

    public function status(MigrationSet $set): MigrationSetStatus
    {
        $configuration = $this->registry->configuration($set);
        if (! $this->hasDirectory($set, $configuration)) {
            return MigrationSetStatus::read($set, [], []);
        }

        $factory = $this->factories->create($set, $configuration, $set->migrationStep(), initialize: false);

        try {
            return MigrationSetStatus::read(
                $set,
                $this->versions($factory->getMetadataStorage()->getExecutedMigrations()->getItems()),
                $this->versions($factory->getMigrationStatusCalculator()->getNewMigrations()->getItems()),
            );
        } finally {
            $this->factories->release($factory);
        }
    }

    public function sets(): array
    {
        return $this->registry->all();
    }

    /**
     * @throws MigrationSetRefusedException when a directory the set needs is missing, not a directory or not traversable, or is gone while migrations were executed
     */
    private function hasDirectory(MigrationSet $set, MigrationSetConfiguration $configuration): bool
    {
        if (is_dir($configuration->directory)) {
            return true;
        }

        if (! $set->isModule()) {
            throw new MigrationSetRefusedException(sprintf('The %s cannot run: "%s" does not exist or cannot be accessed.', $set->label(), $configuration->directory));
        }

        // An untraversable module or modules directory would pass for a set without migrations.
        foreach ([dirname($configuration->directory, 2), dirname($configuration->directory)] as $parent) {
            if (file_exists($parent) && ! is_executable($parent)) {
                throw new MigrationSetRefusedException(sprintf('The %s cannot run: cannot traverse "%s", check its permissions.', $set->label(), $parent));
            }
        }

        if (file_exists($configuration->directory) || is_link($configuration->directory)) {
            throw new MigrationSetRefusedException(sprintf('The %s cannot run: "%s" is not a directory.', $set->label(), $configuration->directory));
        }

        $executed = $this->factories->executedVersions($configuration, $set->migrationStep());
        if ($executed !== []) {
            throw new MigrationSetRefusedException(sprintf(
                'The %s has executed migrations, but "%s" does not exist or cannot be accessed: check the module files and their permissions.',
                $set->label(),
                $configuration->directory,
            ));
        }

        return false;
    }

    /**
     * @param bool $initialize false to write nothing, not even the metadata table
     */
    private function rollbackFactory(MigrationSet $set, bool $initialize): DependencyFactory
    {
        $configuration = $this->registry->configuration($set);
        if (! $this->hasDirectory($set, $configuration)) {
            throw new RollbackTargetNotFoundException(sprintf('The %s has no migration to roll back: "%s" does not exist.', $set->label(), $configuration->directory));
        }

        return $this->factories->create($set, $configuration, $set->rollbackStep(), $initialize);
    }

    /**
     * @return list<Version> the executed versions sorting after the target, oldest first
     */
    private function toRevert(MigrationSet $set, DependencyFactory $factory, string $versionOrAlias): array
    {
        $namespace = $this->registry->configuration($set)->namespace;

        // Accept a class name or its bare timestamp.
        $alias = preg_match('/^(Version\d+|\d{14})$/D', $versionOrAlias) === 1
            ? $namespace . '\\Version' . preg_replace('/^Version/', '', $versionOrAlias)
            : $versionOrAlias;

        try {
            $target = $factory->getVersionAliasResolver()->resolveVersionAlias($alias);
        } catch (UnknownMigrationVersion|NoMigrationsToExecute|NoMigrationsFoundWithCriteria $exception) {
            throw new RollbackTargetNotFoundException(sprintf(
                'The %s has no "%s" migration: pass a version listed by centreon:migrations:status -v, its '
                . 'class name (Version20260101000000) or its timestamp (20260101000000), "prev" or "first".',
                $set->label(),
                $versionOrAlias,
            ), 0, $exception);
        }

        $executed = $factory->getMetadataStorage()->getExecutedMigrations();
        $isFirst = (string) $target === '0';
        if (! $isFirst && ! $executed->hasMigration($target)) {
            throw new RollbackTargetNotFoundException(sprintf('Cannot roll the %s back to "%s": it has not been executed.', $set->label(), $target));
        }

        // Not getPlanUntilVersion(), which plans an upgrade as soon as an older migration is pending.
        $toRevert = [];
        $isAfterTarget = $isFirst;
        foreach ($executed->getItems() as $migration) {
            if ($isAfterTarget) {
                $toRevert[] = $migration->getVersion();
            }
            $isAfterTarget = $isAfterTarget || $migration->getVersion()->equals($target);
        }

        return $toRevert;
    }

    /**
     * @param list<Version> $versions
     *
     * @return list<string> in the order they are reverted
     */
    private function versionNames(array $versions): array
    {
        return array_map('strval', array_reverse($versions));
    }

    /**
     * @param array<ExecutedMigration|AvailableMigration> $migrations
     *
     * @return list<string>
     */
    private function versions(array $migrations): array
    {
        return array_values(array_map(static fn (ExecutedMigration|AvailableMigration $migration): string => (string) $migration->getVersion(), $migrations));
    }

    /**
     * @return list<string>
     */
    private function execute(MigrationSet $set, DependencyFactory $factory, MigrationPlanList $plan): array
    {
        $done = [];
        // One at a time: a batch error does not name its migration, and a batch runs past a skipped one.
        foreach ($plan->getItems() as $item) {
            try {
                $factory->getMigrator()->migrate(new MigrationPlanList([$item], $plan->getDirection()), new MigratorConfiguration());
            } catch (\Throwable $exception) {
                throw MigrationFailedException::failed($set, (string) $item->getVersion(), $exception, $done, $this->warnings($factory));
            }

            if ($item->getResult()?->isSkipped() === true) {
                $logger = $factory->getLogger();
                $reason = $logger instanceof MigrationStepLogger ? $logger->skipReason((string) $item->getVersion()) : null;

                throw MigrationFailedException::skipped($set, (string) $item->getVersion(), $reason, $done, $this->warnings($factory));
            }

            $done[] = (string) $item->getVersion();
        }

        return $done;
    }

    /**
     * @return list<string> what the migrations run by this factory warned of
     */
    private function warnings(DependencyFactory $factory): array
    {
        $logger = $factory->getLogger();

        return $logger instanceof MigrationStepLogger ? $logger->warnings() : [];
    }
}
