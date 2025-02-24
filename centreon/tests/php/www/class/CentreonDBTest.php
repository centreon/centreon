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

use Adaptation\Database\Connection\Collection\BatchInsertParameters;
use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\Exception\ConnectionException;
use Adaptation\Database\Connection\Model\ConnectionConfig;
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use CentreonDB;
use CentreonDbException;
use CentreonDBStatement;
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
    $dbConfigCentreon = new ConnectionConfig(
        host: $dbHost,
        user: $dbUser,
        password: $dbPassword,
        databaseName: 'centreon',
        databaseNameStorage: 'centreon_storage',
        port: 3306
    );
    $dbConfigCentreonStorage = new ConnectionConfig(
        host: $dbHost,
        user: $dbUser,
        password: $dbPassword,
        databaseName: 'centreon_storage',
        databaseNameStorage: 'centreon_storage',
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
        new PDO (
            $connectionConfig->getMysqlDsn(),
            $connectionConfig->getUser(),
            $connectionConfig->getPassword(),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        return true;
    } catch (\PDOException $e) {
        return false;
    }
}

// ************************************** With centreon database connection *******************************************

if (! is_null($dbConfigCentreon) && hasConnectionDb($dbConfigCentreon)) {
    it(
        'test CentreonDB : connect to centreon database with CentreonDB constructor',
        function () use ($dbConfigCentreon): void {
            $db = new CentreonDB(dbLabel: CentreonDB::LABEL_DB_CONFIGURATION, connectionConfig: $dbConfigCentreon);
            expect($db)->toBeInstanceOf(CentreonDB::class);
            $stmt = $db->prepare("select database()");
            $stmt->execute();
            $dbName = $stmt->fetchColumn();
            expect($dbName)->toBe('centreon')
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    );

    it(
        'test CentreonDB : connect to centreon database with CentreonDB::connectToCentreonDb factory',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            expect($db)->toBeInstanceOf(CentreonDB::class);
            $stmt = $db->prepare("select database()");
            $stmt->execute();
            $dbName = $stmt->fetchColumn();
            expect($dbName)->toBe('centreon')
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    );

    it(
        'test CentreonDB : CentreonDB::createFromConfig factory with centreon database',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::createFromConfig($dbConfigCentreon);
            expect($db)->toBeInstanceOf(CentreonDB::class);
            $stmt = $db->prepare("select database()");
            $stmt->execute();
            $dbName = $stmt->fetchColumn();
            expect($dbName)->toBe('centreon')
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    );

    it(
        'test CentreonDB : get connection config',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $connectionConfig = $db->getConnectionConfig();
            expect($connectionConfig)->toBeInstanceOf(ConnectionConfig::class)
                ->and($connectionConfig->getHost())->toBe($dbConfigCentreon->getHost())
                ->and($connectionConfig->getUser())->toBe($dbConfigCentreon->getUser())
                ->and($connectionConfig->getPassword())->toBe($dbConfigCentreon->getPassword())
                ->and($connectionConfig->getDatabaseName())->toBe($dbConfigCentreon->getDatabaseName())
                ->and($connectionConfig->getPort())->toBe($dbConfigCentreon->getPort());
        }
    );

    it('test CentreonDB : get the database name of the current connection', function () use ($dbConfigCentreon): void {
        $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
        $dbName = $db->getDatabaseName();
        expect($dbName)->toBe('centreon');
    });

    it('test CentreonDB : get native connection', function () use ($dbConfigCentreon): void {
        $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
        $pdo = $db->getNativeConnection();
        expect($pdo)->toBeInstanceOf(PDO::class);
    });

    it('test CentreonDB : get last insert id', function () use ($dbConfigCentreon): void {
        $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
        $insert = $db->exec(
            "INSERT INTO contact(contact_id, contact_name, contact_alias) VALUES(110, 'foo_name', 'foo_alias')"
        );
        expect($insert)->toBeInt()->toBe(1);
        $lastInsertId = $db->getLastInsertId();
        expect($lastInsertId)->toBeString()->toBe('110');
        // clean up the database
        $delete = $db->exec("DELETE FROM contact WHERE contact_id = 110");
        expect($delete)->toBeInt()->toBe(1);
    });

    it('test CentreonDB : is connected', function () use ($dbConfigCentreon): void {
        $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
        expect($db->isConnected())->toBeTrue();
    });

    it('test CentreonDB : quote string', function () use ($dbConfigCentreon): void {
        $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
        $quotedString = $db->quote("foo");
        expect($quotedString)->toBeString()->toBe("'foo'");
    });

    // --------------------------------------- CUD METHODS -----------------------------------------

    // -- executeStatement()

    it(
        'test CentreonDB : execute statement with a correct query without query parameters',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $inserted = $db->executeStatement(
                "INSERT INTO contact(contact_id, contact_name, contact_alias) VALUES(110, 'foo_name', 'foo_alias')"
            );
            expect($inserted)->toBeInt()->toBe(1)
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
            // clean up the database
            $deleted = $db->exec("DELETE FROM contact WHERE contact_id = 110");
            expect($deleted)->toBeInt()->toBe(1)
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    );

    it(
        'test CentreonDB : execute statement with a correct query with query parameters',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $queryParameters = QueryParameters::create(
                [
                    QueryParameter::int('id', 110),
                    QueryParameter::string('name', 'foo_name'),
                    QueryParameter::string('alias', 'foo_alias')
                ]
            );
            $inserted = $db->executeStatement(
                "INSERT INTO contact(contact_id, contact_name, contact_alias) VALUES(:id, :name, :alias)",
                $queryParameters
            );
            expect($inserted)->toBeInt()->toBe(1)
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
            // clean up the database
            $deleted = $db->exec("DELETE FROM contact WHERE contact_id = 110");
            expect($deleted)->toBeInt()->toBe(1)
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    );

    it('test CentreonDB : execute statement with a SELECT query', function () use ($dbConfigCentreon): void {
        $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
        $db->executeStatement("SELECT * FROM contact");
        expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    })->throws(ConnectionException::class);

    it('test CentreonDB : execute statement with an empty query', function () use ($dbConfigCentreon): void {
        $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
        $db->executeStatement("");
        expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    })->throws(ConnectionException::class);

    it('test CentreonDB : execute statement with an incorrect query', function () use ($dbConfigCentreon): void {
        $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
        $db->executeStatement("foo");
        expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    })->throws(ConnectionException::class);

    it(
        'test CentreonDB : execute statement with an incorrect query parameters',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $queryParameters = QueryParameters::create(
                [
                    QueryParameter::int('id', 110),
                    QueryParameter::string('name', 'foo_name')
                ]
            );
            $db->executeStatement(
                "INSERT INTO contact(contact_id, contact_name, contact_alias) VALUES(:id, :name, :alias)",
                $queryParameters
            );
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    )->throws(ConnectionException::class);

    // -- insert()

    it(
        'test CentreonDB : insert with a correct query with query parameters',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $queryParameters = QueryParameters::create(
                [
                    QueryParameter::int('id', 110),
                    QueryParameter::string('name', 'foo_name'),
                    QueryParameter::string('alias', 'foo_alias')
                ]
            );
            $inserted = $db->insert(
                "INSERT INTO contact(contact_id, contact_name, contact_alias) VALUES(:id, :name, :alias)",
                $queryParameters
            );
            expect($inserted)->toBeInt()->toBe(1)
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
            // clean up the database
            $deleted = $db->exec("DELETE FROM contact WHERE contact_id = 110");
            expect($deleted)->toBeInt()->toBe(1)
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    );

    it('test CentreonDB : insert with a SELECT query', function () use ($dbConfigCentreon): void {
        $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
        $db->insert(
            "SELECT * FROM contact WHERE contact_id = :id",
            QueryParameters::create([QueryParameter::int('contact_id', 110)])
        );
        expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    })->throws(ConnectionException::class);

    it('test CentreonDB : insert with an empty query', function () use ($dbConfigCentreon): void {
        $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
        $db->insert("", QueryParameters::create([QueryParameter::int('contact_id', 110)]));
        expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    })->throws(ConnectionException::class);

    it('test CentreonDB : insert with an incorrect query', function () use ($dbConfigCentreon): void {
        $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
        $db->insert("foo", QueryParameters::create([QueryParameter::int('contact_id', 110)]));
        expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    })->throws(ConnectionException::class);

    it(
        'test CentreonDB : insert with an incorrect query parameters',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $queryParameters = QueryParameters::create(
                [
                    QueryParameter::int('id', 110),
                    QueryParameter::string('name', 'foo_name')
                ]
            );
            $db->insert(
                "INSERT INTO contact(contact_id, contact_name, contact_alias) VALUES(:id, :name, :alias)",
                $queryParameters
            );
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    )->throws(ConnectionException::class);

    // -- batchInsert()

    it(
        'test CentreonDB : batch insert with a correct query with batch query parameters',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $batchQueryParameters = BatchInsertParameters::create([
                QueryParameters::create([
                    QueryParameter::int('contact_id', 110),
                    QueryParameter::string('contact_name', 'foo_name'),
                    QueryParameter::string('contact_alias', 'foo_alias')
                ]),
                QueryParameters::create([
                    QueryParameter::int('contact_id', 111),
                    QueryParameter::string('contact_name', 'bar_name'),
                    QueryParameter::string('contact_alias', 'bar_alias')
                ]),
                QueryParameters::create([
                    QueryParameter::int('contact_id', 112),
                    QueryParameter::string('contact_name', 'baz_name'),
                    QueryParameter::string('contact_alias', 'baz_alias')
                ])
            ]);
            $inserted = $db->batchInsert(
                'contact',
                ['contact_id', 'contact_name', 'contact_alias'],
                $batchQueryParameters
            );
            expect($inserted)->toBeInt()->toBe(3)
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
            // clean up the database
            $deleted = $db->exec("DELETE FROM contact WHERE contact_id IN (110,111,112)");
            expect($deleted)->toBeInt()->toBe(3)
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    );

    it(
        'test CentreonDB : batch insert with a correct query with empty batch query parameters',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $db->batchInsert(
                'contact',
                ['contact_id', 'contact_name', 'contact_alias'],
                BatchInsertParameters::create([])
            );
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    )->throws(ConnectionException::class);

    it(
        'test CentreonDB : batch insert with an incorrect batch query parameters',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $batchQueryParameters = BatchInsertParameters::create([
                QueryParameters::create([
                    QueryParameter::int('contact_id', 110),
                    QueryParameter::string('contact_name', 'foo_name')
                ]),
                QueryParameters::create([
                    QueryParameter::int('contact_id', 111),
                    QueryParameter::string('contact_name', 'bar_name')
                ]),
                QueryParameters::create([
                    QueryParameter::int('contact_id', 112),
                    QueryParameter::string('contact_name', 'baz_name')
                ])
            ]);
            $db->batchInsert(
                'contact',
                ['contact_id', 'contact_name', 'contact_alias'],
                $batchQueryParameters
            );
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    )->throws(ConnectionException::class);

    // -- update()

    it(
        'test CentreonDB : update with a correct query with query parameters',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $inserted = $db->executeStatement(
                "INSERT INTO contact(contact_id, contact_name, contact_alias) VALUES(110, 'foo_name', 'foo_alias')"
            );
            expect($inserted)->toBeInt()->toBe(1);
            $queryParameters = QueryParameters::create(
                [
                    QueryParameter::string('name', 'bar_name'),
                    QueryParameter::string('alias', 'bar_alias'),
                    QueryParameter::int('id', 110)
                ]
            );
            $updated = $db->update(
                "UPDATE contact SET contact_name = :name, contact_alias = :alias WHERE contact_id = :id",
                $queryParameters
            );
            expect($updated)->toBeInt()->toBe(1)
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
            // clean up the database
            $delete = $db->exec("DELETE FROM contact WHERE contact_id = 110");
            expect($delete)->toBeInt()->toBe(1)
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    );

    it('test CentreonDB : update with a SELECT query', function () use ($dbConfigCentreon): void {
        $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
        $db->update(
            "SELECT * FROM contact WHERE contact_id = :id",
            QueryParameters::create([QueryParameter::int('contact_id', 110)])
        );
        expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    })->throws(ConnectionException::class);

    it('test CentreonDB : update with an empty query', function () use ($dbConfigCentreon): void {
        $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
        $db->update("", QueryParameters::create([QueryParameter::int('contact_id', 110)]));
        expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    })->throws(ConnectionException::class);

    it('test CentreonDB : update with an incorrect query', function () use ($dbConfigCentreon): void {
        $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
        $db->update("foo", QueryParameters::create([QueryParameter::int('contact_id', 110)]));
        expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    })->throws(ConnectionException::class);

    it(
        'test CentreonDB : update with an incorrect query parameters',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $queryParameters = QueryParameters::create(
                [
                    QueryParameter::string('name', 'bar_name'),
                    QueryParameter::string('alias', 'bar_alias')
                ]
            );
            $db->update(
                "UPDATE contact SET contact_name = :name, contact_alias = :alias WHERE contact_id = :id",
                $queryParameters
            );
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    )->throws(ConnectionException::class);

    // -- delete()

    it(
        'test CentreonDB : delete with a correct query with query parameters',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $inserted = $db->executeStatement(
                "INSERT INTO contact(contact_id, contact_name, contact_alias) VALUES(110, 'foo_name', 'foo_alias')"
            );
            expect($inserted)->toBeInt()->toBe(1)
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
            $queryParameters = QueryParameters::create([QueryParameter::int('id', 110)]);
            $deleted = $db->delete("DELETE FROM contact WHERE contact_id = :id", $queryParameters);
            expect($deleted)->toBeInt()->toBe(1)
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    );

    it('test CentreonDB : delete with a SELECT query', function () use ($dbConfigCentreon): void {
        $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
        $db->delete(
            "SELECT * FROM contact WHERE contact_id = :id",
            QueryParameters::create([QueryParameter::int('contact_id', 110)])
        );
        expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    })->throws(ConnectionException::class);

    it('test CentreonDB : delete with an empty query', function () use ($dbConfigCentreon): void {
        $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
        $db->delete("", QueryParameters::create([QueryParameter::int('contact_id', 110)]));
        expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    })->throws(ConnectionException::class);

    it('test CentreonDB : delete with an incorrect query', function () use ($dbConfigCentreon): void {
        $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
        $db->delete("foo", QueryParameters::create([QueryParameter::int('contact_id', 110)]));
        expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    })->throws(ConnectionException::class);

    it(
        'test CentreonDB : delete with an incorrect query parameters',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $queryParameters = QueryParameters::create(
                [
                    QueryParameter::string('name', 'foo_name'),
                    QueryParameter::string('alias', 'foo_alias')
                ]
            );
            $db->delete(
                "DELETE FROM contact WHERE contact_id = :id AND contact_name = :name AND contact_alias = :alias",
                $queryParameters
            );
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    )->throws(ConnectionException::class);

    // ---------------------------------------- FETCH METHODS ----------------------------------------------

    // -- fetchNumeric()

    it(
        'test CentreonDB : fetchNumeric with a correct query with query parameters',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $contact = $db->fetchNumeric(
                "SELECT * FROM contact WHERE contact_id = :id",
                QueryParameters::create([QueryParameter::int('id', 1)])
            );
            expect($contact)->toBeArray()
                ->and($contact[0])->toBe(1)
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    );

    it('test CentreonDB : fetchNumeric with a CUD query', function () use ($dbConfigCentreon): void {
        $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
        $db->fetchNumeric(
            "DELETE FROM contact WHERE contact_id = :id",
            QueryParameters::create([QueryParameter::int('id', 1)])
        );
        expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    })->throws(ConnectionException::class);

    it('test CentreonDB : fetchNumeric with an empty query', function () use ($dbConfigCentreon): void {
        $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
        $db->fetchNumeric("", QueryParameters::create([QueryParameter::int('id', 1)]));
        expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    })->throws(ConnectionException::class);

    it('test CentreonDB : fetchNumeric with an incorrect query', function () use ($dbConfigCentreon): void {
        $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
        $db->fetchNumeric("foo", QueryParameters::create([QueryParameter::int('id', 1)]));
        expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    })->throws(ConnectionException::class);

    it(
        'test CentreonDB : fetchNumeric with an incorrect query parameters',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $db->fetchNumeric(
                "SELECT * FROM contact WHERE contact_id = :id",
                QueryParameters::create([QueryParameter::string('name', 'foo_name')])
            );
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    )->throws(ConnectionException::class);

    // -- fetchAssociative()

    it(
        'test CentreonDB : fetchAssociative with a correct query with query parameters',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $contact = $db->fetchAssociative(
                "SELECT * FROM contact WHERE contact_id = :id",
                QueryParameters::create([QueryParameter::int('id', 1)])
            );
            expect($contact)->toBeArray()
                ->and($contact['contact_id'])->toBe(1)
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    );

    it('test CentreonDB : fetchAssociative with a CUD query', function () use ($dbConfigCentreon): void {
        $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
        $db->fetchAssociative(
            "DELETE FROM contact WHERE contact_id = :id",
            QueryParameters::create([QueryParameter::int('id', 1)])
        );
        expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    })->throws(ConnectionException::class);

    it('test CentreonDB : fetchAssociative with an empty query', function () use ($dbConfigCentreon): void {
        $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
        $db->fetchAssociative("", QueryParameters::create([QueryParameter::int('id', 1)]));
        expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    })->throws(ConnectionException::class);

    it('test CentreonDB : fetchAssociative with an incorrect query', function () use ($dbConfigCentreon): void {
        $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
        $db->fetchAssociative("foo", QueryParameters::create([QueryParameter::int('id', 1)]));
        expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    })->throws(ConnectionException::class);

    it(
        'test CentreonDB : fetchAssociative with an incorrect query parameters',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $db->fetchAssociative(
                "SELECT * FROM contact WHERE contact_id = :id",
                QueryParameters::create([QueryParameter::string('name', 'foo_name')])
            );
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    )->throws(ConnectionException::class);

    // -- fetchOne()

    it(
        'test CentreonDB : fetchOne with a correct query with query parameters',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $alias = $db->fetchOne(
                "SELECT contact_alias FROM contact WHERE contact_id = :id",
                QueryParameters::create([QueryParameter::int('id', 1)])
            );
            expect($alias)->toBeString()->toBe('admin')
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    );

    it('test CentreonDB : fetchOne with a CUD query', function () use ($dbConfigCentreon): void {
        $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
        $db->fetchOne(
            "DELETE FROM contact WHERE contact_id = :id",
            QueryParameters::create([QueryParameter::int('id', 1)])
        );
        expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    })->throws(ConnectionException::class);

    it('test CentreonDB : fetchOne with an empty query', function () use ($dbConfigCentreon): void {
        $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
        $db->fetchOne("", QueryParameters::create([QueryParameter::int('id', 1)]));
        expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    })->throws(ConnectionException::class);

    it('test CentreonDB : fetchOne with an incorrect query', function () use ($dbConfigCentreon): void {
        $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
        $db->fetchOne("foo", QueryParameters::create([QueryParameter::int('id', 1)]));
        expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    })->throws(ConnectionException::class);

    it(
        'test CentreonDB : fetchOne with an incorrect query parameters',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $db->fetchOne(
                "SELECT contact_alias FROM contact WHERE contact_id = :id",
                QueryParameters::create([QueryParameter::string('name', 'foo_name')])
            );
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    )->throws(ConnectionException::class);

    // -- fetchFirstColumn()

    it(
        'test CentreonDB : fetchFirstColumn with a correct query with query parameters',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $contact = $db->fetchFirstColumn(
                "SELECT contact_id FROM contact ORDER BY contact_id",
            );
            expect($contact)->toBeArray()
                ->and($contact[0])->toBeInt()->toBe(1)
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    );

    it(
        'test CentreonDB : fetchFirstColumn with a correct query with query parameters and another column',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $contact = $db->fetchFirstColumn("SELECT contact_alias FROM contact ORDER BY contact_id");
            expect($contact)->toBeArray()
                ->and($contact[0])->toBeString()->toBe('admin')
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    );

    it('test CentreonDB : fetchFirstColumn with a CUD query', function () use ($dbConfigCentreon): void {
        $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
        $db->fetchFirstColumn(
            "DELETE FROM contact WHERE contact_id = :id",
            QueryParameters::create([QueryParameter::int('id', 1)])
        );
        expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    })->throws(ConnectionException::class);

    it('test CentreonDB : fetchFirstColumn with an empty query', function () use ($dbConfigCentreon): void {
        $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
        $db->fetchFirstColumn("", QueryParameters::create([QueryParameter::int('id', 1)]));
        expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    })->throws(ConnectionException::class);

    it('test CentreonDB : fetchFirstColumn with an incorrect query', function () use ($dbConfigCentreon): void {
        $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
        $db->fetchFirstColumn("foo", QueryParameters::create([QueryParameter::int('id', 1)]));
        expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    })->throws(ConnectionException::class);

    it(
        'test CentreonDB : fetchFirstColumn with an incorrect query parameters',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $db->fetchFirstColumn(
                "SELECT contact_alias FROM contact WHERE contact_id = :id",
                QueryParameters::create([QueryParameter::string('name', 'foo_name')])
            );
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    )->throws(ConnectionException::class);

    // -- fetchAllNumeric()

    it(
        'test CentreonDB : fetchAllNumeric with a correct query with query parameters',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $contact = $db->fetchAllNumeric(
                "SELECT * FROM contact WHERE contact_id = :id",
                QueryParameters::create([QueryParameter::int('id', 1)])
            );
            expect($contact)->toBeArray()
                ->and($contact[0][0])->toBe(1)
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    );

    it('test CentreonDB : fetchAllNumeric with a CUD query', function () use ($dbConfigCentreon): void {
        $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
        $db->fetchAllNumeric(
            "DELETE FROM contact WHERE contact_id = :id",
            QueryParameters::create([QueryParameter::int('id', 1)])
        );
        expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    })->throws(ConnectionException::class);

    it('test CentreonDB : fetchAllNumeric with an empty query', function () use ($dbConfigCentreon): void {
        $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
        $db->fetchAllNumeric("", QueryParameters::create([QueryParameter::int('id', 1)]));
        expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    })->throws(ConnectionException::class);

    it('test CentreonDB : fetchAllNumeric with an incorrect query', function () use ($dbConfigCentreon): void {
        $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
        $db->fetchAllNumeric("foo", QueryParameters::create([QueryParameter::int('id', 1)]));
        expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    })->throws(ConnectionException::class);

    it(
        'test CentreonDB : fetchAllNumeric with an incorrect query parameters',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $db->fetchAllNumeric(
                "SELECT * FROM contact WHERE contact_id = :id",
                QueryParameters::create([QueryParameter::string('name', 'foo_name')])
            );
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    )->throws(ConnectionException::class);

    // -- fetchAllAssociative()

    it(
        'test CentreonDB : fetchAllAssociative with a correct query with query parameters',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $contact = $db->fetchAllAssociative(
                "SELECT * FROM contact WHERE contact_id = :id",
                QueryParameters::create([QueryParameter::int('id', 1)])
            );
            expect($contact)->toBeArray()
                ->and($contact[0]['contact_id'])->toBe(1)
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    );

    it('test CentreonDB : fetchAllAssociative with a CUD query', function () use ($dbConfigCentreon): void {
        $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
        $db->fetchAllAssociative(
            "DELETE FROM contact WHERE contact_id = :id",
            QueryParameters::create([QueryParameter::int('id', 1)])
        );
        expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    })->throws(ConnectionException::class);

    it('test CentreonDB : fetchAllAssociative with an empty query', function () use ($dbConfigCentreon): void {
        $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
        $db->fetchAllAssociative("", QueryParameters::create([QueryParameter::int('id', 1)]));
        expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    })->throws(ConnectionException::class);

    it('test CentreonDB : fetchAllAssociative with an incorrect query', function () use ($dbConfigCentreon): void {
        $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
        $db->fetchAllAssociative("foo", QueryParameters::create([QueryParameter::int('id', 1)]));
        expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    })->throws(ConnectionException::class);

    it(
        'test CentreonDB : fetchAllAssociative with an incorrect query parameters',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $db->fetchAllAssociative(
                "SELECT * FROM contact WHERE contact_id = :id",
                QueryParameters::create([QueryParameter::string('name', 'foo_name')])
            );
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    )->throws(ConnectionException::class);

    // -- fetchAllKeyValue()

    it(
        'test CentreonDB : fetchAllKeyValue with a correct query with query parameters',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $contact = $db->fetchAllKeyValue(
                "SELECT contact_id, contact_alias FROM contact WHERE contact_id = :id",
                QueryParameters::create([QueryParameter::int('id', 1)])
            );
            expect($contact)->toBeArray()
                ->and($contact)->toBeArray()->toBe(['1' => 'admin'])
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    );

    it('test CentreonDB : fetchAllKeyValue with a CUD query', function () use ($dbConfigCentreon): void {
        $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
        $db->fetchAllKeyValue(
            "DELETE FROM contact WHERE contact_id = :id",
            QueryParameters::create([QueryParameter::int('id', 1)])
        );
        expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    })->throws(ConnectionException::class);

    it('test CentreonDB : fetchAllKeyValue with an empty query', function () use ($dbConfigCentreon): void {
        $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
        $db->fetchAllKeyValue("", QueryParameters::create([QueryParameter::int('id', 1)]));
        expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    })->throws(ConnectionException::class);

    it('test CentreonDB : fetchAllKeyValue with an incorrect query', function () use ($dbConfigCentreon): void {
        $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
        $db->fetchAllKeyValue("foo", QueryParameters::create([QueryParameter::int('id', 1)]));
        expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    })->throws(ConnectionException::class);

    it(
        'test CentreonDB : fetchAllKeyValue with an incorrect query parameters',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $db->fetchAllKeyValue(
                "SELECT contact_id, contact_alias FROM contact WHERE contact_id = :id",
                QueryParameters::create([QueryParameter::string('name', 'foo_name')])
            );
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    )->throws(ConnectionException::class);

    // -- fetchAllAssociativeIndexed()

    it(
        'test CentreonDB : fetchAllAssociativeIndexed with a correct query with query parameters',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $contact = $db->fetchAllAssociativeIndexed(
                "SELECT contact_id, contact_name, contact_alias FROM contact WHERE contact_id = :id",
                QueryParameters::create([QueryParameter::int('id', 1)])
            );
            expect($contact)->toBeArray()
                ->and($contact[1])->toBeArray()->toBe(['contact_name' => 'admin admin', 'contact_alias' => 'admin'])
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    );

    it('test CentreonDB : fetchAllAssociativeIndexed with a CUD query', function () use ($dbConfigCentreon): void {
        $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
        $db->fetchAllAssociativeIndexed(
            "DELETE FROM contact WHERE contact_id = :id",
            QueryParameters::create([QueryParameter::int('id', 1)])
        );
        expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    })->throws(ConnectionException::class);

    it('test CentreonDB : fetchAllAssociativeIndexed with an empty query', function () use ($dbConfigCentreon): void {
        $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
        $db->fetchAllAssociativeIndexed("", QueryParameters::create([QueryParameter::int('id', 1)]));
        expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    })->throws(ConnectionException::class);

    it(
        'test CentreonDB : fetchAllAssociativeIndexed with an incorrect query',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $db->fetchAllAssociativeIndexed("foo", QueryParameters::create([QueryParameter::int('id', 1)]));
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    )->throws(ConnectionException::class);

    it(
        'test CentreonDB : fetchAllAssociativeIndexed with an incorrect query parameters',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $db->fetchAllAssociativeIndexed(
                "SELECT contact_id, contact_name, contact_alias FROM contact WHERE contact_id = :id",
                QueryParameters::create([QueryParameter::string('name', 'foo_name')])
            );
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    )->throws(ConnectionException::class);

    // ---------------------------------------- ITERATE METHODS ----------------------------------------------

    // -- iterateNumeric()

    it(
        'test CentreonDB : iterateNumeric with a correct query with query parameters',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $contacts = $db->iterateNumeric(
                "SELECT * FROM contact WHERE contact_id = :id",
                QueryParameters::create([QueryParameter::int('id', 1)])
            );
            foreach ($contacts as $contact) {
                expect($contact)->toBeArray()->and($contact[0])->toBe(1)
                    ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
            }
        }
    );

    it('test CentreonDB : iterateNumeric with a CUD query', function () use ($dbConfigCentreon): void {
        $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
        $contacts = $db->iterateNumeric(
            "DELETE FROM contact WHERE contact_id = :id",
            QueryParameters::create([QueryParameter::int('id', 1)])
        );
        foreach ($contacts as $contact) {
            /* to avoid alert */
            $dummy = $contact;
        }
        expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    })->throws(ConnectionException::class);

    it('test CentreonDB : iterateNumeric with an empty query', function () use ($dbConfigCentreon): void {
        $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
        $contacts = $db->iterateNumeric("", QueryParameters::create([QueryParameter::int('id', 1)]));
        foreach ($contacts as $contact) {
            /* to avoid alert */
            $dummy = $contact;
        }
        expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    })->throws(ConnectionException::class);

    it('test CentreonDB : iterateNumeric with an incorrect query', function () use ($dbConfigCentreon): void {
        $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
        $contacts = $db->iterateNumeric("foo", QueryParameters::create([QueryParameter::int('id', 1)]));
        foreach ($contacts as $contact) {
            /* to avoid alert */
            $dummy = $contact;
        }
        expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    })->throws(ConnectionException::class);

    it(
        'test CentreonDB : iterateNumeric with an incorrect query parameters',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $contacts = $db->iterateNumeric(
                "SELECT * FROM contact WHERE contact_id = :id",
                QueryParameters::create([QueryParameter::string('name', 'foo_name')])
            );
            foreach ($contacts as $contact) {
                /* to avoid alert */
                $dummy = $contact;
            }
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    )->throws(ConnectionException::class);

    // -- iterateAssociative()

    it(
        'test CentreonDB : iterateAssociative with a correct query with query parameters',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $contacts = $db->iterateAssociative(
                "SELECT * FROM contact WHERE contact_id = :id",
                QueryParameters::create([QueryParameter::int('id', 1)])
            );
            foreach ($contacts as $contact) {
                expect($contact)->toBeArray()->and($contact['contact_id'])->toBe(1)
                    ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
            }
        }
    );

    it('test CentreonDB : iterateAssociative with a CUD query', function () use ($dbConfigCentreon): void {
        $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
        $contacts = $db->iterateAssociative(
            "DELETE FROM contact WHERE contact_id = :id",
            QueryParameters::create([QueryParameter::int('id', 1)])
        );
        foreach ($contacts as $contact) {
            /* to avoid alert */
            $dummy = $contact;
        }
        expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    })->throws(ConnectionException::class);

    it('test CentreonDB : iterateAssociative with an empty query', function () use ($dbConfigCentreon): void {
        $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
        $contacts = $db->iterateAssociative("", QueryParameters::create([QueryParameter::int('id', 1)]));
        foreach ($contacts as $contact) {
            /* to avoid alert */
            $dummy = $contact;
        }
        expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    })->throws(ConnectionException::class);

    it('test CentreonDB : iterateAssociative with an incorrect query', function () use ($dbConfigCentreon): void {
        $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
        $contacts = $db->iterateAssociative("foo", QueryParameters::create([QueryParameter::int('id', 1)]));
        foreach ($contacts as $contact) {
            /* to avoid alert */
            $dummy = $contact;
        }
        expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    })->throws(ConnectionException::class);

    it(
        'test CentreonDB : iterateAssociative with an incorrect query parameters',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $contacts = $db->iterateAssociative(
                "SELECT * FROM contact WHERE contact_id = :id",
                QueryParameters::create([QueryParameter::string('name', 'foo_name')])
            );
            foreach ($contacts as $contact) {
                /* to avoid alert */
                $dummy = $contact;
            }
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    )->throws(ConnectionException::class);

    // -- iterateColumn()

    it(
        'test CentreonDB : iterateColumn with a correct query with query parameters',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $contacts = $db->iterateColumn(
                "SELECT contact_id FROM contact WHERE contact_id = :id",
                QueryParameters::create([QueryParameter::int('id', 1)])
            );
            foreach ($contacts as $contact) {
                expect($contact)->toBeInt()->toBe(1)
                    ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
            }
        }
    );

    it(
        'test CentreonDB : iterateColumn with a correct query with query parameters and another column',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $contacts = $db->iterateColumn(
                "SELECT contact_alias FROM contact WHERE contact_id = :id",
                QueryParameters::create([QueryParameter::int('id', 1)])
            );
            foreach ($contacts as $contact) {
                expect($contact)->toBeString()->toBe('admin')
                    ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
            }
        }
    );

    it('test CentreonDB : iterateColumn with a CUD query', function () use ($dbConfigCentreon): void {
        $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
        $contacts = $db->iterateColumn(
            "DELETE FROM contact WHERE contact_id = :id",
            QueryParameters::create([QueryParameter::int('id', 1)])
        );
        foreach ($contacts as $contact) {
            /* to avoid alert */
            $dummy = $contact;
        }
        expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    })->throws(ConnectionException::class);

    it('test CentreonDB : iterateColumn with an empty query', function () use ($dbConfigCentreon): void {
        $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
        $contacts = $db->iterateColumn("", QueryParameters::create([QueryParameter::int('id', 1)]));
        foreach ($contacts as $contact) {
            /* to avoid alert */
            $dummy = $contact;
        }
        expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    })->throws(ConnectionException::class);

    it('test CentreonDB : iterateColumn with an incorrect query', function () use ($dbConfigCentreon): void {
        $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
        $contacts = $db->iterateColumn("foo", QueryParameters::create([QueryParameter::int('id', 1)]));
        foreach ($contacts as $contact) {
            /* to avoid alert */
            $dummy = $contact;
        }
        expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    })->throws(ConnectionException::class);

    it(
        'test CentreonDB : iterateColumn with an incorrect query parameters',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $contacts = $db->iterateColumn(
                "SELECT * FROM contact WHERE contact_id = :id",
                QueryParameters::create([QueryParameter::string('name', 'foo_name')])
            );
            foreach ($contacts as $contact) {
                /* to avoid alert */
                $dummy = $contact;
            }
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    )->throws(ConnectionException::class);

    // -- iterateKeyValue()

    it(
        'test CentreonDB : iterateKeyValue with a correct query with query parameters',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $contact = $db->iterateKeyValue(
                "SELECT contact_id, contact_alias FROM contact WHERE contact_id = :id",
                QueryParameters::create([QueryParameter::int('id', 1)])
            );
            foreach ($contact as $contactId => $contactAlias) {
                expect($contactId)->toBeInt()->toBe(1)
                    ->and($contactAlias)->toBeString()->toBe('admin')
                    ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
            }
        }
    );

    it('test CentreonDB : iterateKeyValue with a CUD query', function () use ($dbConfigCentreon): void {
        $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
        $contacts = $db->iterateKeyValue(
            "DELETE FROM contact WHERE contact_id = :id",
            QueryParameters::create([QueryParameter::int('id', 1)])
        );
        foreach ($contacts as $contact) {
            /* to avoid alert */
            $dummy = $contact;
        }
        expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    })->throws(ConnectionException::class);

    it('test CentreonDB : iterateKeyValue with an empty query', function () use ($dbConfigCentreon): void {
        $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
        $contacts = $db->iterateKeyValue("", QueryParameters::create([QueryParameter::int('id', 1)]));
        foreach ($contacts as $contact) {
            /* to avoid alert */
            $dummy = $contact;
        }
        expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    })->throws(ConnectionException::class);

    it('test CentreonDB : iterateKeyValue with an incorrect query', function () use ($dbConfigCentreon): void {
        $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
        $contacts = $db->iterateKeyValue("foo", QueryParameters::create([QueryParameter::int('id', 1)]));
        foreach ($contacts as $contact) {
            /* to avoid alert */
            $dummy = $contact;
        }
        expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    })->throws(ConnectionException::class);

    it(
        'test CentreonDB : iterateKeyValue with an incorrect query parameters',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $contacts = $db->iterateKeyValue(
                "SELECT contact_id, contact_alias FROM contact WHERE contact_id = :id",
                QueryParameters::create([QueryParameter::string('name', 'foo_name')])
            );
            foreach ($contacts as $contact) {
                /* to avoid alert */
                $dummy = $contact;
            }
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    )->throws(ConnectionException::class);

    // -- iterateAssociativeIndexed()

    it(
        'test CentreonDB : iterateAssociativeIndexed with a correct query with query parameters',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $contacts = $db->iterateAssociativeIndexed(
                "SELECT contact_id, contact_name, contact_alias FROM contact WHERE contact_id = :id",
                QueryParameters::create([QueryParameter::int('id', 1)])
            );
            foreach ($contacts as $contactId => $contact) {
                expect($contactId)->toBeInt()->toBe(1)
                    ->and($contact)->toBeArray()->toHaveKey('contact_name', 'admin admin')
                    ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
            }
        }
    );

    it('test CentreonDB : iterateAssociativeIndexed with a CUD query', function () use ($dbConfigCentreon): void {
        $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
        $contacts = $db->iterateAssociativeIndexed(
            "DELETE FROM contact WHERE contact_id = :id",
            QueryParameters::create([QueryParameter::int('id', 1)])
        );
        foreach ($contacts as $contact) {
            /* to avoid alert */
            $dummy = $contact;
        }
        expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    })->throws(ConnectionException::class);

    it('test CentreonDB : iterateAssociativeIndexed with an empty query', function () use ($dbConfigCentreon): void {
        $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
        $contacts = $db->iterateAssociativeIndexed("", QueryParameters::create([QueryParameter::int('id', 1)]));
        foreach ($contacts as $contact) {
            /* to avoid alert */
            $dummy = $contact;
        }
        expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
    })->throws(ConnectionException::class);

    it(
        'test CentreonDB : iterateAssociativeIndexed with an incorrect query',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $contacts = $db->iterateAssociativeIndexed("foo", QueryParameters::create([QueryParameter::int('id', 1)]));
            foreach ($contacts as $contact) {
                /* to avoid alert */
                $dummy = $contact;
            }
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    )->throws(ConnectionException::class);

    it(
        'test CentreonDB : iterateAssociativeIndexed with an incorrect query parameters',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $contacts = $db->iterateAssociativeIndexed(
                "SELECT contact_id, contact_name, contact_alias FROM contact WHERE contact_id = :id",
                QueryParameters::create([QueryParameter::string('name', 'foo_name')])
            );
            foreach ($contacts as $contact) {
                /* to avoid alert */
                $dummy = $contact;
            }
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    )->throws(ConnectionException::class);

    // ----------------------------------- QUERY ON SEVERAL DATABASES -------------------------------------

    it(
        'test DatabaseConnection : execute query on several databases with success',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            // get log actions done by admin
            $sql = <<<'SQL'
                SELECT * FROM `centreon_storage`.`log_action` AS la 
                    INNER JOIN `centreon`.`contact` AS c
                        ON la.log_contact_id = c.contact_id
                WHERE c.contact_id = :contact_id;
                SQL;
            $logActions = $db->fetchAllAssociative(
                $sql,
                QueryParameters::create([QueryParameter::int('contact_id', 1)])
            );
            expect($logActions)->toBeArray();
        }
    );

    // ----------------------------------------- TRANSACTIONS -----------------------------------------

    it('test CentreonDB : execute startTransaction with success', function () use ($dbConfigCentreon): void {
        $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
        $db->startTransaction();
        expect($db->isTransactionActive())->toBeTrue();
    });

    it('test CentreonDB : execute commit with success', function () use ($dbConfigCentreon): void {
        $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
        $db->startTransaction();
        $response = $db->commitTransaction();
        expect($response)->toBeTrue()
            ->and($db->isTransactionActive())->toBeFalse();
    });

    it('test CentreonDB : execute rollback with success', function () use ($dbConfigCentreon): void {
        $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
        $db->startTransaction();
        $response = $db->rollBackTransaction();
        expect($response)->toBeTrue()
            ->and($db->isTransactionActive())->toBeFalse();
    });

    // ------------------------------------- UNBUFFERED QUERIES -----------------------------------------

    it('test CentreonDB : allow unbuffered query with success', function () use ($dbConfigCentreon): void {
        $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
        expect($db->allowUnbufferedQuery())->toBeTrue();
    });

    it('test CentreonDB : execute unbufferedQuery with correct query', function () use ($dbConfigCentreon): void {
        $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
        $db->startUnbufferedQuery();
        expect($db->isUnbufferedQueryActive())->toBeTrue()
            ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class)
            ->and($db->getAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY))->toBe(0);
        $pdoStmt = $db->prepare("SELECT * FROM contact WHERE contact_id = 1");
        $pdoStmt->execute();
        $contact = $pdoStmt->fetch(PDO::FETCH_ASSOC);
        expect($contact)->toBeArray()->toHaveKey('contact_id', 1)
            ->and($db->isUnbufferedQueryActive())->toBeTrue()
            ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class)
            ->and($db->getAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY))->toBe(0);
        $db->stopUnbufferedQuery();
        expect($db->isUnbufferedQueryActive())->toBeFalse()
            ->and($db->getAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY))->toBe(1);
    });

    it(
        'test CentreonDB : stop unbuffered query without start unbuffered query',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
            $db->stopUnbufferedQuery();
        }
    )->throws(ConnectionException::class);

    // ---------------------------------------- BASE METHOD ----------------------------------------------

    // -- closeQuery

    it(
        'test CentreonDB : execute closeQuery with success',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $pdoSth = $db->prepareQuery("select * from contact where contact_id = :contact_id");
            $pdoSth->execute(['contact_id' => 1]);
            $successClose = $db->closeQuery($pdoSth);
            expect($successClose)->toBeTrue();
        }
    );

    // ====================================== DEPRECATED METHODS ===========================================

    // -- prepareQuery

    it(
        'test DEPRECATED CentreonDB : prepare query with a correct prepared query',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $pdoSth = $db->prepareQuery("select * from contact where contact_id = :contact_id");
            expect($pdoSth)->toBeInstanceOf(PDOStatement::class)
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    );

    it(
        'test DEPRECATED CentreonDB : prepare query with an empty prepared query',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $db->prepareQuery("");
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    )->throws(CentreonDbException::class);

    // -- makeBindValue

    it(
        'test DEPRECATED CentreonDB : execute makeBindValue with success',
        function () use ($dbConfigCentreon): void {
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
        'test DEPRECATED CentreonDB : execute makeBindValue with an empty param name',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $pdoSth = $db->prepareQuery("select * from contact where contact_id = :contact_id");
            expect($pdoSth)->toBeInstanceOf(PDOStatement::class);
            $db->makeBindValue($pdoSth, '', 1, PDO::PARAM_INT);
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    )->throws(CentreonDbException::class);

    it(
        'test DEPRECATED CentreonDB : execute makeBindValue with a type that doesnt exist',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $pdoSth = $db->prepareQuery("select * from contact where contact_id = :contact_id");
            expect($pdoSth)->toBeInstanceOf(PDOStatement::class);
            $db->makeBindValue($pdoSth, 'contact_id', 1, 999999);
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    )->throws(CentreonDbException::class);

    // -- makeBindParam

    it(
        'test DEPRECATED CentreonDB : execute makeBindParam with success',
        function () use ($dbConfigCentreon): void {
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
        'test DEPRECATED CentreonDB : execute makeBindParam with an empty param name',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $pdoSth = $db->prepareQuery("select * from contact where contact_id = :contact_id");
            expect($pdoSth)->toBeInstanceOf(PDOStatement::class);
            $contactID = 1;
            $db->makeBindParam($pdoSth, '', $contactID, PDO::PARAM_INT);
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    )->throws(CentreonDbException::class);

    it(
        'test DEPRECATED CentreonDB : execute makeBindParam with a type that doesnt exist',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $pdoSth = $db->prepareQuery("select * from contact where contact_id = :contact_id");
            expect($pdoSth)->toBeInstanceOf(PDOStatement::class);
            $contactID = 1;
            $db->makeBindParam($pdoSth, 'contact_id', $contactID, 9999999);
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    )->throws(CentreonDbException::class);

    // -- fetch()

    it(
        'test DEPRECATED CentreonDB : execute fetch with a correct query',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $pdoSth = $db->prepare("select * from contact");
            expect($pdoSth)->toBeInstanceOf(PDOStatement::class);
            $pdoSth->execute();
            $contact = $db->fetch($pdoSth);
            expect($contact)->toBeArray()
                ->and($contact['contact_id'])->toBe(1)
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    );

    // -- fetchAll()

    it(
        'test DEPRECATED CentreonDB : execute fetchAll a correct query',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $pdoSth = $db->prepare("select * from contact");
            expect($pdoSth)->toBeInstanceOf(PDOStatement::class);
            $pdoSth->execute();
            $contact = $db->fetchAll($pdoSth);
            expect($contact)->toBeArray()
                ->and($contact[0]['contact_id'])->toBe(1)
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    );

    // -- execute()

    it(
        'test DEPRECATED CentreonDB : execute execute with correct query',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $pdoSth = $db->prepareQuery("select * from contact where contact_id = :contact_id");
            expect($pdoSth)->toBeInstanceOf(PDOStatement::class);
            $successExecute = $db->execute($pdoSth, ['contact_id' => 1]);
            expect($successExecute)->toBeTrue()
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    );

    // -- executeQuery()

    it(
        'test DEPRECATED CentreonDB : execute executeQuery with a correct query with associative fetch mode',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $pdoSth = $db->executeQuery("SELECT * FROM contact WHERE contact_id = 1", PDO::FETCH_ASSOC);
            expect($pdoSth)->toBeInstanceOf(PDOStatement::class);
            $contact = $pdoSth->fetch();
            expect($contact)->toBeArray()->toHaveKey('contact_id', 1)
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    );

    it(
        'test DEPRECATED CentreonDB : execute executeQuery with a correct query with numeric fetch mode',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $pdoSth = $db->executeQuery("SELECT * FROM contact WHERE contact_id = 1", PDO::FETCH_NUM);
            expect($pdoSth)->toBeInstanceOf(PDOStatement::class);
            $contact = $pdoSth->fetch();
            expect($contact)->toBeArray()->toHaveKey(0, 1)
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    );

    it(
        'test DEPRECATED CentreonDB : execute executeQuery with a correct query with column fetch mode',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $pdoSth = $db->executeQuery("SELECT * FROM contact WHERE contact_id = 1", PDO::FETCH_COLUMN, [0]);
            expect($pdoSth)->toBeInstanceOf(PDOStatement::class);
            $contact = $pdoSth->fetch();
            expect($contact)->toBeInt()->toBe(1)
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    );

    it(
        'test DEPRECATED CentreonDB : execute executeQuery with a correct query with object fetch mode',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $pdoSth = $db->executeQuery("SELECT * FROM contact WHERE contact_id = 1", PDO::FETCH_OBJ);
            expect($pdoSth)->toBeInstanceOf(PDOStatement::class);
            $contact = $pdoSth->fetch();
            expect($contact)->toBeObject()->toHaveProperty('contact_id', 1)
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    );

    it(
        'test DEPRECATED CentreonDB : execute executeQuery with an incorrect query',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $db->executeQuery("foo");
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    )->throws(CentreonDbException::class);

    it(
        'test DEPRECATED CentreonDB : execute executeQuery with an empty query',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $db->executeQuery("");
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    )->throws(CentreonDbException::class);

    // -- executePreparedQuery()

    it(
        'test DEPRECATED CentreonDB : execute prepared query with a correct prepared query ',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $pdoSth = $db->prepareQuery("select * from contact where contact_id = :contact_id");
            expect($pdoSth)->toBeInstanceOf(PDOStatement::class);
            $result = $db->executePreparedQuery($pdoSth, ['contact_id' => 1]);
            expect($result)->toBeTrue()
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    );

    it(
        'test DEPRECATED CentreonDB : execute prepared query with an empty array for $bindParams',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $pdoSth = $db->prepareQuery("select * from contact where contact_id = :contact_id");
            expect($pdoSth)->toBeInstanceOf(PDOStatement::class);
            $db->executePreparedQuery($pdoSth, []);
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    )->throws(CentreonDbException::class);

    it(
        'test DEPRECATED CentreonDB : execute prepared query with an incorrect prepared query',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $pdoSth = $db->prepareQuery("foo");
            expect($pdoSth)->toBeInstanceOf(PDOStatement::class);
            $db->executePreparedQuery($pdoSth, ['contact_id' => 1]);
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    )->throws(CentreonDbException::class);

    it(
        'test DEPRECATED CentreonDB : execute prepared query with typing binding params with a correct prepared query',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $pdoSth = $db->prepareQuery("select * from contact where contact_id = :contact_id");
            expect($pdoSth)->toBeInstanceOf(PDOStatement::class);
            $result = $db->executePreparedQuery($pdoSth, ['contact_id' => [1, PDO::PARAM_INT]], true);
            expect($result)->toBeTrue()
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    );

    it(
        'test DEPRECATED CentreonDB : execute prepared query with typing binding params with an unexpected type of param',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $pdoSth = $db->prepareQuery("select * from contact where contact_id = :contact_id");
            expect($pdoSth)->toBeInstanceOf(PDOStatement::class);
            $db->executePreparedQuery($pdoSth, ['contact_id' => [1, 'foo']], true);
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    )->throws(CentreonDbException::class);

    it(
        'test DEPRECATED CentreonDB : execute prepared query with typing binding params with no array for the value of $bindParams',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $pdoSth = $db->prepareQuery("select * from contact where contact_id = :contact_id");
            expect($pdoSth)->toBeInstanceOf(PDOStatement::class);
            $db->executePreparedQuery($pdoSth, ['contact_id' => 1], true);
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    )->throws(CentreonDbException::class);

    it(
        'test DEPRECATED CentreonDB : execute prepared query with typing binding params with no type for the value of $bindParams',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $pdoSth = $db->prepareQuery("select * from contact where contact_id = :contact_id");
            expect($pdoSth)->toBeInstanceOf(PDOStatement::class);
            $db->executePreparedQuery($pdoSth, ['contact_id' => [1]], true);
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    )->throws(CentreonDbException::class);

    // -- executeQueryFetchAll()

    it(
        'test DEPRECATED CentreonDB : execute executeQueryFetchAll query with a correct query',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $contacts = $db->executeQueryFetchAll("select * from contact");
            expect($contacts)->toBeArray()
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    );

    it(
        'test DEPRECATED CentreonDB : execute executeQueryFetchAll query with an empty query',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $db->executeQueryFetchAll("");
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    )->throws(CentreonDbException::class);

    // -- executeQueryFetchColumn()

    it(
        'test DEPRECATED CentreonDB : execute executeQueryFetchColumn query with a correct query',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $contactsIds = $db->executeQueryFetchColumn("select * from contact");
            expect($contactsIds)->toBeArray()
                ->and($contactsIds[0])->toBeInt()
                ->and($contactsIds[0])->toBe(1)
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    );

    it(
        'test DEPRECATED CentreonDB : execute executeQueryFetchColumn query with a column option with a correct query',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $alias = $db->executeQueryFetchColumn("select * from contact", 4);
            expect($alias)->toBeArray()
                ->and($alias[0])->toBeString()
                ->and($alias[0])->toBe('admin')
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    );

    it(
        'test DEPRECATED CentreonDB : execute executeQueryFetchColumn query with an empty query',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $db->executeQueryFetchColumn("");
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    )->throws(CentreonDbException::class);

    // -- fetchColumn()

    it(
        'test DEPRECATED CentreonDB : execute fetchColumn a correct query',
        function () use ($dbConfigCentreon): void {
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
        'test DEPRECATED CentreonDB : execute fetchColumn a column that doesnt exist',
        function () use ($dbConfigCentreon): void {
            $db = CentreonDB::connectToCentreonDb($dbConfigCentreon);
            $pdoSth = $db->executeQuery("select * from contact");
            expect($pdoSth)->toBeInstanceOf(PDOStatement::class);
            $db->fetchColumn($pdoSth, 99999999);
            expect($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    )->throws(CentreonDbException::class);

    // -- escapeString()

    it(
        'test DEPRECATED CentreonDB : execute escapeString with success',
        function () use ($dbConfigCentreon): void {
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
        function () use ($dbConfigCentreonStorage): void {
            $db = new CentreonDB(dbLabel: CentreonDB::LABEL_DB_REALTIME, connectionConfig: $dbConfigCentreonStorage);
            expect($db)->toBeInstanceOf(CentreonDB::class);
            $dbName = $db->executeQuery("select database()")->fetchColumn();
            expect($dbName)->toBe('centreon_storage')
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    );

    it(
        'connect to centreon_storage database with CentreonDB::connectToCentreonStorageDb factory',
        function () use ($dbConfigCentreonStorage): void {
            $db = CentreonDB::connectToCentreonStorageDb($dbConfigCentreonStorage);
            expect($db)->toBeInstanceOf(CentreonDB::class);
            $dbName = $db->executeQuery("select database()")->fetchColumn();
            expect($dbName)->toBe('centreon_storage')
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    );

    it(
        'test CentreonDB : CentreonDB::createFromConfig factory with centreon_storage database',
        function () use ($dbConfigCentreonStorage): void {
            $db = CentreonDB::createFromConfig($dbConfigCentreonStorage);
            expect($db)->toBeInstanceOf(CentreonDB::class);
            $stmt = $db->prepare("select database()");
            $stmt->execute();
            $dbName = $stmt->fetchColumn();
            expect($dbName)->toBe('centreon_storage')
                ->and($db->getAttribute(PDO::ATTR_STATEMENT_CLASS)[0])->toBe(CentreonDBStatement::class);
        }
    );

} else {
    it('no centreon_storage database available for testing the CentreonDB connector, so these tests were ignored');
}
