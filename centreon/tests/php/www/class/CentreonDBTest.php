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

$dbConfig = new CentreonDbConfig(
    dbHostCentreon: 'db',
    dbHostCentreonStorage: 'db',
    dbUser: 'centreon',
    dbPassword: 'centreon',
    dbNameCentreon: 'centreon',
    dbNameCentreonStorage: 'centreon_storage',
    dbPort: 3306
);

it(
    'connect to centreon database with CentreonDB constructor',
    function () use ($dbConfig) {
        $db = new CentreonDB(db: CentreonDB::LABEL_DB_CONFIGURATION, dbConfig: $dbConfig);
        expect($db)->toBeInstanceOf(CentreonDB::class);
        $dbName = $db->executeQuery("select database()")->fetchColumn();
        expect($dbName)->toBe('centreon')
            ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    }
);

it(
    'connect to centreon_storage database with CentreonDB constructor',
    function () use ($dbConfig) {
        $db = new CentreonDB(db: CentreonDB::LABEL_DB_REALTIME, dbConfig: $dbConfig);
        expect($db)->toBeInstanceOf(CentreonDB::class);
        $dbName = $db->executeQuery("select database()")->fetchColumn();
        expect($dbName)->toBe('centreon_storage')
            ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    }
);

it(
    'connect to centreon database with CentreonDB::connectToCentreonDb factory',
    function () use ($dbConfig) {
        $db = CentreonDB::connectToCentreonDb($dbConfig);
        expect($db)->toBeInstanceOf(CentreonDB::class);
        $dbName = $db->executeQuery("select database()")->fetchColumn();
        expect($dbName)->toBe('centreon')
            ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    }
);

it(
    'connect to centreon_storage database with CentreonDB::connectToCentreonStorageDb factory',
    function () use ($dbConfig) {
        $db = CentreonDB::connectToCentreonStorageDb($dbConfig);
        expect($db)->toBeInstanceOf(CentreonDB::class);
        $dbName = $db->executeQuery("select database()")->fetchColumn();
        expect($dbName)->toBe('centreon_storage')
            ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    }
);

it(
    'execute query with a correct query',
    function () use ($dbConfig) {
        $db = CentreonDB::connectToCentreonDb($dbConfig);
        $pdoSth = $db->executeQuery("select database()");
        expect($pdoSth)->toBeInstanceOf(PDOStatement::class);
        $dbName = $pdoSth->fetchColumn();
        expect($dbName)->toBe('centreon')
            ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    }
);

it(
    'execute query with an incorrect query',
    function () use ($dbConfig) {
        $db = CentreonDB::connectToCentreonDb($dbConfig);
        $db->executeQuery("foo");
        expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    }
)->throws(CentreonDbException::class);

it(
    'execute query with an empty query',
    function () use ($dbConfig) {
        $db = CentreonDB::connectToCentreonDb($dbConfig);
        $db->executeQuery("");
        expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    }
)->throws(CentreonDbException::class);

it(
    'prepare query with a correct prepared query',
    function () use ($dbConfig) {
        $db = CentreonDB::connectToCentreonDb($dbConfig);
        $pdoSth = $db->prepareQuery("select * from contact where contact_id = :contact_id");
        expect($pdoSth)->toBeInstanceOf(PDOStatement::class)
            ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    }
);

it(
    'prepare query with an empty prepared query',
    function () use ($dbConfig) {
        $db = CentreonDB::connectToCentreonDb($dbConfig);
        $db->prepareQuery("");
        expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    }
)->throws(CentreonDbException::class);

it(
    'execute prepared query with a correct prepared query ',
    function () use ($dbConfig) {
        $db = CentreonDB::connectToCentreonDb($dbConfig);
        $pdoSth = $db->prepareQuery("select * from contact where contact_id = :contact_id");
        expect($pdoSth)->toBeInstanceOf(PDOStatement::class);
        $result = $db->executePreparedQuery($pdoSth, ['contact_id' => 1]);
        expect($result)->toBeTrue()
            ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    }
);

it(
    'execute prepared query with an empty array for $bindParams',
    function () use ($dbConfig) {
        $db = CentreonDB::connectToCentreonDb($dbConfig);
        $pdoSth = $db->prepareQuery("select * from contact where contact_id = :contact_id");
        expect($pdoSth)->toBeInstanceOf(PDOStatement::class);
        $db->executePreparedQuery($pdoSth, []);
        expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    }
)->throws(CentreonDbException::class);

