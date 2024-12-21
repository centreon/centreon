<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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

namespace Tests\Centreon\Infrastructure;

use Centreon\Domain\Log\Logger;
use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Infrastructure\DatabaseConnectionConfig;
use Centreon\Infrastructure\DatabaseConnectionException;
use PDO;
use PDOStatement;

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
$dbConfigCentreonStorage = null;

if (! is_null($dbHost) && ! is_null($dbUser) && ! is_null($dbPassword)) {
    $dbConfigCentreon = new DatabaseConnectionConfig(
        dbHost: $dbHost,
        dbUser: $dbUser,
        dbPassword: $dbPassword,
        dbName: 'centreon',
        dbPort: 3306
    );
    $dbConfigCentreonStorage = new DatabaseConnectionConfig(
        dbHost: $dbHost,
        dbUser: $dbUser,
        dbPassword: $dbPassword,
        dbName: 'centreon_storage',
        dbPort: 3306
    );
}

/**
 * @param DatabaseConnectionConfig $dbConfig
 *
 * @return bool
 */
function hasConnectionDb(DatabaseConnectionConfig $dbConfig): bool
{
    try {
        new PDO (
            $dbConfig->getMysqlDsn(),
            $dbConfig->dbUser, $dbConfig->dbPassword, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        return true;
    } catch (\PDOException $e) {
        return false;
    }
}

// ************************************** With centreon database connection *******************************************

if (! is_null($dbConfigCentreon) && hasConnectionDb($dbConfigCentreon)) {
    it(
        'connect to centreon database with DatabaseConnexion constructor',
        function () use ($dbConfigCentreon): void {
            $db = new DatabaseConnection(
                new Logger(),
                $dbConfigCentreon->dbHost,
                $dbConfigCentreon->dbName,
                $dbConfigCentreon->dbUser,
                $dbConfigCentreon->dbPassword,
                $dbConfigCentreon->dbPort
            );
            expect($db)->toBeInstanceOf(DatabaseConnection::class);
            $dbName = $db->executeQuery("select database()")->fetchColumn();
            expect($dbName)->toBe('centreon')
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    );

    it(
        'connect to centreon database with DatabaseConnection::connect factory',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            expect($db)->toBeInstanceOf(DatabaseConnection::class);
            $dbName = $db->executeQuery("select database()")->fetchColumn();
            expect($dbName)->toBe('centreon')
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    );

    it('check if the database connection is active', function () use ($dbConfigCentreon): void {
        $db = DatabaseConnection::connect($dbConfigCentreon);
        expect($db->isConnected())->toBeTrue();
    });

    it('get the database name of the current connection', function () use ($dbConfigCentreon): void {
        $db = DatabaseConnection::connect($dbConfigCentreon);
        $dbName = $db->getCurrentDatabaseName();
        expect($dbName)->toBe('centreon');
    });

    // ---------------------------------------- prepareQuery ----------------------------------------------

    it(
        'prepare query with a correct prepared query',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $pdoSth = $db->prepareQuery("select * from contact where contact_id = :contact_id");
            expect($pdoSth)->toBeInstanceOf(PDOStatement::class)
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    );

    it(
        'prepare query with an empty prepared query',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $db->prepareQuery("");
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    // ---------------------------------------- executePreparedQuery ----------------------------------------------

    it(
        'execute executePreparedQuery with a correct prepared query ',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $pdoSth = $db->prepareQuery("select * from contact where contact_id = :contact_id");
            expect($pdoSth)->toBeInstanceOf(PDOStatement::class);
            $result = $db->executePreparedQuery($pdoSth, ['contact_id' => 1]);
            expect($result)->toBeInstanceOf(PDOStatement::class)
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    );

    it(
        'execute executePreparedQuery with an empty array for $bindParams',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $pdoSth = $db->prepareQuery("select * from contact where contact_id = :contact_id");
            expect($pdoSth)->toBeInstanceOf(PDOStatement::class);
            $db->executePreparedQuery($pdoSth, []);
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute executePreparedQuery with an incorrect prepared query',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $pdoSth = $db->prepareQuery("foo");
            expect($pdoSth)->toBeInstanceOf(PDOStatement::class);
            $db->executePreparedQuery($pdoSth, ['contact_id' => 1]);
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute executePreparedQuery with typing binding params with a correct prepared query',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $pdoSth = $db->prepareQuery("select * from contact where contact_id = :contact_id");
            expect($pdoSth)->toBeInstanceOf(PDOStatement::class);
            $result = $db->executePreparedQuery($pdoSth, ['contact_id' => [1, PDO::PARAM_INT]], true);
            expect($result)->toBeInstanceOf(PDOStatement::class)
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    );

    it(
        'execute executePreparedQuery with typing binding params with an unexpected type of param',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $pdoSth = $db->prepareQuery("select * from contact where contact_id = :contact_id");
            expect($pdoSth)->toBeInstanceOf(PDOStatement::class);
            $db->executePreparedQuery($pdoSth, ['contact_id' => [1, 'foo']], true);
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute executePreparedQuery with typing binding params with no array for the value of $bindParams',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $pdoSth = $db->prepareQuery("select * from contact where contact_id = :contact_id");
            expect($pdoSth)->toBeInstanceOf(PDOStatement::class);
            $db->executePreparedQuery($pdoSth, ['contact_id' => 1], true);
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute executePreparedQuery with typing binding params with no type for the value of $bindParams',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $pdoSth = $db->prepareQuery("select * from contact where contact_id = :contact_id");
            expect($pdoSth)->toBeInstanceOf(PDOStatement::class);
            $db->executePreparedQuery($pdoSth, ['contact_id' => [1]], true);
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    // ---------------------------------------- FETCH METHODS ----------------------------------------------

    it(
        'execute fetch with a correct query',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $pdoSth = $db->executeQuery("select * from contact");
            expect($pdoSth)->toBeInstanceOf(PDOStatement::class);
            $contact = $db->fetch($pdoSth);
            expect($contact)->toBeArray()
                ->and($contact['contact_id'])->toBe(1)
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    );

    it(
        'execute fetchAll a correct query',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $pdoSth = $db->executeQuery("select * from contact");
            expect($pdoSth)->toBeInstanceOf(PDOStatement::class);
            $contact = $db->fetchAll($pdoSth);
            expect($contact)->toBeArray()
                ->and($contact[0]['contact_id'])->toBe(1)
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    );

    it(
        'execute fetchAssociative with correct query without type without binding parameters',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $contact = $db->fetchAssociative("select * from contact where contact_id = 1");
            expect($contact)->toBeArray()
                ->and($contact['contact_id'])->toBe(1)
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    );

    it(
        'execute fetchAssociative with correct query without type',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $contact = $db->fetchAssociative("select * from contact where contact_id = :id", ['id' => 1]);
            expect($contact)->toBeArray()
                ->and($contact['contact_id'])->toBe(1)
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    );

    it(
        'execute fetchAssociative with correct query with type',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $contact = $db->fetchAssociative(
                "select * from contact where contact_id = :id",
                ['id' => [1, PDO::PARAM_INT]],
                true
            );
            expect($contact)->toBeArray()
                ->and($contact['contact_id'])->toBe(1)
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    );

    it(
        'execute fetchAssociative with correct query with type without binding parameters',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $db->fetchAssociative(
                "select * from contact where contact_id = :id",
                [],
                true
            );
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute fetchAssociative with correct query with type with only the value in binding parameters values',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $db->fetchAssociative(
                "select * from contact where contact_id = :id",
                ['id' => 1],
                true
            );
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute fetchAssociative with correct query with type without type in binding parameters values',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $db->fetchAssociative(
                "select * from contact where contact_id = :id",
                ['id' => [1]],
                true
            );
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute fetchAssociative with incorrect SELECT query',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $db->fetchAssociative("select * from", ['id' => 1]);
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute fetchAssociative with INSERT/UPDATE/DELETE query',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $db->fetchAssociative(
                "INSERT INTO contact(contact_id, contact_name, contact_alias) VALUES(:id, :name, :alias)",
                ['id' => 110, 'name' => 'foo_name', 'alias' => 'foo_alias']
            );
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute fetchAssociative with an empty query',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $db->fetchAssociative("", ['id' => 1]);
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute fetchNumeric with correct query without type without binding parameters',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $contact = $db->fetchNumeric("select * from contact where contact_id = 1");
            expect($contact)->toBeArray()
                ->and($contact[0])->toBe(1)
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    );

    it(
        'execute fetchNumeric with correct query without type',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $contact = $db->fetchNumeric("select * from contact where contact_id = :id", ['id' => 1]);
            expect($contact)->toBeArray()
                ->and($contact[0])->toBe(1)
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    );

    it(
        'execute fetchNumeric with correct query with type',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $contact = $db->fetchNumeric(
                "select * from contact where contact_id = :id",
                ['id' => [1, PDO::PARAM_INT]],
                true
            );
            expect($contact)->toBeArray()
                ->and($contact[0])->toBe(1)
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    );

    it(
        'execute fetchNumeric with correct query with type without binding parameters',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $db->fetchNumeric(
                "select * from contact where contact_id = :id",
                [],
                true
            );
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute fetchNumeric with correct query with type with only the value in binding parameters values',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $db->fetchNumeric(
                "select * from contact where contact_id = :id",
                ['id' => 1],
                true
            );
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute fetchNumeric with correct query with type without type in binding parameters values',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $db->fetchNumeric(
                "select * from contact where contact_id = :id",
                ['id' => [1]],
                true
            );
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute fetchNumeric with incorrect SELECT query',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $db->fetchNumeric("select * from", ['id' => 1]);
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute fetchNumeric with INSERT/UPDATE/DELETE query',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $db->fetchNumeric(
                "INSERT INTO contact(contact_id, contact_name, contact_alias) VALUES(:id, :name, :alias)",
                ['id' => 110, 'name' => 'foo_name', 'alias' => 'foo_alias']
            );
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute fetchNumeric with an empty query',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $db->fetchNumeric("", ['id' => 1]);
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute fetchByColumn with correct query without type without binding parameters',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $contact = $db->fetchByColumn("select * from contact where contact_id = 1");
            expect($contact)->toBeInt()
                ->and($contact)->toBe(1)
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    );

    it(
        'execute fetchByColumn with correct query without type',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $contact = $db->fetchByColumn("select * from contact where contact_id = :id", ['id' => 1]);
            expect($contact)->toBeInt()
                ->and($contact)->toBe(1)
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    );

    it(
        'execute fetchByColumn with correct query with type',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $contact = $db->fetchByColumn(
                "select * from contact where contact_id = :id",
                ['id' => [1, PDO::PARAM_INT]],
                withParamType: true
            );
            expect($contact)->toBeInt()
                ->and($contact)->toBe(1)
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    );

    it(
        'execute fetchByColumn with correct query with type without binding parameters',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $db->fetchByColumn(
                "select * from contact where contact_id = :id",
                [],
                withParamType: true
            );
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute fetchByColumn with correct query with type with only the value in binding parameters values',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $db->fetchByColumn(
                "select * from contact where contact_id = :id",
                ['id' => 1],
                withParamType: true
            );
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute fetchByColumn with correct query with type without type in binding parameters values',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $db->fetchByColumn(
                "select * from contact where contact_id = :id",
                ['id' => [1]],
                withParamType: true
            );
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute fetchByColumn with incorrect SELECT query',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $db->fetchByColumn("select * from", ['id' => 1]);
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute fetchByColumn with INSERT/UPDATE/DELETE query',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $db->fetchByColumn(
                "INSERT INTO contact(contact_id, contact_name, contact_alias) VALUES(:id, :name, :alias)",
                ['id' => 110, 'name' => 'foo_name', 'alias' => 'foo_alias']
            );
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute fetchByColumn with an empty query',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $db->fetchByColumn("", ['id' => 1]);
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute fetchAllAssociative with correct query without type without binding parameters',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $contacts = $db->fetchAllAssociative("select * from contact where contact_id = 1");
            expect($contacts)->toBeArray()
                ->and($contacts[0]['contact_id'])->toBe(1)
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    );

    it(
        'execute fetchAllAssociative with correct query without type',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $contacts = $db->fetchAllAssociative("select * from contact where contact_id = :id", ['id' => 1]);
            expect($contacts)->toBeArray()
                ->and($contacts[0]['contact_id'])->toBe(1)
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    );

    it(
        'execute fetchAllAssociative with correct query with type',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $contacts = $db->fetchAllAssociative(
                "select * from contact where contact_id = :id",
                ['id' => [1, PDO::PARAM_INT]],
                true
            );
            expect($contacts)->toBeArray()
                ->and($contacts[0]['contact_id'])->toBe(1)
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    );

    it(
        'execute fetchAllAssociative with correct query with type without binding parameters',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $db->fetchAllAssociative(
                "select * from contact where contact_id = :id",
                [],
                true
            );
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute fetchAllAssociative with correct query with type with only the value in binding parameters values',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $db->fetchAllAssociative(
                "select * from contact where contact_id = :id",
                ['id' => 1],
                true
            );
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute fetchAllAssociative with correct query with type without type in binding parameters values',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $db->fetchAllAssociative(
                "select * from contact where contact_id = :id",
                ['id' => [1]],
                true
            );
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute fetchAllAssociative with incorrect SELECT query',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $db->fetchAllAssociative("select * from", ['id' => 1]);
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute fetchAllAssociative with INSERT/UPDATE/DELETE query',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $db->fetchAllAssociative(
                "INSERT INTO contact(contact_id, contact_name, contact_alias) VALUES(:id, :name, :alias)",
                ['id' => 110, 'name' => 'foo_name', 'alias' => 'foo_alias']
            );
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute fetchAllAssociative with an empty query',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $db->fetchAllAssociative("", ['id' => 1]);
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute fetchAllNumeric with correct query without type without binding parameters',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $contacts = $db->fetchAllNumeric("select * from contact where contact_id = 1");
            expect($contacts)->toBeArray()
                ->and($contacts[0][0])->toBe(1)
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    );

    it(
        'execute fetchAllNumeric with correct query without type',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $contacts = $db->fetchAllNumeric("select * from contact where contact_id = :id", ['id' => 1]);
            expect($contacts)->toBeArray()
                ->and($contacts[0][0])->toBe(1)
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    );

    it(
        'execute fetchAllNumeric with correct query with type',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $contacts = $db->fetchAllNumeric(
                "select * from contact where contact_id = :id",
                ['id' => [1, PDO::PARAM_INT]],
                true
            );
            expect($contacts)->toBeArray()
                ->and($contacts[0][0])->toBe(1)
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    );

    it(
        'execute fetchAllNumeric with correct query with type without binding parameters',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $db->fetchAllNumeric(
                "select * from contact where contact_id = :id",
                [],
                true
            );
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute fetchAllNumeric with correct query with type with only the value in binding parameters values',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $db->fetchAllNumeric(
                "select * from contact where contact_id = :id",
                ['id' => 1],
                true
            );
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute fetchAllNumeric with correct query with type without type in binding parameters values',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $db->fetchAllNumeric(
                "select * from contact where contact_id = :id",
                ['id' => [1]],
                true
            );
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute fetchAllNumeric with incorrect SELECT query',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $db->fetchAllNumeric("select * from", ['id' => 1]);
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute fetchAllNumeric with INSERT/UPDATE/DELETE query',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $db->fetchAllNumeric(
                "INSERT INTO contact(contact_id, contact_name, contact_alias) VALUES(:id, :name, :alias)",
                ['id' => 110, 'name' => 'foo_name', 'alias' => 'foo_alias']
            );
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute fetchAllNumeric with an empty query',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $db->fetchAllNumeric("", ['id' => 1]);
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute fetchAllKeyValue with correct query without type without binding parameters',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $contacts = $db->fetchAllKeyValue("select contact_id, contact_alias from contact where contact_id = 1");
            expect($contacts)->toBeArray()
                ->and($contacts)->toHaveCount(1)->toHaveKey(1)
                ->and($contacts[1])->toBeString()->toBe('admin')
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    );

    it(
        'execute fetchAllKeyValue with correct query without type',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $contacts = $db->fetchAllKeyValue("select contact_id, contact_alias from contact where contact_id = :id", ['id' => 1]);
            expect($contacts)->toBeArray()
                ->and($contacts)->toHaveCount(1)->toHaveKey(1)
                ->and($contacts[1])->toBeString()->toBe('admin')
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    );

    it(
        'execute fetchAllKeyValue with correct query with type',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $contacts = $db->fetchAllKeyValue(
                "select contact_id, contact_alias from contact where contact_id = :id",
                ['id' => [1, PDO::PARAM_INT]],
                true
            );
            expect($contacts)->toBeArray()
                ->and($contacts)->toHaveCount(1)->toHaveKey(1)
                ->and($contacts[1])->toBeString()->toBe('admin')
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    );

    it(
        'execute fetchAllKeyValue with correct query with type without binding parameters',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $db->fetchAllKeyValue(
                "select contact_id, contact_alias from contact where contact_id = :id",
                [],
                true
            );
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute fetchAllKeyValue with correct query with type with only the value in binding parameters values',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $db->fetchAllKeyValue(
                "select contact_id, contact_alias from contact where contact_id = :id",
                ['id' => 1],
                true
            );
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute fetchAllKeyValue with correct query with type without type in binding parameters values',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $db->fetchAllKeyValue(
                "select contact_id, contact_alias from contact where contact_id = :id",
                ['id' => [1]],
                true
            );
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute fetchAllKeyValue with incorrect SELECT query',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $db->fetchAllKeyValue("select * from", ['id' => 1]);
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute fetchAllKeyValue with INSERT/UPDATE/DELETE query',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $db->fetchAllKeyValue(
                "INSERT INTO contact(contact_id, contact_name, contact_alias) VALUES(:id, :name, :alias)",
                ['id' => 110, 'name' => 'foo_name', 'alias' => 'foo_alias']
            );
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute fetchAllKeyValue with an empty query',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $db->fetchAllKeyValue("", ['id' => 1]);
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute fetchAllAssociativeIndexed with correct query without type without binding parameters',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $contacts = $db->fetchAllAssociativeIndexed("select * from contact where contact_id = 1");
            expect($contacts)->toBeArray()
                ->and($contacts)->toHaveKey(1)
                ->and($contacts[1])->toBeArray()->toHaveKey('contact_alias')
                ->and($contacts[1]['contact_alias'])->toBeString()->toBe('admin')
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    );

    it(
        'execute fetchAllAssociativeIndexed with correct query without type',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $contacts = $db->fetchAllAssociativeIndexed("select * from contact where contact_id = :id", ['id' => 1]);
            expect($contacts)->toBeArray()
                ->and($contacts)->toHaveKey(1)
                ->and($contacts[1])->toBeArray()->toHaveKey('contact_alias')
                ->and($contacts[1]['contact_alias'])->toBeString()->toBe('admin')
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    );

    it(
        'execute fetchAllAssociativeIndexed with correct query with type',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $contacts = $db->fetchAllAssociativeIndexed(
                "select * from contact where contact_id = :id",
                ['id' => [1, PDO::PARAM_INT]],
                true
            );
            expect($contacts)->toBeArray()
                ->and($contacts)->toHaveKey(1)
                ->and($contacts[1])->toBeArray()->toHaveKey('contact_alias')
                ->and($contacts[1]['contact_alias'])->toBeString()->toBe('admin')
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    );

    it(
        'execute fetchAllAssociativeIndexed with correct query with type without binding parameters',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $db->fetchAllAssociativeIndexed(
                "select * from contact where contact_id = :id",
                [],
                true
            );
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute fetchAllAssociativeIndexed with correct query with type with only the value in binding parameters values',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $db->fetchAllAssociativeIndexed(
                "select * from contact where contact_id = :id",
                ['id' => 1],
                true
            );
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute fetchAllAssociativeIndexed with correct query with type without type in binding parameters values',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $db->fetchAllAssociativeIndexed(
                "select * from contact where contact_id = :id",
                ['id' => [1]],
                true
            );
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute fetchAllAssociativeIndexed with incorrect SELECT query',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $db->fetchAllAssociativeIndexed("select * from", ['id' => 1]);
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute fetchAllAssociativeIndexed with INSERT/UPDATE/DELETE query',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $db->fetchAllAssociativeIndexed(
                "INSERT INTO contact(contact_id, contact_name, contact_alias) VALUES(:id, :name, :alias)",
                ['id' => 110, 'name' => 'foo_name', 'alias' => 'foo_alias']
            );
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute fetchAllAssociativeIndexed with an empty query',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $db->fetchAllAssociativeIndexed("", ['id' => 1]);
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    // ---------------------------------------- CUD METHODS ----------------------------------------------

    it('execute insert with correct query without type', function () use ($dbConfigCentreon): void {
        $db = DatabaseConnection::connect($dbConfigCentreon);
        $response = $db->insert(
            "INSERT INTO contact(contact_id, contact_name, contact_alias) VALUES(:id, :name, :alias)",
            ['id' => 100, 'name' => 'foo_name', 'alias' => 'foo_alias']
        );
        expect($response)->toBeInt()->toBe(1)
            ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
    });

    it('execute insert with correct query with type', function () use ($dbConfigCentreon): void {
        $db = DatabaseConnection::connect($dbConfigCentreon);
        $response = $db->insert(
            "INSERT INTO contact(contact_id, contact_name, contact_alias) VALUES(:id, :name, :alias)",
            [
                'id' => [1000, PDO::PARAM_INT],
                'name' => ['foo_name', PDO::PARAM_STR],
                'alias' => ['foo_alias', PDO::PARAM_STR]
            ],
            true
        );
        expect($response)->toBeInt()->toBe(1)
            ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
    });

    it(
        'execute insert with correct query without type without binding parameters',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $db->insert(
                "INSERT INTO contact(contact_id, contact_name, contact_alias) VALUES(1, 'name', 'alias')",
                []
            );
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute insert with correct query with type without binding parameters',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $db->insert(
                "INSERT INTO contact(contact_id, contact_name, contact_alias) VALUES(1, 'name', 'alias')",
                [],
                true
            );
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute insert with correct query with type with only the value in binding parameters values',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $db->insert(
                "INSERT INTO contact(contact_id, contact_name, contact_alias) VALUES(:id, :name, :alias)",
                ['id' => 1, 'name' => 'name', 'alias' => 'alias'],
                true
            );
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute insert with correct query with type without type in binding parameters values',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $db->insert(
                "INSERT INTO contact(contact_id, contact_name, contact_alias) VALUES(:id, :name, :alias)",
                ['id' => [1], 'name' => ['name'], 'alias' => ['alias']],
                true
            );
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute insert with incorrect query',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $db->insert(
                "INSERT INTO contact(contact_id, contact_name, contact_alias) VALUES",
                ['id' => 1, 'name' => 'name', 'alias' => 'alias']
            );
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute insert with an empty query',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $db->insert("", ['id' => 1, 'name' => 'name', 'alias' => 'alias']);
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it('execute update with correct query without type', function () use ($dbConfigCentreon): void {
        $db = DatabaseConnection::connect($dbConfigCentreon);
        $response = $db->update(
            "UPDATE contact SET contact_name = :name, contact_alias = :alias WHERE contact_id = :id",
            ['id' => 100, 'name' => 'foo_name2', 'alias' => 'foo_alias2']
        );
        expect($response)->toBeInt()->toBe(1)
            ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        $response = $db->fetchAssociative("SELECT * FROM contact WHERE contact_id = :id", ['id' => 100]);
        expect($response)->toBeArray()
            ->and($response['contact_name'])->toBe('foo_name2')
            ->and($response['contact_alias'])->toBe('foo_alias2');
    });

    it('execute update with correct query with type', function () use ($dbConfigCentreon): void {
        $db = DatabaseConnection::connect($dbConfigCentreon);
        $response = $db->update(
            "UPDATE contact SET contact_name = :name, contact_alias = :alias WHERE contact_id = :id",
            [
                'id' => [1000, PDO::PARAM_INT],
                'name' => ['foo_name2', PDO::PARAM_STR],
                'alias' => ['foo_alias2', PDO::PARAM_STR]
            ],
            true
        );
        expect($response)->toBeInt()->toBe(1)
            ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        $response = $db->fetchAssociative(
            "SELECT * FROM contact WHERE contact_id = :id",
            ['id' => [1000, PDO::PARAM_INT]],
            true
        );
        expect($response)->toBeArray()
            ->and($response['contact_name'])->toBe('foo_name2')
            ->and($response['contact_alias'])->toBe('foo_alias2');
    });

    it(
        'execute update with correct query without type without binding parameters',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $db->update(
                "UPDATE contact SET contact_name = :name, contact_alias = :alias WHERE contact_id = :id",
                []
            );
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute update with correct query with type without binding parameters',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $db->update(
                "UPDATE contact SET contact_name = :name, contact_alias = :alias WHERE contact_id = :id",
                [],
                true
            );
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute update with correct query with type with only the value in binding parameters values',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $db->update(
                "UPDATE contact SET contact_name = :name, contact_alias = :alias WHERE contact_id = :id",
                ['id' => 1, 'name' => 'name', 'alias' => 'alias'],
                true
            );
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute update with correct query with type without type in binding parameters values',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $db->update(
                "UPDATE contact SET contact_name = :name, contact_alias = :alias WHERE contact_id = :id",
                ['id' => [1], 'name' => ['name'], 'alias' => ['alias']],
                true
            );
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute update with incorrect query',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $db->update("UPDATE contact SET ", ['id' => 1, 'name' => 'name', 'alias' => 'alias']);
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute update with an empty query',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $db->update("", ['id' => 1, 'name' => 'name', 'alias' => 'alias']);
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it('execute delete with correct query without type', function () use ($dbConfigCentreon): void {
        $db = DatabaseConnection::connect($dbConfigCentreon);
        $response = $db->delete("DELETE FROM contact WHERE contact_id = :id", ['id' => 100]);
        expect($response)->toBeInt()->toBe(1)
            ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        $response = $db->fetchAssociative("SELECT * FROM contact WHERE contact_id = :id", ['id' => 100]);
        expect($response)->toBeFalse();
    });

    it('execute delete with correct query with type', function () use ($dbConfigCentreon): void {
        $db = DatabaseConnection::connect($dbConfigCentreon);
        $response = $db->delete(
            "DELETE FROM contact WHERE contact_id = :id",
            ['id' => [1000, PDO::PARAM_INT]],
            true
        );
        expect($response)->toBeInt()->toBe(1)
            ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        $response = $db->fetchAssociative(
            "SELECT * FROM contact WHERE contact_id = :id",
            ['id' => [1000, PDO::PARAM_INT]],
            true
        );
        expect($response)->toBeFalse();
    });

    it(
        'execute delete with correct query without type without binding parameters',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $db->delete("DELETE FROM contact WHERE contact_id = :id", []);
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute delete with correct query with type without binding parameters',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $db->delete("DELETE FROM contact WHERE contact_id = :id", [], true);
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute delete with correct query with type with only the value in binding parameters values',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $db->delete("DELETE FROM contact WHERE contact_id = :id", ['id' => 1], true);
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute delete with correct query with type without type in binding parameters values',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $db->delete("DELETE FROM contact WHERE contact_id = :id", ['id' => [1]], true);
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute delete with incorrect query',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $db->delete("DELETE FROM contact WHERE contact_id =", ['id' => 1]);
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute delete with an empty query',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $db->delete("", ['id' => 1]);
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it('execute several insert with correct query without type', function () use ($dbConfigCentreon): void {
        $db = DatabaseConnection::connect($dbConfigCentreon);
        $response = $db->iterateInsert(
            "contact",
            ['contact_id', 'contact_name', 'contact_alias'],
            [
                ['contact_id' => 101, 'contact_name' => 'foo_name1', 'contact_alias' => 'foo_alias1'],
                ['contact_id' => 102, 'contact_name' => 'foo_name2', 'contact_alias' => 'foo_alias2'],
                ['contact_id' => 103, 'contact_name' => 'foo_name3', 'contact_alias' => 'foo_alias3']
            ]
        );
        expect($response)->toBeInt()->toBe(3)
            ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        $response = $db->fetchAllAssociative(
            "SELECT contact_id FROM contact WHERE contact_id IN (:id1, :id2, :id3)",
            ['id1' => 101, 'id2' => 102, 'id3' => 103]
        );
        expect($response)->toBeArray()
            ->and($response[0]['contact_id'])->toBe(101)
            ->and($response[1]['contact_id'])->toBe(102)
            ->and($response[2]['contact_id'])->toBe(103);
        $db->delete(
            "DELETE FROM contact WHERE contact_id IN (:id1, :id2, :id3)",
            ['id1' => 101, 'id2' => 102, 'id3' => 103]
        );
        expect(
            $db->fetchAllAssociative(
                "SELECT * FROM contact WHERE contact_id IN (:id1, :id2, :id3)",
                ['id1' => 101, 'id2' => 102, 'id3' => 103]
            )
        )->toBeArray()->toBeEmpty();
    });

    it('execute several insert with correct query with type', function () use ($dbConfigCentreon): void {
        $db = DatabaseConnection::connect($dbConfigCentreon);
        $response = $db->iterateInsert(
            "contact",
            ['contact_id', 'contact_name', 'contact_alias'],
            [
                [
                    'contact_id' => [101, PDO::PARAM_INT],
                    'contact_name' => ['foo_name1', PDO::PARAM_STR],
                    'contact_alias' => ['foo_alias1', PDO::PARAM_STR]
                ],
                [
                    'contact_id' => [102, PDO::PARAM_INT],
                    'contact_name' => ['foo_name2', PDO::PARAM_STR],
                    'contact_alias' => ['foo_alias2', PDO::PARAM_STR]
                ],
                [
                    'contact_id' => [103, PDO::PARAM_INT],
                    'contact_name' => ['foo_name3', PDO::PARAM_STR],
                    'contact_alias' => ['foo_alias3', PDO::PARAM_STR]
                ]
            ],
            true
        );
        expect($response)->toBeInt()->toBe(3)
            ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        $response = $db->fetchAllAssociative(
            "SELECT contact_id FROM contact WHERE contact_id IN (:id1, :id2, :id3)",
            ['id1' => [101, PDO::PARAM_INT], 'id2' => [102, PDO::PARAM_INT], 'id3' => [103, PDO::PARAM_INT]],
            true
        );
        expect($response)->toBeArray()
            ->and($response[0]['contact_id'])->toBe(101)
            ->and($response[1]['contact_id'])->toBe(102)
            ->and($response[2]['contact_id'])->toBe(103);
        $db->delete(
            "DELETE FROM contact WHERE contact_id IN (:id1, :id2, :id3)",
            ['id1' => [101, PDO::PARAM_INT], 'id2' => [102, PDO::PARAM_INT], 'id3' => [103, PDO::PARAM_INT]],
            true
        );
        expect(
            $db->fetchAllAssociative(
                "SELECT * FROM contact WHERE contact_id IN (:id1, :id2, :id3)",
                ['id1' => [101, PDO::PARAM_INT], 'id2' => [102, PDO::PARAM_INT], 'id3' => [103, PDO::PARAM_INT]],
                true
            )
        )->toBeArray()->toBeEmpty();
    });

    it(
        'execute iterateInsert with correct query without type without binding parameters',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $response = $db->iterateInsert("contact", ['contact_id', 'contact_name', 'contact_alias'], []);
            expect($response)->toBeInt()->toBe(0)
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute iterateInsert with correct query with type without binding parameters',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $response = $db->iterateInsert("contact", ['contact_id', 'contact_name', 'contact_alias'], [], true);
            expect($response)->toBeInt()->toBe(0)
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute iterateInsert with correct query with type with only the value in binding parameters values',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $db->iterateInsert(
                "contact",
                ['contact_id', 'contact_name', 'contact_alias'],
                [
                    ['contact_id' => 101, 'contact_name' => 'foo_name1', 'contact_alias' => 'foo_alias1']
                ],
                true
            );
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute iterateInsert with correct query with type without type in binding parameters values',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $db->iterateInsert(
                "contact",
                ['contact_id', 'contact_name', 'contact_alias'],
                [
                    [
                        'contact_id' => [101],
                        'contact_name' => ['foo_name1'],
                        'contact_alias' => ['foo_alias1']
                    ]
                ],
                true
            );
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute iterateInsert with empty table',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $db->iterateInsert("", ['contact_id', 'contact_name', 'contact_alias'], [
                ['contact_id' => 101, 'contact_name' => 'foo_name1', 'contact_alias' => 'foo_alias1']
            ]);
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute iterateInsert with empty columns',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $db->iterateInsert("contact", [], [
                ['contact_id' => 101, 'contact_name' => 'foo_name1', 'contact_alias' => 'foo_alias1']
            ]);
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    // ---------------------------------------- ITERATE METHODS ----------------------------------------------

    it(
        'execute iterateAssociative with correct query without type without binding parameters',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $contacts = $db->iterateAssociative("select * from contact where contact_id = 1");
            foreach ($contacts as $contact) {
                expect($contact)->toBeArray()
                    ->and($contact['contact_id'])->toBe(1);
            }
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    );

    it(
        'execute iterateAssociative with correct query without type',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $contacts = $db->iterateAssociative("select * from contact where contact_id = :id", ['id' => 1]);
            foreach ($contacts as $contact) {
                expect($contact)->toBeArray()
                    ->and($contact['contact_id'])->toBe(1);
            }
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    );

    it(
        'execute iterateAssociative with correct query with type',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $contacts = $db->iterateAssociative(
                "select * from contact where contact_id = :id",
                ['id' => [1, PDO::PARAM_INT]],
                true
            );
            foreach ($contacts as $contact) {
                expect($contact)->toBeArray()
                    ->and($contact['contact_id'])->toBe(1);
            }
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    );

    it(
        'execute iterateAssociative with correct query with type without binding parameters',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $contacts = $db->iterateAssociative(
                "select * from contact where contact_id = :id",
                [],
                true
            );
            foreach ($contacts as $contact) {
            }
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute iterateAssociative with correct query with type with only the value in binding parameters values',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $contacts = $db->iterateAssociative(
                "select * from contact where contact_id = :id",
                ['id' => 1],
                true
            );
            foreach ($contacts as $contact) {
            }
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute iterateAssociative with correct query with type without type in binding parameters values',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $contacts = $db->iterateAssociative(
                "select * from contact where contact_id = :id",
                ['id' => [1]],
                true
            );
            foreach ($contacts as $contact) {
            }
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute iterateAssociative with incorrect SELECT query',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $contacts = $db->iterateAssociative("select * from", ['id' => 1]);
            foreach ($contacts as $contact) {
            }
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute iterateAssociative with INSERT/UPDATE/DELETE query',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $contacts = $db->iterateAssociative(
                "INSERT INTO contact(contact_id, contact_name, contact_alias) VALUES(:id, :name, :alias)",
                ['id' => 110, 'name' => 'foo_name', 'alias' => 'foo_alias']
            );
            foreach ($contacts as $contact) {
            }
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute iterateAssociative with an empty query',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $contacts = $db->iterateAssociative("", ['id' => 1]);
            foreach ($contacts as $contact) {
            }
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute iterateNumeric with correct query without type without binding parameters',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $contacts = $db->iterateNumeric("select * from contact where contact_id = 1");
            foreach ($contacts as $contact) {
                expect($contact)->toBeArray()
                    ->and($contact[0])->toBe(1);
            }
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    );

    it(
        'execute iterateNumeric with correct query without type',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $contacts = $db->iterateNumeric("select * from contact where contact_id = :id", ['id' => 1]);
            foreach ($contacts as $contact) {
                expect($contact)->toBeArray()
                    ->and($contact[0])->toBe(1);
            }
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    );

    it(
        'execute iterateNumeric with correct query with type',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $contacts = $db->iterateNumeric(
                "select * from contact where contact_id = :id",
                ['id' => [1, PDO::PARAM_INT]],
                true
            );
            foreach ($contacts as $contact) {
                expect($contact)->toBeArray()
                    ->and($contact[0])->toBe(1);
            }
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    );

    it(
        'execute iterateNumeric with correct query with type without binding parameters',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $contacts = $db->iterateNumeric(
                "select * from contact where contact_id = :id",
                [],
                true
            );
            foreach ($contacts as $contact) {
            }
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute iterateNumeric with correct query with type with only the value in binding parameters values',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $contacts = $db->iterateNumeric(
                "select * from contact where contact_id = :id",
                ['id' => 1],
                true
            );
            foreach ($contacts as $contact) {
            }
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute iterateNumeric with correct query with type without type in binding parameters values',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $contacts = $db->iterateNumeric(
                "select * from contact where contact_id = :id",
                ['id' => [1]],
                true
            );
            foreach ($contacts as $contact) {
            }
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute iterateNumeric with incorrect SELECT query',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $contacts = $db->iterateNumeric("select * from", ['id' => 1]);
            foreach ($contacts as $contact) {
            }
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute iterateNumeric with INSERT/UPDATE/DELETE query',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $contacts = $db->iterateNumeric(
                "INSERT INTO contact(contact_id, contact_name, contact_alias) VALUES(:id, :name, :alias)",
                ['id' => 110, 'name' => 'foo_name', 'alias' => 'foo_alias']
            );
            foreach ($contacts as $contact) {
            }
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute iterateNumeric with an empty query',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $contacts = $db->iterateNumeric("", ['id' => 1]);
            foreach ($contacts as $contact) {
            }
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute iterateByColumn with correct query without type without binding parameters',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $contacts = $db->iterateByColumn("select * from contact where contact_id = 1");
            foreach ($contacts as $contact) {
                expect($contact)->toBeInt()->toBe(1);
            }
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    );

    it(
        'execute iterateByColumn with correct query without type',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $contacts = $db->iterateByColumn("select * from contact where contact_id = :id", ['id' => 1]);
            foreach ($contacts as $contact) {
                expect($contact)->toBeInt()->toBe(1);
            }
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    );

    it(
        'execute iterateByColumn with correct query with type',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $contacts = $db->iterateByColumn(
                "select * from contact where contact_id = :id",
                ['id' => [1, PDO::PARAM_INT]],
                withParamType: true
            );
            foreach ($contacts as $contact) {
                expect($contact)->toBeInt()->toBe(1);
            }
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    );

    it(
        'execute iterateByColumn with correct query with type without binding parameters',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $contacts = $db->iterateByColumn(
                "select * from contact where contact_id = :id",
                withParamType: true
            );
            foreach ($contacts as $contact) {}
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute iterateByColumn with correct query with type with only the value in binding parameters values',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $contacts = $db->iterateByColumn(
                "select * from contact where contact_id = :id",
                ['id' => 1],
                withParamType: true
            );
            foreach ($contacts as $contact) {}
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute iterateByColumn with correct query with type without type in binding parameters values',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $contacts = $db->iterateByColumn(
                "select * from contact where contact_id = :id",
                ['id' => [1]],
                withParamType: true
            );
            foreach ($contacts as $contact) {}
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute iterateByColumn with incorrect SELECT query',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $contacts = $db->iterateByColumn("select * from", ['id' => 1]);
            foreach ($contacts as $contact) {}
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute iterateByColumn with INSERT/UPDATE/DELETE query',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $contacts = $db->iterateByColumn(
                "INSERT INTO contact(contact_id, contact_name, contact_alias) VALUES(:id, :name, :alias)",
                ['id' => 110, 'name' => 'foo_name', 'alias' => 'foo_alias']
            );
            foreach ($contacts as $contact) {}
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute iterateByColumn with an empty query',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $contacts = $db->iterateByColumn("", ['id' => 1]);
            foreach ($contacts as $contact) {}
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute iterateKeyValue with correct query without type without binding parameters',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $contacts = $db->iterateKeyValue("select contact_id, contact_alias from contact where contact_id = 1");
            foreach ($contacts as $contact) {
                expect($contact)->toBeArray()->toHaveCount(1)
                    ->and($contact)->toHaveKey(1)
                    ->and($contact[1])->toBeString()->toBe('admin');
            }
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    );

    it(
        'execute iterateKeyValue with correct query without type',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $contacts = $db->iterateKeyValue(
                "select contact_id, contact_alias from contact where contact_id = :id",
                ['id' => 1]
            );
            foreach ($contacts as $contact) {
                expect($contact)->toBeArray()->toHaveCount(1)
                    ->and($contact)->toHaveKey(1)
                    ->and($contact[1])->toBeString()->toBe('admin');
            }
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    );

    it(
        'execute iterateKeyValue with correct query with type',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $contacts = $db->iterateKeyValue(
                "select contact_id, contact_alias from contact where contact_id = :id",
                ['id' => [1, PDO::PARAM_INT]],
                true
            );
            foreach ($contacts as $contact) {
                expect($contact)->toBeArray()->toHaveCount(1)
                    ->and($contact)->toHaveKey(1)
                    ->and($contact[1])->toBeString()->toBe('admin');
            }
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    );

    it(
        'execute iterateKeyValue with correct query with type without binding parameters',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $contacts = $db->iterateKeyValue(
                "select contact_id, contact_alias from contact where contact_id = :id",
                [],
                true
            );
            foreach ($contacts as $contact) {
            }
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute iterateKeyValue with correct query with type with only the value in binding parameters values',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $contacts = $db->iterateKeyValue(
                "select contact_id, contact_alias from contact where contact_id = :id",
                ['id' => 1],
                true
            );
            foreach ($contacts as $contact) {
            }
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute iterateKeyValue with correct query with type without type in binding parameters values',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $contacts = $db->iterateKeyValue(
                "select contact_id, contact_alias from contact where contact_id = :id",
                ['id' => [1]],
                true
            );
            foreach ($contacts as $contact) {
            }
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute iterateKeyValue with incorrect SELECT query',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $contacts = $db->iterateKeyValue("select contact_id, contact_alias from", ['id' => 1]);
            foreach ($contacts as $contact) {
            }
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute iterateKeyValue with INSERT/UPDATE/DELETE query',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $contacts = $db->iterateKeyValue(
                "INSERT INTO contact(contact_id, contact_name, contact_alias) VALUES(:id, :name, :alias)",
                ['id' => 110, 'name' => 'foo_name', 'alias' => 'foo_alias']
            );
            foreach ($contacts as $contact) {
            }
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute iterateKeyValue with an empty query',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $contacts = $db->iterateKeyValue("", ['id' => 1]);
            foreach ($contacts as $contact) {
            }
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute iterateAssociativeIndexed with correct query without type without binding parameters',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $contacts = $db->iterateAssociativeIndexed("select * from contact where contact_id = 1");
            foreach ($contacts as $contact) {
                expect($contact)->toBeArray()->toHaveKey(1)
                    ->and($contact[1])->toBeArray()->toHaveKey('contact_alias', 'admin');
            }
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    );

    it(
        'execute iterateAssociativeIndexed with correct query without type',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $contacts = $db->iterateAssociativeIndexed("select * from contact where contact_id = :id", ['id' => 1]);
            foreach ($contacts as $contact) {
                expect($contact)->toBeArray()->toHaveKey(1)
                    ->and($contact[1])->toBeArray()->toHaveKey('contact_alias', 'admin');
            }
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    );

    it(
        'execute iterateAssociativeIndexed with correct query with type',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $contacts = $db->iterateAssociativeIndexed(
                "select * from contact where contact_id = :id",
                ['id' => [1, PDO::PARAM_INT]],
                true
            );
            foreach ($contacts as $contact) {
                expect($contact)->toBeArray()->toHaveKey(1)
                    ->and($contact[1])->toBeArray()->toHaveKey('contact_alias', 'admin');
            }
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    );

    it(
        'execute iterateAssociativeIndexed with correct query with type without binding parameters',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $contacts = $db->iterateAssociativeIndexed(
                "select * from contact where contact_id = :id",
                [],
                true
            );
            foreach ($contacts as $contact) {
            }
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute iterateAssociativeIndexed with correct query with type with only the value in binding parameters values',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $contacts = $db->iterateAssociativeIndexed(
                "select * from contact where contact_id = :id",
                ['id' => 1],
                true
            );
            foreach ($contacts as $contact) {
            }
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute iterateAssociativeIndexed with correct query with type without type in binding parameters values',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $contacts = $db->iterateAssociativeIndexed(
                "select * from contact where contact_id = :id",
                ['id' => [1]],
                true
            );
            foreach ($contacts as $contact) {
            }
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute iterateAssociativeIndexed with incorrect SELECT query',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $contacts = $db->iterateAssociativeIndexed("select * from", ['id' => 1]);
            foreach ($contacts as $contact) {
            }
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute iterateAssociativeIndexed with INSERT/UPDATE/DELETE query',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $contacts = $db->iterateAssociativeIndexed(
                "INSERT INTO contact(contact_id, contact_name, contact_alias) VALUES(:id, :name, :alias)",
                ['id' => 110, 'name' => 'foo_name', 'alias' => 'foo_alias']
            );
            foreach ($contacts as $contact) {
            }
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute iterateAssociativeIndexed with an empty query',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $contacts = $db->iterateAssociativeIndexed("", ['id' => 1]);
            foreach ($contacts as $contact) {
            }
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);


    // ---------------------------------------- DDL METHODS ----------------------------------------------

    it('execute updateDatabase with correct query', function () use ($dbConfigCentreon): void {
        $db = DatabaseConnection::connect($dbConfigCentreon);
        $response = $db->updateDatabase(
            "CREATE TABLE IF NOT EXISTS test_table (id INT PRIMARY KEY, name VARCHAR(255))"
        );
        expect($response)->toBeTrue();
        $stmt = $db->query("SHOW TABLES LIKE 'test_table'");
        $response = $stmt->fetch();
        expect($response)->toBeArray()->toHaveCount(1);
        $response = $db->updateDatabase("DROP TABLE test_table");
        expect($response)->toBeTrue();
        $stmt = $db->query("SHOW TABLES LIKE 'test_table'");
        $response = $stmt->fetch();
        expect($response)->toBeFalse();
    });

    it('execute updateDatabase with incorrect query', function () use ($dbConfigCentreon): void {
        $db = DatabaseConnection::connect($dbConfigCentreon);
        $response = $db->updateDatabase("CREATE TABLE IF NOT EXISTS test_table");
        expect($response)->toBeFalse();
    })->throws(DatabaseConnectionException::class);

    it('execute updateDatabase with an empty query', function () use ($dbConfigCentreon): void {
        $db = DatabaseConnection::connect($dbConfigCentreon);
        $response = $db->updateDatabase("");
        expect($response)->toBeFalse();
    })->throws(DatabaseConnectionException::class);

    it('execute updateDatabase with a no DDL query', function () use ($dbConfigCentreon): void {
        $db = DatabaseConnection::connect($dbConfigCentreon);
        $response = $db->updateDatabase("SELECT * FROM contact");
        expect($response)->toBeFalse();
    })->throws(DatabaseConnectionException::class);

    // ----------------------------------------- TRANSACTIONS -----------------------------------------

    it('execute startTransaction with success', function () use ($dbConfigCentreon): void {
        $db = DatabaseConnection::connect($dbConfigCentreon);
        $db->startTransaction();
        expect($db->isTransactionActive())->toBeTrue();
    });

    it('execute commit with success', function () use ($dbConfigCentreon): void {
        $db = DatabaseConnection::connect($dbConfigCentreon);
        $db->startTransaction();
        $response = $db->commit();
        expect($response)->toBeTrue()
            ->and($db->isTransactionActive())->toBeFalse();
    });

    it('execute rollback with success', function () use ($dbConfigCentreon): void {
        $db = DatabaseConnection::connect($dbConfigCentreon);
        $db->startTransaction();
        $response = $db->rollback();
        expect($response)->toBeTrue()
            ->and($db->isTransactionActive())->toBeFalse();
    });

    // ------------------------------------- UNBUFFERED QUERIES -----------------------------------------

    it('execute unbufferedQuery with correct query', function () use ($dbConfigCentreon): void {
        $db = DatabaseConnection::connect($dbConfigCentreon);
        $db->startUnbufferedQuery();
        var_dump($db->getAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY));
        $stmt = $db->executeQuery("SELECT * FROM contact WHERE contact_id = 1");
        expect($stmt)->toBeInstanceOf(PDOStatement::class);
        $contact = $stmt->fetch();
        expect($contact)->toBeArray()->toHaveKey('contact_id', 1)
            ->and($db->isUnbufferedQueryActive())->toBeTrue()
            ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class)
            ->and($db->getAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY))->toBe(0);
        $db->stopUnbufferedQuery();
        expect($db->isUnbufferedQueryActive())->toBeFalse()
            ->and($db->getAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY))->toBe(1);
    });

    it(
        'stop unbuffered query without start unbuffered query',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
            $db->stopUnbufferedQuery();
        }
    )->throws(DatabaseConnectionException::class);

    // ---------------------------------------- execute ----------------------------------------------

    it(
        'execute execute with correct query',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $pdoSth = $db->prepareQuery("select * from contact where contact_id = :contact_id");
            expect($pdoSth)->toBeInstanceOf(PDOStatement::class);
            $successExecute = $db->execute($pdoSth, ['contact_id' => 1]);
            expect($successExecute)->toBeTrue()
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    );

    it(
        'execute execute with an empty array for params',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $pdoSth = $db->prepareQuery("select * from contact where contact_id = :contact_id");
            expect($pdoSth)->toBeInstanceOf(PDOStatement::class);
            $db->execute($pdoSth, []);
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    // ---------------------------------------- executeQuery ----------------------------------------------

    it(
        'execute executeQuery with a correct query with associative fetch mode',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $pdoSth = $db->executeQuery("SELECT * FROM contact WHERE contact_id = 1", PDO::FETCH_ASSOC);
            expect($pdoSth)->toBeInstanceOf(PDOStatement::class);
            $contact = $pdoSth->fetch();
            expect($contact)->toBeArray()->toHaveKey('contact_id', 1)
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    );

    it(
        'execute executeQuery with a correct query with numeric fetch mode',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $pdoSth = $db->executeQuery("SELECT * FROM contact WHERE contact_id = 1", PDO::FETCH_NUM);
            expect($pdoSth)->toBeInstanceOf(PDOStatement::class);
            $contact = $pdoSth->fetch();
            expect($contact)->toBeArray()->toHaveKey(0, 1)
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    );

    it(
        'execute executeQuery with a correct query with column fetch mode',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $pdoSth = $db->executeQuery("SELECT * FROM contact WHERE contact_id = 1", PDO::FETCH_COLUMN, [0]);
            expect($pdoSth)->toBeInstanceOf(PDOStatement::class);
            $contact = $pdoSth->fetch();
            expect($contact)->toBeInt()->toBe(1)
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    );

    it(
        'execute executeQuery with a correct query with object fetch mode',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $pdoSth = $db->executeQuery("SELECT * FROM contact WHERE contact_id = 1", PDO::FETCH_OBJ);
            expect($pdoSth)->toBeInstanceOf(PDOStatement::class);
            $contact = $pdoSth->fetch();
            expect($contact)->toBeObject()->toHaveProperty('contact_id', 1)
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    );

    it(
        'execute executeQuery with an incorrect query',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $db->executeQuery("foo");
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute executeQuery with an empty query',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $db->executeQuery("");
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    // ---------------------------------------- makeBindValue ----------------------------------------------

    it(
        'execute makeBindValue with success',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $pdoSth = $db->prepareQuery("select * from contact where contact_id = :contact_id");
            expect($pdoSth)->toBeInstanceOf(PDOStatement::class);
            $successBind = $db->makeBindValue($pdoSth, 'contact_id', 1, PDO::PARAM_INT);
            expect($successBind)->toBeTrue();
            $successExecute = $db->execute($pdoSth);
            expect($successExecute)->toBeTrue()
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    );

    it(
        'execute makeBindValue with an empty param name',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $pdoSth = $db->prepareQuery("select * from contact where contact_id = :contact_id");
            expect($pdoSth)->toBeInstanceOf(PDOStatement::class);
            $db->makeBindValue($pdoSth, '', 1, PDO::PARAM_INT);
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute makeBindValue with a type that doesnt exist',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $pdoSth = $db->prepareQuery("select * from contact where contact_id = :contact_id");
            expect($pdoSth)->toBeInstanceOf(PDOStatement::class);
            $db->makeBindValue($pdoSth, 'contact_id', 1, 999999);
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    // ---------------------------------------- makeBindParam ----------------------------------------------

    it(
        'execute makeBindParam with success',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $pdoSth = $db->prepareQuery("select * from contact where contact_id = :contact_id");
            expect($pdoSth)->toBeInstanceOf(PDOStatement::class);
            $contactID = 1;
            $successBind = $db->makeBindParam($pdoSth, 'contact_id', $contactID, PDO::PARAM_INT);
            expect($successBind)->toBeTrue();
            $successExecute = $db->execute($pdoSth);
            expect($successExecute)->toBeTrue()
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    );

    it(
        'execute makeBindParam with an empty param name',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $pdoSth = $db->prepareQuery("select * from contact where contact_id = :contact_id");
            expect($pdoSth)->toBeInstanceOf(PDOStatement::class);
            $contactID = 1;
            $db->makeBindParam($pdoSth, '', $contactID, PDO::PARAM_INT);
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    it(
        'execute makeBindParam with a type that doesnt exist',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $pdoSth = $db->prepareQuery("select * from contact where contact_id = :contact_id");
            expect($pdoSth)->toBeInstanceOf(PDOStatement::class);
            $contactID = 1;
            $db->makeBindParam($pdoSth, 'contact_id', $contactID, 9999999);
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(PDOStatement::class);
        }
    )->throws(DatabaseConnectionException::class);

    // ---------------------------------------- OTHER METHODS ----------------------------------------------

    it(
        'execute closeQuery with success',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $pdoSth = $db->prepareQuery("select * from contact where contact_id = :contact_id");
            $pdoSth->execute(['contact_id' => 1]);
            $successClose = $db->closeQuery($pdoSth);
            expect($successClose)->toBeTrue();
        }
    );

    it(
        'execute escapeString with success',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::connect($dbConfigCentreon);
            $escapedString = $db->escapeString('foo');
            expect($escapedString)->toBeString()->toBe('\'foo\'');
            $escapedString = $db->escapeString('1');
            expect($escapedString)->toBeString()->toBe('\'1\'');
        }
    );

} else {
    it('no centreon database available for testing the DatabaseConnection connector, so these tests were ignored');
}
