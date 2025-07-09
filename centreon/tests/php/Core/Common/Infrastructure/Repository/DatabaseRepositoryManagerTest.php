<?php
/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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

namespace Tests\Centreon\Infrastructure\Repository;

use Adaptation\Database\Connection\Adapter\Dbal\DbalConnectionAdapter;
use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\Model\ConnectionConfig;
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Infrastructure\Repository\DataStorageEngineRdb;
use CentreonDB;
use Core\Common\Infrastructure\Repository\DatabaseRepositoryManager;

/**
 * @param string $nameEnvVar
 *
 * @return string|null
 */
function getEnvironmentVariable(string $nameEnvVar): ?string
{
    $envVarValue = getenv($nameEnvVar, true) ?: getenv($nameEnvVar);

    return (is_string($envVarValue) && ! empty($envVarValue)) ? $envVarValue : null;
}

$dbHost = getEnvironmentVariable('MYSQL_HOST');
$dbUser = getEnvironmentVariable('MYSQL_USER');
$dbPassword = getEnvironmentVariable('MYSQL_PASSWORD');

$dbConfigCentreon = null;

if (! is_null($dbHost) && ! is_null($dbUser) && ! is_null($dbPassword)) {
    $dbConfigCentreon = new ConnectionConfig(
        host: $dbHost,
        user: $dbUser,
        password: $dbPassword,
        databaseNameConfiguration: 'centreon',
        databaseNameRealTime: 'centreon_storage',
        port: 3306
    );
}

/**
 * @param ConnectionConfig $connectionConfig
 *
 * @return bool
 */
function hasConnectionDb(ConnectionConfig $connectionConfig): bool
{
    try {
        new \PDO (
            $connectionConfig->getMysqlDsn(),
            $connectionConfig->getUser(),
            $connectionConfig->getPassword(),
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );

        return true;
    } catch (\PDOException $exception) {
        return false;
    }
}

