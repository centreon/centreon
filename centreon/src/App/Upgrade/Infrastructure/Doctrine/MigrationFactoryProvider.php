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

use App\Upgrade\Domain\Exception\MigrationSetRefusedException;
use App\Upgrade\Domain\Model\MigrationSet;
use App\Upgrade\Infrastructure\Log\FailSafeLogger;
use App\Upgrade\Infrastructure\Migration\DdlMigration;
use App\Upgrade\Infrastructure\Migration\DmlMigration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\Configuration\Connection\ExistingConnection;
use Doctrine\Migrations\Configuration\Migration\ConfigurationArray;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Metadata\ExecutedMigration;
use Doctrine\Migrations\Provider\DBALSchemaDiffProvider;
use Doctrine\Migrations\Provider\LazySchemaDiffProvider;
use Doctrine\Migrations\Provider\SchemaDiffProvider;
use Doctrine\Persistence\ConnectionRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Webmozart\Assert\Assert;

/**
 * Builds the Doctrine services of a set, on a connection of their own, once the set passes the checks of the engine.
 */
final readonly class MigrationFactoryProvider
{
    /** MySQL and MariaDB column types DBAL cannot read. */
    private const array UNMAPPED_TYPES = [
        'bit', 'geometry', 'point', 'linestring', 'polygon', 'multipoint', 'multilinestring', 'multipolygon',
        'geometrycollection', 'inet4', 'inet6', 'uuid',
    ];

    public function __construct(
        #[Autowire(service: 'doctrine')]
        private ConnectionRegistry $connections,
        #[Autowire(service: FailSafeLogger::class)]
        private LoggerInterface $logger,
        #[Autowire(param: 'centreon.version')]
        private string $platformVersion,
        /** @var (\Closure(string): (string|false))|null reads a migration file, file_get_contents() by default */
        private ?\Closure $readFile = null,
    ) {
    }

    /**
     * The caller closes its connection with release().
     *
     * @param bool $initialize false to only read: the metadata table is not created
     *
     * @throws MigrationSetRefusedException when the migrations cannot be listed or read, an executed one is missing, or one breaks the conventions
     * @throws \LogicException when the connection is not a DBAL one, or $initialize is true with a transaction open
     */
    public function create(MigrationSet $set, MigrationSetConfiguration $configuration, string $step, bool $initialize): DependencyFactory
    {
        $factory = $this->createFactory($configuration, $step, $initialize);

        try {
            $this->assertConventions($set, $factory, $configuration);

            // After the conventions check, so a refused set leaves no table, and before Doctrine reads the table.
            if ($initialize) {
                $factory->getMetadataStorage()->ensureInitialized();
            }

            // The Migrator runs despite executed migrations missing from the code base.
            $unavailable = $factory->getMigrationStatusCalculator()->getExecutedUnavailableMigrations();
            if (count($unavailable) > 0) {
                throw new MigrationSetRefusedException(sprintf(
                    'The %s has executed migrations that are not available anymore: %s. A migration of a maintenance '
                    . 'release ships in the next version too: upgrade to a version that has it. Remove them from the "%s" '
                    . 'table only if they were dropped on purpose.',
                    $set->label(),
                    implode(', ', array_map(static fn ($migration): string => (string) $migration->getVersion(), $unavailable->getItems())),
                    $configuration->table,
                ));
            }
        } catch (\Throwable $exception) {
            $this->release($factory);

            throw $exception;
        }

        return $factory;
    }

    public function release(DependencyFactory $factory): void
    {
        $factory->getConnection()->close();
    }

    /**
     * Reads the metadata table only: the migrations directory may be missing.
     *
     * @throws \LogicException when the connection is not a DBAL one
     *
     * @return list<string>
     */
    public function executedVersions(MigrationSetConfiguration $configuration, string $step): array
    {
        $factory = $this->createFactory($configuration, $step, initialize: false);

        try {
            return array_values(array_map(
                static fn (ExecutedMigration $migration): string => (string) $migration->getVersion(),
                $factory->getMetadataStorage()->getExecutedMigrations()->getItems(),
            ));
        } finally {
            $this->release($factory);
        }
    }

    /**
     * @param bool $initialize true to run migrations: refused while a Doctrine connection has an open transaction
     *
     * @throws \LogicException
     */
    private function createFactory(MigrationSetConfiguration $configuration, string $step, bool $initialize): DependencyFactory
    {
        $shared = $this->connections->getConnection($configuration->connection);
        Assert::isInstanceOf($shared, Connection::class, sprintf('The "%s" connection is not a DBAL connection.', $configuration->connection));

        // The migrations run on a connection of their own: they would wait for the locks of an open transaction.
        if ($initialize) {
            foreach ($this->connections->getConnections() as $name => $registered) {
                if ($registered instanceof Connection) {
                    Assert::false($registered->isTransactionActive(), sprintf('Migrations cannot run inside a transaction: the "%s" connection has one open.', $name));
                }
            }
        }

        // A plain connection to the actual server: the configured version may be wrong, introspection needs the right platform.
        $params = $shared->getParams();
        unset($params['serverVersion'], $params['primary'], $params['replica']);
        // With this key, the test driver hands back its connection, already in a transaction.
        if (array_key_exists('dama.connection_key', $params)) {
            unset($params['dama.connection_key']);
        }
        $connection = DriverManager::getConnection($params, $shared->getConfiguration());

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
     * Refuses the migrations that break the engine conventions.
     *
     * @throws MigrationSetRefusedException
     */
    private function assertConventions(MigrationSet $set, DependencyFactory $factory, MigrationSetConfiguration $configuration): void
    {
        // Listed with GLOB_ERR before Doctrine requires the files: an unreadable directory must not look empty, a duplicate class would be fatal.
        $files = glob($configuration->directory . '/*', \GLOB_ERR);
        if ($files === false) {
            throw new MigrationSetRefusedException(sprintf('Cannot list the migrations of "%s": check its permissions.', $configuration->directory));
        }

        // Doctrine only finds Version*.php files at the root of the directory: any other one would never run.
        foreach ($files as $file) {
            if (! is_file($file) || preg_match('/^Version\d+\.php$/D', basename($file)) !== 1) {
                throw new MigrationSetRefusedException(sprintf('"%s" is not a migration file: name it Version<timestamp>.php, at the root of "%s".', $file, $configuration->directory));
            }
        }

        foreach ($files as $file) {
            $expected = $configuration->namespace . '\\' . basename($file, '.php');
            $source = ($this->readFile ?? file_get_contents(...))($file);
            if ($source === false) {
                throw new MigrationSetRefusedException(sprintf('Cannot read the migration file "%s": check its permissions.', $file));
            }
            $declared = $this->declaredClasses($source);
            if (! in_array($expected, $declared, true)) {
                throw new MigrationSetRefusedException(sprintf('"%s" must declare the "%s" migration.', $file, $expected));
            }
            foreach (array_diff($declared, [$expected]) as $class) {
                throw new MigrationSetRefusedException(sprintf('The migration "%s" must be alone in a file named after it.', $class));
            }
        }

        // Requiring the files runs their code: a failure there must name the set and the file.
        try {
            // Listed without being instantiated: Doctrine instantiates them all and fails on a class that is not a migration.
            foreach ($factory->getMigrationsFinder()->findMigrations($configuration->directory, $configuration->namespace) as $class) {
                if (! is_subclass_of($class, DdlMigration::class) && ! is_subclass_of($class, DmlMigration::class)) {
                    throw new MigrationSetRefusedException(sprintf('The migration "%s" must extend DdlMigration or DmlMigration.', $class));
                }
                if ((new \ReflectionClass($class))->isAbstract()) {
                    throw new MigrationSetRefusedException(sprintf('The migration "%s" must not be abstract: Doctrine instantiates every class of a migration file.', $class));
                }
            }

            foreach ($factory->getMigrationRepository()->getMigrations()->getItems() as $available) {
                if (trim($available->getMigration()->getDescription()) === '') {
                    throw new MigrationSetRefusedException(sprintf('The migration "%s" must describe what it changes in getDescription(): the upgrade log shows it.', $available->getMigration()::class));
                }
            }
        } catch (MigrationSetRefusedException $refusal) {
            throw $refusal;
        } catch (\Throwable $exception) {
            throw new MigrationSetRefusedException(sprintf('Cannot load the migrations of the %s: %s (%s:%d).', $set->label(), $exception->getMessage(), $exception->getFile(), $exception->getLine()), $exception);
        }
    }

    /**
     * @return list<string> the fully qualified names of the classes, interfaces, traits and enums the source declares
     */
    private function declaredClasses(string $source): array
    {
        $tokens = array_values(array_filter(
            \PhpToken::tokenize($source),
            static fn (\PhpToken $token): bool => ! $token->isIgnorable(),
        ));

        $namespace = '';
        $classes = [];
        foreach ($tokens as $index => $token) {
            $next = $tokens[$index + 1] ?? null;
            if ($token->is(\T_NAMESPACE) && $next?->is([\T_STRING, \T_NAME_QUALIFIED]) === true) {
                $namespace = $next->text . '\\';
            } elseif ($token->is(\T_NAMESPACE) && $next?->text === '{') {
                $namespace = '';
            }
            // A name follows neither Foo::class nor an anonymous class.
            if ($token->is([\T_CLASS, \T_INTERFACE, \T_TRAIT, \T_ENUM]) && $next?->is(\T_STRING) === true) {
                $classes[] = $namespace . $next->text;
            }
        }

        return $classes;
    }
}
