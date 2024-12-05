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

namespace Tests\www\class;

use CentreonDB;
use CentreonDbConfig;
use CentreonDbException;
use CentreonDBStatement;
use PDO;
use PDOStatement;
use ValueError;

/**
 * @param string $nameEnvVar
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
    $dbConfigCentreon = new CentreonDbConfig(
        dbHost: $dbHost,
        dbUser: $dbUser,
        dbPassword: $dbPassword,
        dbName: 'centreon',
        dbPort: 3306
    );
    $dbConfigCentreonStorage = new CentreonDbConfig(
        dbHost: $dbHost,
        dbUser: $dbUser,
        dbPassword: $dbPassword,
        dbName: 'centreon_storage',
        dbPort: 3306
    );
}

/**
 * @param CentreonDbConfig $dbConfig
 * @return bool
 */
function hasConnectionDb(CentreonDbConfig $dbConfig): bool
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
        'connect to centreon database with CentreonDB constructor',
        function () use ($dbConfigCentreon) {
            $db = new CentreonDB(dbLabel: CentreonDB::LABEL_DB_CONFIGURATION, dbConfig: $dbConfigCentreon);
            expect($db)->toBeInstanceOf(CentreonDB::class);
            $dbName = $db->executeQuery("select database()")->fetchColumn();
            expect($dbName)->toBe('centreon')
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    );

    it(
        'connect to centreon database with CentreonDB::connectToCentreonDb factory',
        function () use ($dbConfigCentreon) {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            expect($db)->toBeInstanceOf(CentreonDB::class);
            $dbName = $db->executeQuery("select database()")->fetchColumn();
            expect($dbName)->toBe('centreon')
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    );

    it(
        'execute query with a correct query',
        function () use ($dbConfigCentreon) {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $pdoSth = $db->executeQuery("select database()");
            expect($pdoSth)->toBeInstanceOf(PDOStatement::class);
            $dbName = $pdoSth->fetchColumn();
            expect($dbName)->toBe('centreon')
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    );

    it(
        'execute query with an incorrect query',
        function () use ($dbConfigCentreon) {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $db->executeQuery("foo");
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    )->throws(CentreonDbException::class);

    it(
        'execute query with an empty query',
        function () use ($dbConfigCentreon) {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $db->executeQuery("");
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    )->throws(CentreonDbException::class);

    it(
        'prepare query with a correct prepared query',
        function () use ($dbConfigCentreon) {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $pdoSth = $db->prepareQuery("select * from contact where contact_id = :contact_id");
            expect($pdoSth)->toBeInstanceOf(PDOStatement::class)
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    );

    it(
        'prepare query with an empty prepared query',
        function () use ($dbConfigCentreon) {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $db->prepareQuery("");
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    )->throws(CentreonDbException::class);

    it(
        'execute prepared query with a correct prepared query ',
        function () use ($dbConfigCentreon) {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $pdoSth = $db->prepareQuery("select * from contact where contact_id = :contact_id");
            expect($pdoSth)->toBeInstanceOf(PDOStatement::class);
            $result = $db->executePreparedQuery($pdoSth, ['contact_id' => 1]);
            expect($result)->toBeTrue()
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    );

    it(
        'execute prepared query with an empty array for $bindParams',
        function () use ($dbConfigCentreon) {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $pdoSth = $db->prepareQuery("select * from contact where contact_id = :contact_id");
            expect($pdoSth)->toBeInstanceOf(PDOStatement::class);
            $db->executePreparedQuery($pdoSth, []);
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    )->throws(CentreonDbException::class);

    it(
        'execute prepared query with an incorrect prepared query',
        function () use ($dbConfigCentreon) {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $pdoSth = $db->prepareQuery("foo");
            expect($pdoSth)->toBeInstanceOf(PDOStatement::class);
            $db->executePreparedQuery($pdoSth, ['contact_id' => 1]);
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    )->throws(CentreonDbException::class);

    it(
        'execute prepared query with typing binding params with a correct prepared query',
        function () use ($dbConfigCentreon) {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $pdoSth = $db->prepareQuery("select * from contact where contact_id = :contact_id");
            expect($pdoSth)->toBeInstanceOf(PDOStatement::class);
            $result = $db->executePreparedQuery($pdoSth, ['contact_id' => [1, PDO::PARAM_INT]], true);
            expect($result)->toBeTrue()
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    );

    it(
        'execute prepared query with typing binding params with an unexpected type of param',
        function () use ($dbConfigCentreon) {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $pdoSth = $db->prepareQuery("select * from contact where contact_id = :contact_id");
            expect($pdoSth)->toBeInstanceOf(PDOStatement::class);
            $db->executePreparedQuery($pdoSth, ['contact_id' => [1, 'foo']], true);
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    )->throws(CentreonDbException::class);

    it(
        'execute prepared query with typing binding params with no array for the value of $bindParams',
        function () use ($dbConfigCentreon) {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $pdoSth = $db->prepareQuery("select * from contact where contact_id = :contact_id");
            expect($pdoSth)->toBeInstanceOf(PDOStatement::class);
            $db->executePreparedQuery($pdoSth, ['contact_id' => 1], true);
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    )->throws(CentreonDbException::class);

    it(
        'execute prepared query with typing binding params with no type for the value of $bindParams',
        function () use ($dbConfigCentreon) {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $pdoSth = $db->prepareQuery("select * from contact where contact_id = :contact_id");
            expect($pdoSth)->toBeInstanceOf(PDOStatement::class);
            $db->executePreparedQuery($pdoSth, ['contact_id' => [1]], true);
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    )->throws(CentreonDbException::class);

    it(
        'execute executeQueryFetchAll query with a correct query',
        function () use ($dbConfigCentreon) {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $contacts = $db->executeQueryFetchAll("select * from contact");
            expect($contacts)->toBeArray()
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    );

    it(
        'execute executeQueryFetchAll query with an empty query',
        function () use ($dbConfigCentreon) {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $db->executeQueryFetchAll("");
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    )->throws(CentreonDbException::class);

    it(
        'execute executeQueryFetchColumn query with a correct query',
        function () use ($dbConfigCentreon) {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $contactsIds = $db->executeQueryFetchColumn("select * from contact");
            expect($contactsIds)->toBeArray()
                ->and($contactsIds[0])->toBeInt()
                ->and($contactsIds[0])->toBe(1)
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    );

    it(
        'execute executeQueryFetchColumn query with a column option with a correct query',
        function () use ($dbConfigCentreon) {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $alias = $db->executeQueryFetchColumn("select * from contact", 4);
            expect($alias)->toBeArray()
                ->and($alias[0])->toBeString()
                ->and($alias[0])->toBe('admin')
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    );

    it(
        'execute executeQueryFetchColumn query with an empty query',
        function () use ($dbConfigCentreon) {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $db->executeQueryFetchColumn("");
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    )->throws(CentreonDbException::class);

    it(
        'execute fetch with a correct query',
        function () use ($dbConfigCentreon) {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $pdoSth = $db->executeQuery("select * from contact");
            expect($pdoSth)->toBeInstanceOf(PDOStatement::class);
            $contact = $db->fetch($pdoSth);
            expect($contact)->toBeArray()
                ->and($contact['contact_id'])->toBe(1)
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    );

    it(
        'execute fetchAll a correct query',
        function () use ($dbConfigCentreon) {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $pdoSth = $db->executeQuery("select * from contact");
            expect($pdoSth)->toBeInstanceOf(PDOStatement::class);
            $contact = $db->fetchAll($pdoSth);
            expect($contact)->toBeArray()
                ->and($contact[0]['contact_id'])->toBe(1)
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    );

    it(
        'execute fetchColumn a correct query',
        function () use ($dbConfigCentreon) {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $pdoSth = $db->executeQuery("select * from contact");
            expect($pdoSth)->toBeInstanceOf(PDOStatement::class);
            $contactId = $db->fetchColumn($pdoSth);
            expect($contactId)->toBeInt()
                ->and($contactId)->toBe(1)
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    );

    it(
        'execute fetchColumn a column that doesnt exist',
        function () use ($dbConfigCentreon) {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $pdoSth = $db->executeQuery("select * from contact");
            expect($pdoSth)->toBeInstanceOf(PDOStatement::class);
            $db->fetchColumn($pdoSth, 99999999);
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    )->throws(ValueError::class);

    it(
        'execute execute with success',
        function () use ($dbConfigCentreon) {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $pdoSth = $db->prepareQuery("select * from contact where contact_id = :contact_id");
            expect($pdoSth)->toBeInstanceOf(PDOStatement::class);
            $successExecute = $db->execute($pdoSth, ['contact_id' => 1]);
            expect($successExecute)->toBeTrue()
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    );

    it(
        'execute execute with an empty array for params',
        function () use ($dbConfigCentreon) {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $pdoSth = $db->prepareQuery("select * from contact where contact_id = :contact_id");
            expect($pdoSth)->toBeInstanceOf(PDOStatement::class);
            $db->execute($pdoSth, []);
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    )->throws(CentreonDbException::class);

    it(
        'execute makeBindValue with success',
        function () use ($dbConfigCentreon) {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $pdoSth = $db->prepareQuery("select * from contact where contact_id = :contact_id");
            expect($pdoSth)->toBeInstanceOf(PDOStatement::class);
            $successBind = $db->makeBindValue($pdoSth, 'contact_id', 1, PDO::PARAM_INT);
            expect($successBind)->toBeTrue();
            $successExecute = $db->execute($pdoSth);
            expect($successExecute)->toBeTrue()
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    );

    it(
        'execute makeBindValue with an empty param name',
        function () use ($dbConfigCentreon) {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $pdoSth = $db->prepareQuery("select * from contact where contact_id = :contact_id");
            expect($pdoSth)->toBeInstanceOf(PDOStatement::class);
            $db->makeBindValue($pdoSth, '', 1, PDO::PARAM_INT);
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    )->throws(CentreonDbException::class);

    it(
        'execute makeBindValue with a type that doesnt exist',
        function () use ($dbConfigCentreon) {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $pdoSth = $db->prepareQuery("select * from contact where contact_id = :contact_id");
            expect($pdoSth)->toBeInstanceOf(PDOStatement::class);
            $db->makeBindValue($pdoSth, 'contact_id', 1, 999999);
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    )->throws(CentreonDbException::class);

    it(
        'execute makeBindParam with success',
        function () use ($dbConfigCentreon) {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $pdoSth = $db->prepareQuery("select * from contact where contact_id = :contact_id");
            expect($pdoSth)->toBeInstanceOf(PDOStatement::class);
            $contactID = 1;
            $successBind = $db->makeBindParam($pdoSth, 'contact_id', $contactID, PDO::PARAM_INT);
            expect($successBind)->toBeTrue();
            $successExecute = $db->execute($pdoSth);
            expect($successExecute)->toBeTrue()
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    );

    it(
        'execute makeBindParam with an empty param name',
        function () use ($dbConfigCentreon) {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $pdoSth = $db->prepareQuery("select * from contact where contact_id = :contact_id");
            expect($pdoSth)->toBeInstanceOf(PDOStatement::class);
            $contactID = 1;
            $db->makeBindParam($pdoSth, '', $contactID, PDO::PARAM_INT);
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    )->throws(CentreonDbException::class);

    it(
        'execute makeBindParam with a type that doesnt exist',
        function () use ($dbConfigCentreon) {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $pdoSth = $db->prepareQuery("select * from contact where contact_id = :contact_id");
            expect($pdoSth)->toBeInstanceOf(PDOStatement::class);
            $contactID = 1;
            $db->makeBindParam($pdoSth, 'contact_id', $contactID, 9999999);
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    )->throws(CentreonDbException::class);

    it(
        'execute closeQuery with success',
        function () use ($dbConfigCentreon) {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $pdoSth = $db->prepareQuery("select * from contact where contact_id = :contact_id");
            $pdoSth->execute(['contact_id' => 1]);
            $successClose = $db->closeQuery($pdoSth);
            expect($successClose)->toBeTrue();
        }
    );

    it(
        'execute escapeString with success',
        function () use ($dbConfigCentreon) {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $escapedString = $db->escapeString('foo');
            expect($escapedString)->toBeString()->toBe('\'foo\'');
            $escapedString = $db->escapeString('1');
            expect($escapedString)->toBeString()->toBe('\'1\'');
        }
    );
} else {
    it('no centreon database available for testing the CentreonDB connector, so these tests were ignored');
}

// ********************************** With centreon_storage database connection ***************************************

if (! is_null($dbConfigCentreonStorage) && hasConnectionDb($dbConfigCentreonStorage)) {
    it(
        'connect to centreon_storage database with CentreonDB constructor',
        function () use ($dbConfigCentreonStorage) {
            $db = new CentreonDB(dbLabel: CentreonDB::LABEL_DB_REALTIME, dbConfig: $dbConfigCentreonStorage);
            expect($db)->toBeInstanceOf(CentreonDB::class);
            $dbName = $db->executeQuery("select database()")->fetchColumn();
            expect($dbName)->toBe('centreon_storage')
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    );

    it(
        'connect to centreon_storage database with CentreonDB::connectToCentreonStorageDb factory',
        function () use ($dbConfigCentreonStorage) {
            $db = CentreonDB::connectToCentreonStorageDb($dbConfigCentreonStorage);
            expect($db)->toBeInstanceOf(CentreonDB::class);
            $dbName = $db->executeQuery("select database()")->fetchColumn();
            expect($dbName)->toBe('centreon_storage')
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    );
} else {
    it('no centreon_storage database available for testing the CentreonDB connector, so these tests were ignored');
}