if (! is_null($dbConfigCentreon) && hasConnectionDb($dbConfigCentreon)) {
    it('test commit a transaction using DbalConnectionAdapter with success', function () use ($dbConfigCentreon) {
        $connection = DbalConnectionAdapter::createFromConfig($dbConfigCentreon);
        $databaseRepositoryManager = new DatabaseRepositoryManager($connection);
        // Check starting transaction
        $databaseRepositoryManager->startTransaction();
        expect($databaseRepositoryManager->isTransactionActive())->toBeTrue()
            ->and($connection->isTransactionActive())->toBeTrue();
        // Check transaction
        $queryParameters = QueryParameters::create(
            [
                QueryParameter::int('id', 110),
                QueryParameter::string('name', 'foo_name'),
                QueryParameter::string('alias', 'foo_alias')
            ]
        );
        $inserted = $connection->insert(
            "INSERT INTO contact(contact_id, contact_name, contact_alias) VALUES(:id, :name, :alias)",
            $queryParameters
        );
        expect($inserted)->toBeInt()->toBe(1);
        // Check commit transaction
        $successCommit = $databaseRepositoryManager->commitTransaction();
        expect($successCommit)->toBeTrue()
            ->and($connection->isTransactionActive())->toBeFalse();
        // clean up the database
        $deleted = $connection->delete(
            "DELETE FROM contact WHERE contact_id = :id",
            QueryParameters::create([QueryParameter::int('id', 110)])
        );
        expect($deleted)->toBeInt()->toBe(1);
    });

    it('test rollback a transaction using DbalConnectionAdapter with success', function () use ($dbConfigCentreon) {
        $connection = DbalConnectionAdapter::createFromConfig($dbConfigCentreon);
        $databaseRepositoryManager = new DatabaseRepositoryManager($connection);
        // Check starting transaction
        $databaseRepositoryManager->startTransaction();
        expect($databaseRepositoryManager->isTransactionActive())->toBeTrue()
            ->and($connection->isTransactionActive())->toBeTrue();
        // Check transaction
        $queryParameters = QueryParameters::create(
            [
                QueryParameter::int('id', 110),
                QueryParameter::string('name', 'foo_name'),
                QueryParameter::string('alias', 'foo_alias')
            ]
        );
        $inserted = $connection->insert(
            "INSERT INTO contact(contact_id, contact_name, contact_alias) VALUES(:id, :name, :alias)",
            $queryParameters
        );
        expect($inserted)->toBeInt()->toBe(1);
        // Check rollback transaction
        $successRollback = $databaseRepositoryManager->rollbackTransaction();
        expect($successRollback)->toBeTrue()
            ->and($connection->isTransactionActive())->toBeFalse();
        // Check that the data was not inserted
        $contact = $connection->fetchAssociative(
            "SELECT * FROM contact WHERE contact_id = :id",
            QueryParameters::create([QueryParameter::int('id', 110)])
        );
        expect($contact)->toBeFalse();
    });

    it('test commit a transaction using DatabaseConnection with success', function () use ($dbConfigCentreon) {
        $connection = DatabaseConnection::createFromConfig($dbConfigCentreon);
        $databaseRepositoryManager = new DatabaseRepositoryManager($connection);
        // Check starting transaction
        $databaseRepositoryManager->startTransaction();
        expect($databaseRepositoryManager->isTransactionActive())->toBeTrue()
            ->and($connection->isTransactionActive())->toBeTrue();
        // Check transaction
        $queryParameters = QueryParameters::create(
            [
                QueryParameter::int('id', 110),
                QueryParameter::string('name', 'foo_name'),
                QueryParameter::string('alias', 'foo_alias')
            ]
        );
        $inserted = $connection->insert(
            "INSERT INTO contact(contact_id, contact_name, contact_alias) VALUES(:id, :name, :alias)",
            $queryParameters
        );
        expect($inserted)->toBeInt()->toBe(1);
        // Check commit transaction
        $successCommit = $databaseRepositoryManager->commitTransaction();
        expect($successCommit)->toBeTrue()
            ->and($connection->isTransactionActive())->toBeFalse();
        // clean up the database
        $deleted = $connection->delete(
            "DELETE FROM contact WHERE contact_id = :id",
            QueryParameters::create([QueryParameter::int('id', 110)])
        );
        expect($deleted)->toBeInt()->toBe(1);
    });

    it('test rollback a transaction using DatabaseConnection with success', function () use ($dbConfigCentreon) {
        $connection = DatabaseConnection::createFromConfig($dbConfigCentreon);
        $databaseRepositoryManager = new DatabaseRepositoryManager($connection);
        // Check starting transaction
        $databaseRepositoryManager->startTransaction();
        expect($databaseRepositoryManager->isTransactionActive())->toBeTrue()
            ->and($connection->isTransactionActive())->toBeTrue();
        // Check transaction
        $queryParameters = QueryParameters::create(
            [
                QueryParameter::int('id', 110),
                QueryParameter::string('name', 'foo_name'),
                QueryParameter::string('alias', 'foo_alias')
            ]
        );
        $inserted = $connection->insert(
            "INSERT INTO contact(contact_id, contact_name, contact_alias) VALUES(:id, :name, :alias)",
            $queryParameters
        );
        expect($inserted)->toBeInt()->toBe(1);
        // Check rollback transaction
        $successRollback = $databaseRepositoryManager->rollbackTransaction();
        expect($successRollback)->toBeTrue()
            ->and($connection->isTransactionActive())->toBeFalse();
        // Check that the data was not inserted
        $contact = $connection->fetchAssociative(
            "SELECT * FROM contact WHERE contact_id = :id",
            QueryParameters::create([QueryParameter::int('id', 110)])
        );
        expect($contact)->toBeFalse();
    });

    it('test commit a transaction using CentreonDB with success', function () use ($dbConfigCentreon) {
        $connection = CentreonDB::createFromConfig($dbConfigCentreon);
        $databaseRepositoryManager = new DatabaseRepositoryManager($connection);
        // Check starting transaction
        $databaseRepositoryManager->startTransaction();
        expect($databaseRepositoryManager->isTransactionActive())->toBeTrue()
            ->and($connection->isTransactionActive())->toBeTrue();
        // Check transaction
        $queryParameters = QueryParameters::create(
            [
                QueryParameter::int('id', 110),
                QueryParameter::string('name', 'foo_name'),
                QueryParameter::string('alias', 'foo_alias')
            ]
        );
        $inserted = $connection->insert(
            "INSERT INTO contact(contact_id, contact_name, contact_alias) VALUES(:id, :name, :alias)",
            $queryParameters
        );
        expect($inserted)->toBeInt()->toBe(1);
        // Check commit transaction
        $successCommit = $databaseRepositoryManager->commitTransaction();
        expect($successCommit)->toBeTrue()
            ->and($connection->isTransactionActive())->toBeFalse();
        // clean up the database
        $deleted = $connection->delete(
            "DELETE FROM contact WHERE contact_id = :id",
            QueryParameters::create([QueryParameter::int('id', 110)])
        );
        expect($deleted)->toBeInt()->toBe(1);
    });

    it('test rollback a transaction using CentreonDB with success', function () use ($dbConfigCentreon) {
        $connection = CentreonDB::createFromConfig($dbConfigCentreon);
        $databaseRepositoryManager = new DatabaseRepositoryManager($connection);
        // Check starting transaction
        $databaseRepositoryManager->startTransaction();
        expect($databaseRepositoryManager->isTransactionActive())->toBeTrue()
            ->and($connection->isTransactionActive())->toBeTrue();
        // Check transaction
        $queryParameters = QueryParameters::create(
            [
                QueryParameter::int('id', 110),
                QueryParameter::string('name', 'foo_name'),
                QueryParameter::string('alias', 'foo_alias')
            ]
        );
        $inserted = $connection->insert(
            "INSERT INTO contact(contact_id, contact_name, contact_alias) VALUES(:id, :name, :alias)",
            $queryParameters
        );
        expect($inserted)->toBeInt()->toBe(1);
        // Check rollback transaction
        $successRollback = $databaseRepositoryManager->rollbackTransaction();
        expect($successRollback)->toBeTrue()
            ->and($connection->isTransactionActive())->toBeFalse();
        // Check that the data was not inserted
        $contact = $connection->fetchAssociative(
            "SELECT * FROM contact WHERE contact_id = :id",
            QueryParameters::create([QueryParameter::int('id', 110)])
        );
        expect($contact)->toBeFalse();
    });

    it(
        'test commit a transaction using two different connectors is not correct',
        function () use ($dbConfigCentreon) {
            $connection = DbalConnectionAdapter::createFromConfig($dbConfigCentreon);
            $databaseRepositoryManager = new DatabaseRepositoryManager(
                DatabaseConnection::createFromConfig($dbConfigCentreon)
            );
            // Check starting transaction
            $databaseRepositoryManager->startTransaction();
            expect($databaseRepositoryManager->isTransactionActive())->toBeTrue()
                ->and($connection->isTransactionActive())->toBeFalse();
        }
    );

    it(
        'test rollback a transaction using two different connectors is not correct',
        function () use ($dbConfigCentreon) {
            $connection = DbalConnectionAdapter::createFromConfig($dbConfigCentreon);
            $databaseRepositoryManager = new DatabaseRepositoryManager(
                DatabaseConnection::createFromConfig($dbConfigCentreon)
            );
            // Check starting transaction
            $databaseRepositoryManager->startTransaction();
            // Check transaction
            $queryParameters = QueryParameters::create(
                [
                    QueryParameter::int('id', 110),
                    QueryParameter::string('name', 'foo_name'),
                    QueryParameter::string('alias', 'foo_alias')
                ]
            );
            $inserted = $connection->insert(
                "INSERT INTO contact(contact_id, contact_name, contact_alias) VALUES(:id, :name, :alias)",
                $queryParameters
            );
            expect($inserted)->toBeInt()->toBe(1);
            // Check rollback transaction
            $successRollback = $databaseRepositoryManager->rollbackTransaction();
            expect($successRollback)->toBeTrue()
                ->and($connection->isTransactionActive())->toBeFalse();
            // Check that the data was not inserted
            $contact = $connection->fetchAssociative(
                "SELECT * FROM contact WHERE contact_id = :id",
                QueryParameters::create([QueryParameter::int('id', 110)])
            );
            expect($contact)->toBeArray()
                ->and($contact)->toHaveKey('contact_id')
                ->and($contact['contact_id'])->toBe(110);
            // clean up the database
            $deleted = $connection->delete(
                "DELETE FROM contact WHERE contact_id = :id",
                QueryParameters::create([QueryParameter::int('id', 110)])
            );
            expect($deleted)->toBeInt()->toBe(1);
        }
    );
}