it(
    'execute prepared query with an incorrect prepared query',
    function () use ($dbConfig) {
        $db = CentreonDB::connectToCentreonDb($dbConfig);
        $pdoSth = $db->prepareQuery("foo");
        expect($pdoSth)->toBeInstanceOf(PDOStatement::class);
        $db->executePreparedQuery($pdoSth, ['contact_id' => 1]);
        expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    }
)->throws(CentreonDbException::class);

it(
    'execute prepared query with typing binding params with a correct prepared query',
    function () use ($dbConfig) {
        $db = CentreonDB::connectToCentreonDb($dbConfig);
        $pdoSth = $db->prepareQuery("select * from contact where contact_id = :contact_id");
        expect($pdoSth)->toBeInstanceOf(PDOStatement::class);
        $result = $db->executePreparedQuery($pdoSth, ['contact_id' => [1, PDO::PARAM_INT]], true);
        expect($result)->toBeTrue()
            ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    }
);

it(
    'execute prepared query with typing binding params with an unexpected type of param',
    function () use ($dbConfig) {
        $db = CentreonDB::connectToCentreonDb($dbConfig);
        $pdoSth = $db->prepareQuery("select * from contact where contact_id = :contact_id");
        expect($pdoSth)->toBeInstanceOf(PDOStatement::class);
        $db->executePreparedQuery($pdoSth, ['contact_id' => [1, 'foo']], true);
        expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    }
)->throws(CentreonDbException::class);

it(
    'execute prepared query with typing binding params with no array for the value of $bindParams',
    function () use ($dbConfig) {
        $db = CentreonDB::connectToCentreonDb($dbConfig);
        $pdoSth = $db->prepareQuery("select * from contact where contact_id = :contact_id");
        expect($pdoSth)->toBeInstanceOf(PDOStatement::class);
        $db->executePreparedQuery($pdoSth, ['contact_id' => 1], true);
        expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    }
)->throws(CentreonDbException::class);

it(
    'execute prepared query with typing binding params with no type for the value of $bindParams',
    function () use ($dbConfig) {
        $db = CentreonDB::connectToCentreonDb($dbConfig);
        $pdoSth = $db->prepareQuery("select * from contact where contact_id = :contact_id");
        expect($pdoSth)->toBeInstanceOf(PDOStatement::class);
        $db->executePreparedQuery($pdoSth, ['contact_id' => [1]], true);
        expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    }
)->throws(CentreonDbException::class);

it(
    'execute executeQueryFetchAll query with a correct query',
    function () use ($dbConfig) {
        $db = CentreonDB::connectToCentreonDb($dbConfig);
        $contacts = $db->executeQueryFetchAll("select * from contact");
        expect($contacts)->toBeArray()
            ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    }
);

it(
    'execute executeQueryFetchAll query with an empty query',
    function () use ($dbConfig) {
        $db = CentreonDB::connectToCentreonDb($dbConfig);
        $db->executeQueryFetchAll("");
        expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    }
)->throws(CentreonDbException::class);

it(
    'execute executeQueryFetchColumn query with a correct query',
    function () use ($dbConfig) {
        $db = CentreonDB::connectToCentreonDb($dbConfig);
        $contactsIds = $db->executeQueryFetchColumn("select * from contact");
        expect($contactsIds)->toBeArray()
            ->and($contactsIds[0])->toBeInt()
            ->and($contactsIds[0])->toBe(1)
            ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    }
);

it(
    'execute executeQueryFetchColumn query with a column option with a correct query',
    function () use ($dbConfig) {
        $db = CentreonDB::connectToCentreonDb($dbConfig);
        $alias = $db->executeQueryFetchColumn("select * from contact", 4);
        expect($alias)->toBeArray()
            ->and($alias[0])->toBeString()
            ->and($alias[0])->toBe('admin')
            ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    }
);

it(
    'execute executeQueryFetchColumn query with an empty query',
    function () use ($dbConfig) {
        $db = CentreonDB::connectToCentreonDb($dbConfig);
        $db->executeQueryFetchColumn("");
        expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    }
)->throws(CentreonDbException::class);

