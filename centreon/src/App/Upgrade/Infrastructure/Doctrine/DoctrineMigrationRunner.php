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

use App\Upgrade\Application\MigrationRunner;
use App\Upgrade\Application\MigrationSetStatus;
use App\Upgrade\Domain\MigrationSet;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\Configuration\Connection\ExistingConnection;
use Doctrine\Migrations\Configuration\Migration\ConfigurationArray;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Exception\UnknownMigrationVersion;
use Doctrine\Migrations\Metadata\AvailableMigration;
use Doctrine\Migrations\Metadata\ExecutedMigration;
use Doctrine\Migrations\Metadata\MigrationPlanList;
use Doctrine\Migrations\MigratorConfiguration;
use Doctrine\Migrations\Provider\DBALSchemaDiffProvider;
use Doctrine\Migrations\Provider\LazySchemaDiffProvider;
use Doctrine\Migrations\Provider\SchemaDiffProvider;
use Doctrine\Migrations\Version\Direction;
use Doctrine\Migrations\Version\Version;
use Doctrine\Persistence\ConnectionRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class DoctrineMigrationRunner implements MigrationRunner
{
    /** MySQL and MariaDB column types DBAL cannot read. */
    private const array UNMAPPED_TYPES = [
        'bit', 'geometry', 'point', 'linestring', 'polygon', 'multipoint', 'multilinestring', 'multipolygon',
        'geometrycollection', 'inet4', 'inet6', 'uuid',
    ];

    public function __construct(
        private MigrationSetRegistry $registry,
        #[Autowire(service: 'doctrine')]
        private ConnectionRegistry $connections,
        #[Autowire(service: 'monolog.logger.upgrade')]
        private LoggerInterface $logger,
        #[Autowire(param: 'centreon.version')]
        private string $platformVersion,
    ) {
    }

    public function migrate(MigrationSet $set): array
    {
        $configuration = $this->registry->configuration($set);
        if (! $this->hasDirectory($set, $configuration->directory)) {
            return [];
        }

        $factory = $this->createCheckedFactory($set, $configuration, $set->migrationStep());
        $latest = $factory->getVersionAliasResolver()->resolveVersionAlias('latest');

        return $this->execute($set, $factory, $factory->getMigrationPlanCalculator()->getPlanUntilVersion($latest));
    }

    public function planRollback(MigrationSet $set, string $versionOrAlias): array
    {
        [, $toRevert] = $this->rollbackPlan($set, $versionOrAlias, initialize: false);

        return $this->versionNames($toRevert);
    }

    public function rollback(MigrationSet $set, string $versionOrAlias, array $expected): array
    {
        [$factory, $toRevert] = $this->rollbackPlan($set, $versionOrAlias, initialize: true);

        $planned = $this->versionNames($toRevert);
        if ($planned !== $expected) {
            throw new \RuntimeException(sprintf(
                'The migrations of the %s changed since they were confirmed: it would now revert %s. Nothing was reverted.',
                $set->label(),
                $planned === [] ? 'none' : implode(', ', $planned),
            ));
        }

        return $this->execute($set, $factory, $factory->getMigrationPlanCalculator()->getPlanForVersions($toRevert, Direction::DOWN));
    }

    public function status(MigrationSet $set): MigrationSetStatus
    {
        $configuration = $this->registry->configuration($set);
        if (! $this->hasDirectory($set, $configuration->directory)) {
            return new MigrationSetStatus($set, [], []);
        }

        $factory = $this->createCheckedFactory($set, $configuration, $set->migrationStep(), initialize: false);

        return new MigrationSetStatus(
            $set,
            $this->versions($factory->getMetadataStorage()->getExecutedMigrations()->getItems()),
            $this->versions($factory->getMigrationStatusCalculator()->getNewMigrations()->getItems()),
        );
    }

    public function sets(): array
    {
        return $this->registry->all();
    }

    private function createFactory(MigrationSetConfiguration $configuration, string $step): DependencyFactory
    {
        $connection = $this->connections->getConnection($configuration->connection);
        if (! $connection instanceof Connection) {
            throw new \LogicException(sprintf('The "%s" connection is not a DBAL connection.', $configuration->connection));
        }

        // Map unknown column types to text so that introspecting module tables does not throw.
        $platform = $connection->getDatabasePlatform();
        foreach (self::UNMAPPED_TYPES as $type) {
            if (! $platform->hasDoctrineTypeMappingFor($type)) {
                $platform->registerDoctrineTypeMapping($type, Types::TEXT);
            }
        }

        // The logger is given at construction time: the factory freezes on its first getter call.
        $factory = DependencyFactory::fromConnection(
            new ConfigurationArray([
                'migrations_paths' => [$configuration->namespace => $configuration->directory],
                'table_storage' => ['table_name' => $configuration->table],
            ]),
            new ExistingConnection($connection),
            new MigrationStepLogger($this->logger, $this->platformVersion, $step),
        );
        $factory->setService(SchemaDiffProvider::class, new ReadOnlySchemaDiffProvider(new LazySchemaDiffProvider(
            new DBALSchemaDiffProvider($connection->createSchemaManager(), $platform),
        )));

        return $factory;
    }

    /**
     * @throws \RuntimeException when the directory of a Centreon Web set is missing
     */
    private function hasDirectory(MigrationSet $set, string $directory): bool
    {
        if (is_dir($directory)) {
            return true;
        }

        if ($set->isModule()) {
            return false;
        }

        throw new \RuntimeException(sprintf('The %s cannot run: "%s" does not exist.', $set->label(), $directory));
    }

    /**
     * The checks migrate() relies on, which status() reports too.
     *
     * @param bool $initialize false to only read: the metadata table is not created
     *
     * @throws \RuntimeException when migrations recorded as executed are missing from the code base, or when a migration
     *                           breaks the conventions
     */
    /**
     * @param bool $initialize false to write nothing, not even the metadata table
     *
     * @return array{DependencyFactory, list<Version>} the versions to revert, executed after the target, oldest first
     */
    private function rollbackPlan(MigrationSet $set, string $versionOrAlias, bool $initialize): array
    {
        $configuration = $this->registry->configuration($set);
        if (! is_dir($configuration->directory)) {
            throw new \RuntimeException(sprintf('The %s has no migration to roll back: "%s" does not exist.', $set->label(), $configuration->directory));
        }

        $factory = $this->createCheckedFactory($set, $configuration, $set->rollbackStep(), $initialize);

        // Accept a class name or its bare timestamp.
        $alias = preg_match('/^(Version\d+|\d{14})$/D', $versionOrAlias) === 1
            ? $configuration->namespace . '\\Version' . preg_replace('/^Version/', '', $versionOrAlias)
            : $versionOrAlias;

        try {
            $target = $factory->getVersionAliasResolver()->resolveVersionAlias($alias);
        } catch (UnknownMigrationVersion $exception) {
            throw new \RuntimeException(sprintf(
                'The %s has no "%s" migration: pass a version listed by centreon:migrations:status -v, its '
                . 'class name (Version20260101000000) or its timestamp (20260101000000), "prev" or "first".',
                $set->label(),
                $versionOrAlias,
            ), 0, $exception);
        }

        $executed = $factory->getMetadataStorage()->getExecutedMigrations();
        $isFirst = (string) $target === '0';
        if (! $isFirst && ! $executed->hasMigration($target)) {
            throw new \RuntimeException(sprintf('Cannot roll the %s back to "%s": it is not executed.', $set->label(), $target));
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

        return [$factory, $toRevert];
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

    private function createCheckedFactory(MigrationSet $set, MigrationSetConfiguration $configuration, string $step, bool $initialize = true): DependencyFactory
    {
        $factory = $this->createFactory($configuration, $step);
        if ($initialize) {
            $factory->getMetadataStorage()->ensureInitialized();
        }

        // Not checked by the Migrator.
        $unavailable = $factory->getMigrationStatusCalculator()->getExecutedUnavailableMigrations();
        if (count($unavailable) > 0) {
            throw new \RuntimeException(sprintf(
                'The %s has executed migrations that are not available anymore: %s. Restore them (a migration is '
                . 'never deleted nor renamed once released), or remove them from the "%s" table if they were dropped on purpose.',
                $set->label(),
                implode(', ', $this->versions($unavailable->getItems())),
                $configuration->table,
            ));
        }

        $this->assertConventions($factory, $configuration);

        return $factory;
    }

    /**
     * Refuses what Doctrine would skip or run unchecked: a class out of the set namespace or not named after its file.
     *
     * @throws \RuntimeException
     */
    private function assertConventions(DependencyFactory $factory, MigrationSetConfiguration $configuration): void
    {
        $classes = [];
        foreach ($factory->getMigrationRepository()->getMigrations()->getItems() as $available) {
            $migration = $available->getMigration();
            if (! $migration instanceof DdlMigration && ! $migration instanceof DmlMigration) {
                throw new \RuntimeException(sprintf('The migration "%s" must extend DdlMigration or DmlMigration.', $migration::class));
            }
            $classes[] = $migration::class;
        }

        $expected = [];
        foreach (glob($configuration->directory . '/Version*.php') ?: [] as $file) {
            $expected[] = $class = $configuration->namespace . '\\' . basename($file, '.php');
            if (! in_array($class, $classes, true)) {
                throw new \RuntimeException(sprintf('"%s" must declare the "%s" migration.', $file, $class));
            }
        }

        // Doctrine loads every class of the namespace declared in a migration file.
        foreach (array_diff($classes, $expected) as $class) {
            throw new \RuntimeException(sprintf('The migration "%s" must be alone in a file named after it.', $class));
        }

        foreach ($factory->getMigrationRepository()->getMigrations()->getItems() as $available) {
            if (trim($available->getMigration()->getDescription()) === '') {
                throw new \RuntimeException(sprintf('The migration "%s" must describe what it changes in getDescription(): the upgrade log shows it.', $available->getMigration()::class));
            }
        }
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
        if (count($plan) === 0) {
            return [];
        }

        try {
            return array_map('strval', array_keys($factory->getMigrator()->migrate($plan, new MigratorConfiguration())));
        } catch (\Throwable $exception) {
            // Doctrine rethrows the error of the query as is: it would not tell which migration failed.
            $version = $this->failedVersion($factory, $plan);

            throw new \RuntimeException(sprintf(
                '%s of the %s failed: %s',
                $version === null ? 'A migration' : sprintf('The "%s" migration', $version),
                $set->label(),
                $exception->getMessage(),
            ), 0, $exception);
        }
    }

    /**
     * The first one of the plan left as it was: an upgrade records a migration once it ran, a rollback once reverted.
     */
    private function failedVersion(DependencyFactory $factory, MigrationPlanList $plan): ?string
    {
        try {
            $executed = $factory->getMetadataStorage()->getExecutedMigrations();
        } catch (\Throwable) {
            // The database may be what failed: the error of the migration matters more.
            return null;
        }

        foreach ($plan->getItems() as $item) {
            if ($executed->hasMigration($item->getVersion()) === ($plan->getDirection() === Direction::DOWN)) {
                return (string) $item->getVersion();
            }
        }

        return null;
    }
}
