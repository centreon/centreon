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

        $factory = $this->createInitializedFactory($set, $configuration);
        $latest = $factory->getVersionAliasResolver()->resolveVersionAlias('latest');

        return $this->execute($set, $factory, $factory->getMigrationPlanCalculator()->getPlanUntilVersion($latest));
    }

    public function rollback(MigrationSet $set, string $versionOrAlias): array
    {
        $configuration = $this->registry->configuration($set);
        if (! is_dir($configuration->directory)) {
            throw new \RuntimeException(\sprintf('The "%s" migration set has no migration to roll back: "%s" does not exist.', $set->name, $configuration->directory));
        }

        $factory = $this->createInitializedFactory($set, $configuration);

        if (preg_match('/^Version\d+$/D', $versionOrAlias) === 1) {
            $versionOrAlias = $configuration->namespace . '\\' . $versionOrAlias;
        }

        $target = $factory->getVersionAliasResolver()->resolveVersionAlias($versionOrAlias);
        $plan = $factory->getMigrationPlanCalculator()->getPlanUntilVersion($target);

        if (\count($plan) > 0 && $plan->getDirection() !== Direction::DOWN) {
            throw new \RuntimeException(\sprintf('Cannot roll the "%s" migration set back to "%s": it is ahead of the current version.', $set->name, $target));
        }

        return $this->execute($set, $factory, $plan);
    }

    public function status(MigrationSet $set): MigrationSetStatus
    {
        $configuration = $this->registry->configuration($set);
        if (! is_dir($configuration->directory)) {
            return new MigrationSetStatus($set, [], []);
        }

        // Read-only: the metadata table is not created.
        $factory = $this->createFactory($configuration);

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

        // A migration reading $schema reads every table, a module one included. Only read: the type does not matter.
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
     * @throws \RuntimeException when migrations recorded as executed are missing from the code base
     */
    private function createInitializedFactory(MigrationSet $set, MigrationSetConfiguration $configuration): DependencyFactory
    {
        $factory = $this->createFactory($configuration);
        $factory->getMetadataStorage()->ensureInitialized();

        // Not checked by the Migrator.
        $unavailable = $factory->getMigrationStatusCalculator()->getExecutedUnavailableMigrations();
        if (\count($unavailable) > 0) {
            throw new \RuntimeException(\sprintf(
                'The "%s" migration set has executed migrations that are not available anymore: %s. Restore them (a migration '
                . 'is never deleted nor renamed once released), or remove them from the "%s" table if they were dropped on purpose.',
                $set->name,
                implode(', ', $this->versions($unavailable->getItems())),
                $configuration->table,
            ));
        }

        $this->assertConventions($factory, $configuration);

        return $factory;
    }

    /**
     * Doctrine silently ignores a class out of the namespace of the set, and would run a migration with no base class
     * checks: the module would stay outdated, or bypass them.
     *
     * @throws \RuntimeException
     */
    private function assertConventions(DependencyFactory $factory, MigrationSetConfiguration $configuration): void
    {
        $files = [];
        foreach ($factory->getMigrationRepository()->getMigrations()->getItems() as $available) {
            $migration = $available->getMigration();
            if (! $migration instanceof DdlMigration && ! $migration instanceof DmlMigration) {
                throw new \RuntimeException(\sprintf('The migration "%s" must extend DdlMigration or DmlMigration.', $migration::class));
            }
            $files[] = (new \ReflectionClass($migration))->getFileName();
        }

        foreach (glob($configuration->directory . '/Version*.php') ?: [] as $file) {
            if (! \in_array(realpath($file), $files, true)) {
                throw new \RuntimeException(\sprintf('"%s" declares no migration in the "%s" namespace.', $file, $configuration->namespace));
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
        if (\count($plan) === 0) {
            return [];
        }

        $this->logger->info('Running the "{set}" migration set', ['set' => $set->name, 'direction' => $plan->getDirection()]);

        return array_map('strval', array_keys($factory->getMigrator()->migrate($plan, new MigratorConfiguration())));
    }
}
