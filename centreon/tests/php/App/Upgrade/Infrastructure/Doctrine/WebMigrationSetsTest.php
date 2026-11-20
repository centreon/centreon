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

namespace Tests\App\Upgrade\Infrastructure\Doctrine;

use App\Upgrade\Domain\Model\MigrationOutcome;
use App\Upgrade\Domain\Model\MigrationSet;
use App\Upgrade\Domain\Model\WebMigrationSetEnum;
use App\Upgrade\Infrastructure\Doctrine\MigrationFactoryProvider;
use App\Upgrade\Infrastructure\Doctrine\MigrationRunner;
use App\Upgrade\Infrastructure\Doctrine\MigrationSetRegistry;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Filesystem\Filesystem;
use Tests\App\Upgrade\Infrastructure\Double\NamedConnectionRegistry;

/**
 * The Centreon Web sets on two databases, as centreon and centreon_storage.
 */
final class WebMigrationSetsTest extends TestCase
{
    private const string CONFIGURATION_DATABASE = 'centreon_web_migrations_test';
    private const string REALTIME_DATABASE = 'centreon_web_migrations_test_storage';

    private Connection $configuration;

    private Connection $realtime;

    private MigrationRunner $runner;

    protected function setUp(): void
    {
        $root = DriverManager::getConnection([
            'driver' => 'pdo_mysql',
            'host' => $this->env('hostCentreon'),
            'port' => (int) $this->env('port'),
            'user' => $this->env('rootUser', 'root'),
            'password' => $this->env('rootPassword', $this->env('password')),
        ]);
        foreach ([self::CONFIGURATION_DATABASE, self::REALTIME_DATABASE] as $database) {
            $root->executeStatement(sprintf('DROP DATABASE IF EXISTS `%s`', $database));
            $root->executeStatement(sprintf('CREATE DATABASE `%s`', $database));
            $root->executeStatement(sprintf("GRANT ALL PRIVILEGES ON `%s`.* TO '%s'@'%%'", $database, $this->env('user')));
        }
        $root->close();

        $this->configuration = $this->connect(self::CONFIGURATION_DATABASE);
        $this->realtime = $this->connect(self::REALTIME_DATABASE);

        $this->runner = new MigrationRunner(
            new MigrationSetRegistry(__DIR__ . '/Fixtures/web', '/nonexistent', new NullLogger()),
            new MigrationFactoryProvider(
                new NamedConnectionRegistry(['default' => $this->configuration, 'realtime' => $this->realtime]),
                new NullLogger(),
                '26.12.0',
            ),
        );
    }

    protected function tearDown(): void
    {
        foreach ([self::CONFIGURATION_DATABASE, self::REALTIME_DATABASE] as $database) {
            $this->configuration->executeStatement(sprintf('DROP DATABASE IF EXISTS `%s`', $database));
        }
        $this->configuration->close();
        $this->realtime->close();
    }

    public function testEachWebSetMigratesAndRecordsInItsOwnDatabase(): void
    {
        foreach (MigrationSet::webSets() as $set) {
            $this->runner->migrate($set);
        }

        self::assertSame([\Centreon\Migrations\Realtime\Version20000101000001::class], $this->versions($this->realtime));
        self::assertSame([\Centreon\Migrations\Configuration\Version20000101000001::class], $this->versions($this->configuration));
        self::assertTrue($this->realtime->createSchemaManager()->tablesExist(['fx_realtime']));
        self::assertFalse($this->realtime->createSchemaManager()->tablesExist(['fx_configuration']));
        self::assertTrue($this->configuration->createSchemaManager()->tablesExist(['fx_configuration']));
        self::assertFalse($this->configuration->createSchemaManager()->tablesExist(['fx_realtime']));

        foreach (WebMigrationSetEnum::cases() as $webSet) {
            $status = $this->runner->status(MigrationSet::web($webSet));
            self::assertNull($status->refusal, $webSet->value);
            self::assertSame([], $status->pending, $webSet->value);
        }
    }

    public function testEmptyMigrationDirectoriesAsShippedRunNothing(): void
    {
        $root = sys_get_temp_dir() . '/' . uniqid('empty_sets_', true);
        $filesystem = new Filesystem();
        $filesystem->mkdir([$root . '/web/migrations/realtime', $root . '/web/migrations/configuration', $root . '/modules/centreon-open-tickets/migrations']);

        $runner = new MigrationRunner(
            new MigrationSetRegistry($root . '/web', $root . '/modules', new NullLogger()),
            new MigrationFactoryProvider(
                new NamedConnectionRegistry(['default' => $this->configuration, 'realtime' => $this->realtime]),
                new NullLogger(),
                '26.12.0',
            ),
        );

        try {
            foreach ([...MigrationSet::webSets(), MigrationSet::module('centreon-open-tickets')] as $set) {
                self::assertEquals(new MigrationOutcome([], []), $runner->migrate($set), $set->name);

                $status = $runner->status($set);
                self::assertNull($status->readError, $set->name);
                self::assertNull($status->refusal, $set->name);
                self::assertSame([], $status->executed, $set->name);
                self::assertSame([], $status->pending, $set->name);
                self::assertSame([], $runner->planRollback($set, 'first'), $set->name);
                self::assertSame([], $runner->planRollback($set, 'prev'), $set->name);
            }
        } finally {
            $filesystem->remove($root);
        }

        self::assertTrue($this->realtime->createSchemaManager()->tablesExist(['doctrine_migration_versions']));
        self::assertTrue($this->configuration->createSchemaManager()->tablesExist(['doctrine_migration_versions', 'doctrine_migration_versions_centreon_open_tickets']));
    }

    public function testAWebSetRefusesToRunWhileAnotherConnectionHasATransactionOpen(): void
    {
        $this->configuration->beginTransaction();

        try {
            $this->runner->migrate(MigrationSet::web(WebMigrationSetEnum::Realtime));
            self::fail('Expected the migration to be refused');
        } catch (\LogicException $exception) {
            self::assertStringContainsString('the "default" connection has one open', $exception->getMessage());
        } finally {
            $this->configuration->rollBack();
        }

        self::assertFalse($this->realtime->createSchemaManager()->tablesExist(['fx_realtime']));
    }

    /**
     * @return list<string>
     */
    private function versions(Connection $connection): array
    {
        return array_values(array_filter(
            $connection->fetchFirstColumn('SELECT version FROM doctrine_migration_versions ORDER BY version'),
            is_string(...),
        ));
    }

    private function connect(string $database): Connection
    {
        return DriverManager::getConnection([
            'driver' => 'pdo_mysql',
            'host' => $this->env('hostCentreon'),
            'port' => (int) $this->env('port'),
            'user' => $this->env('user'),
            'password' => $this->env('password'),
            'dbname' => $database,
        ]);
    }

    private function env(string $name, string $default = ''): string
    {
        return is_string($_ENV[$name] ?? null) ? $_ENV[$name] : $default;
    }
}