it(
    'execute fetch with a correct query',
    function () use ($dbConfig) {
        $db = CentreonDB::connectToCentreonDb($dbConfig);
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
    function () use ($dbConfig) {
        $db = CentreonDB::connectToCentreonDb($dbConfig);
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
    function () use ($dbConfig) {
        $db = CentreonDB::connectToCentreonDb($dbConfig);
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
    function () use ($dbConfig) {
        $db = CentreonDB::connectToCentreonDb($dbConfig);
        $pdoSth = $db->executeQuery("select * from contact");
        expect($pdoSth)->toBeInstanceOf(PDOStatement::class);
        $db->fetchColumn($pdoSth, 99999999);
        expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    }
)->throws(ValueError::class);

it(
    'execute execute with success',
    function () use ($dbConfig) {
        $db = CentreonDB::connectToCentreonDb($dbConfig);
        $pdoSth = $db->prepareQuery("select * from contact where contact_id = :contact_id");
        expect($pdoSth)->toBeInstanceOf(PDOStatement::class);
        $successExecute = $db->execute($pdoSth, ['contact_id' => 1]);
        expect($successExecute)->toBeTrue()
            ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    }
);

it(
    'execute execute with an empty array for params',
    function () use ($dbConfig) {
        $db = CentreonDB::connectToCentreonDb($dbConfig);
        $pdoSth = $db->prepareQuery("select * from contact where contact_id = :contact_id");
        expect($pdoSth)->toBeInstanceOf(PDOStatement::class);
        $db->execute($pdoSth, []);
        expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    }
)->throws(CentreonDbException::class);

it(
    'execute makeBindValue with success',
    function () use ($dbConfig) {
        $db = CentreonDB::connectToCentreonDb($dbConfig);
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
    function () use ($dbConfig) {
        $db = CentreonDB::connectToCentreonDb($dbConfig);
        $pdoSth = $db->prepareQuery("select * from contact where contact_id = :contact_id");
        expect($pdoSth)->toBeInstanceOf(PDOStatement::class);
        $db->makeBindValue($pdoSth, '', 1, PDO::PARAM_INT);
        expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    }
)->throws(CentreonDbException::class);

it(
    'execute makeBindValue with a type that doesnt exist',
    function () use ($dbConfig) {
        $db = CentreonDB::connectToCentreonDb($dbConfig);
        $pdoSth = $db->prepareQuery("select * from contact where contact_id = :contact_id");
        expect($pdoSth)->toBeInstanceOf(PDOStatement::class);
        $db->makeBindValue($pdoSth, 'contact_id', 1, 999999);
        expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    }
)->throws(CentreonDbException::class);

it(
    'execute makeBindParam with success',
    function () use ($dbConfig) {
        $db = CentreonDB::connectToCentreonDb($dbConfig);
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
    function () use ($dbConfig) {
        $db = CentreonDB::connectToCentreonDb($dbConfig);
        $pdoSth = $db->prepareQuery("select * from contact where contact_id = :contact_id");
        expect($pdoSth)->toBeInstanceOf(PDOStatement::class);
        $contactID = 1;
        $db->makeBindParam($pdoSth, '', $contactID, PDO::PARAM_INT);
        expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    }
)->throws(CentreonDbException::class);

it(
    'execute makeBindParam with a type that doesnt exist',
    function () use ($dbConfig) {
        $db = CentreonDB::connectToCentreonDb($dbConfig);
        $pdoSth = $db->prepareQuery("select * from contact where contact_id = :contact_id");
        expect($pdoSth)->toBeInstanceOf(PDOStatement::class);
        $contactID = 1;
        $db->makeBindParam($pdoSth, 'contact_id', $contactID, 9999999);
        expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    }
)->throws(CentreonDbException::class);

it(
    'execute closeQuery with success',
    function () use ($dbConfig) {
        $db = CentreonDB::connectToCentreonDb($dbConfig);
        $pdoSth = $db->prepareQuery("select * from contact where contact_id = :contact_id");
        $pdoSth->execute(['contact_id' => 1]);
        $successClose = $db->closeQuery($pdoSth);
        expect($successClose)->toBeTrue();
    }
);

it(
    'execute escapeString with success',
    function () use ($dbConfig) {
        $db = CentreonDB::connectToCentreonDb($dbConfig);
        $escapedString = $db->escapeString('foo');
        expect($escapedString)->toBeString()->toBe('\'foo\'');
        $escapedString = $db->escapeString('1');
        expect($escapedString)->toBeString()->toBe('\'1\'');
    }
);
