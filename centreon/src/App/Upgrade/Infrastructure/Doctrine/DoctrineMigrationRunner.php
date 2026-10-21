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
    ) {
    }

    public function migrate(MigrationSet $set): array
    {
        $configuration = $this->registry->configuration($set);
        if (! is_dir($configuration->directory)) {
            return [];
        }

        $factory = $this->createCheckedFactory($set, $configuration);
        $latest = $factory->getVersionAliasResolver()->resolveVersionAlias('latest');

        return $this->execute($set, $factory, $factory->getMigrationPlanCalculator()->getPlanUntilVersion($latest));
    }

    public function rollback(MigrationSet $set, string $versionOrAlias, bool $dryRun = false, ?array $expected = null): array
    {
        $configuration = $this->registry->configuration($set);
        if (! is_dir($configuration->directory)) {
            throw new \RuntimeException(\sprintf('The %s has no migration to roll back: "%s" does not exist.', $set->label(), $configuration->directory));
        }

        // A dry run writes nothing: not even the metadata table.
        $factory = $this->createCheckedFactory($set, $configuration, initialize: ! $dryRun);

        // As status shows them, or as the generated file names end.
        $alias = preg_match('/^(Version\d+|\d{14})$/D', $versionOrAlias) === 1
            ? $configuration->namespace . '\\Version' . preg_replace('/^Version/', '', $versionOrAlias)
            : $versionOrAlias;

        try {
            $target = $factory->getVersionAliasResolver()->resolveVersionAlias($alias);
        } catch (UnknownMigrationVersion $exception) {
            throw new \RuntimeException(\sprintf(
                'The %s has no "%s" migration: pass a version listed by centreon:migrations:status -v, its '
                . 'class name (Version20260101000000) or its timestamp (20260101000000), "prev" or "first".',
                $set->label(),
                $versionOrAlias,
            ), 0, $exception);
        }

        $executed = $factory->getMetadataStorage()->getExecutedMigrations();
        $isFirst = (string) $target === '0';
        if (! $isFirst && ! $executed->hasMigration($target)) {
            throw new \RuntimeException(\sprintf('Cannot roll the %s back to "%s": it is not executed.', $set->label(), $target));
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

        // In the order they would be reverted.
        $planned = array_map('strval', array_reverse($toRevert));
        if ($dryRun) {
            return $planned;
        }

        if ($expected !== null && $planned !== $expected) {
            throw new \RuntimeException(\sprintf(
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
        if (! is_dir($configuration->directory)) {
            return new MigrationSetStatus($set, [], []);
        }

        $factory = $this->createCheckedFactory($set, $configuration, initialize: false);

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

    private function createFactory(MigrationSetConfiguration $configuration): DependencyFactory
    {
        $connection = $this->connections->getConnection($configuration->connection);
        if (! $connection instanceof Connection) {
            throw new \LogicException(\sprintf('The "%s" connection is not a DBAL connection.', $configuration->connection));
        }

        // A migration reading $schema reads every table, a module one included. Only read: the type does not matter. The
        // mapping stays on the platform of the connection for the rest of the process: it only lets an introspection read
        // these columns, as text, where it would throw.
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
            $this->logger,
        );
        $factory->setService(SchemaDiffProvider::class, new ReadOnlySchemaDiffProvider(new LazySchemaDiffProvider(
            new DBALSchemaDiffProvider($connection->createSchemaManager(), $platform),
        )));

        return $factory;
    }

    /**
     * The checks migrate() relies on, which status() reports too.
     *
     * @param bool $initialize false to only read: the metadata table is not created
     *
     * @throws \RuntimeException when migrations recorded as executed are missing from the code base, or when a migration
     *                           breaks the conventions
     */
    private function createCheckedFactory(MigrationSet $set, MigrationSetConfiguration $configuration, bool $initialize = true): DependencyFactory
    {
        $factory = $this->createFactory($configuration);
        if ($initialize) {
            $factory->getMetadataStorage()->ensureInitialized();
        }

        // Not checked by the Migrator.
        $unavailable = $factory->getMigrationStatusCalculator()->getExecutedUnavailableMigrations();
        if (\count($unavailable) > 0) {
            throw new \RuntimeException(\sprintf(
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
     * Doctrine silently ignores a class out of the namespace of the set, and would run a migration with no base class
     * checks: the module would stay outdated, or bypass them. The Extension Manager also tells the pending migrations
     * of a module from their file names (see ModuleSource): each one is named after its file.
     *
     * @throws \RuntimeException
     */
    private function assertConventions(DependencyFactory $factory, MigrationSetConfiguration $configuration): void
    {
        $classes = [];
        foreach ($factory->getMigrationRepository()->getMigrations()->getItems() as $available) {
            $migration = $available->getMigration();
            if (! $migration instanceof DdlMigration && ! $migration instanceof DmlMigration) {
                throw new \RuntimeException(\sprintf('The migration "%s" must extend DdlMigration or DmlMigration.', $migration::class));
            }
            $classes[] = $migration::class;
        }

        $expected = [];
        foreach (glob($configuration->directory . '/Version*.php') ?: [] as $file) {
            $expected[] = $class = $configuration->namespace . '\\' . basename($file, '.php');
            if (! \in_array($class, $classes, true)) {
                throw new \RuntimeException(\sprintf('"%s" must declare the "%s" migration.', $file, $class));
            }
        }

        // Doctrine loads every class of the namespace declared in a migration file.
        foreach (array_diff($classes, $expected) as $class) {
            throw new \RuntimeException(\sprintf('The migration "%s" must be alone in a file named after it.', $class));
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
        if (\count($plan) === 0) {
            return [];
        }

        $this->logger->info('Running the {set}', ['set' => $set->label(), 'direction' => $plan->getDirection()]);

        try {
            return array_map('strval', array_keys($factory->getMigrator()->migrate($plan, new MigratorConfiguration())));
        } catch (\Throwable $exception) {
            // Doctrine rethrows the error of the query as is: it would not tell which migration failed.
            $version = $this->failedVersion($factory, $plan);

            throw new \RuntimeException(\sprintf(
                '%s of the %s failed: %s',
                $version === null ? 'A migration' : \sprintf('The "%s" migration', $version),
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
