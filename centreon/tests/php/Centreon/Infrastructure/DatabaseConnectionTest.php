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

use Adaptation\Database\ExpressionBuilder\Adapter\Dbal\DbalExpressionBuilderAdapter;
use Adaptation\Database\ExpressionBuilder\ExpressionBuilderInterface;
use Adaptation\Database\QueryBuilder\Adapter\Dbal\DbalQueryBuilderAdapter;
use Adaptation\Database\Connection\Collection\BatchInsertParameters;
use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\Exception\ConnectionException;
use Adaptation\Database\Connection\Model\ConnectionConfig;
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use Adaptation\Database\QueryBuilder\QueryBuilderInterface;
use Centreon\Infrastructure\DatabaseConnection;

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
        databaseName: 'centreon',
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

// ************************************** With centreon database connection *******************************************

if (! is_null($dbConfigCentreon) && hasConnectionDb($dbConfigCentreon)) {
    it(
        'test DatabaseConnection : DatabaseConnection constructor',
        function () use ($dbConfigCentreon): void {
            $db = new DatabaseConnection($dbConfigCentreon);
            expect($db)->toBeInstanceOf(DatabaseConnection::class);
            $stmt = $db->prepare("select database()");
            $stmt->execute();
            $dbName = $stmt->fetchColumn();
            expect($dbName)->toBe('centreon')
                ->and($db->getAttribute(\PDO::ATTR_STATEMENT_CLASS)[0])->toBe(\PDOStatement::class);
        }
    );

    it(
        'test DatabaseConnection : DatabaseConnection::createFromConfig factory"',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
            expect($db)->toBeInstanceOf(DatabaseConnection::class);
            $stmt = $db->prepare("select database()");
            $stmt->execute();
            $dbName = $stmt->fetchColumn();
            expect($dbName)->toBe('centreon')
                ->and($db->getAttribute(\PDO::ATTR_STATEMENT_CLASS)[0])->toBe(\PDOStatement::class);
        }
    );

    it(
        'test DatabaseConnection : create query builder with success',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
            $queryBuilder = $db->createQueryBuilder();
            expect($queryBuilder)
                ->toBeInstanceOf(QueryBuilderInterface::class)
                ->toBeInstanceOf(DbalQueryBuilderAdapter::class);
        }
    );

    it(
        'test DatabaseConnection : create expression builder with success',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
            $expressionBuilder = $db->createExpressionBuilder();
            expect($expressionBuilder)
                ->toBeInstanceOf(ExpressionBuilderInterface::class)
                ->toBeInstanceOf(DbalExpressionBuilderAdapter::class);
        }
    );

    it(
        'test DatabaseConnection : switch to database',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
            $db->switchToDb('centreon_storage');
            expect($db->getDatabaseName())->toBe('centreon_storage');
            $db->switchToDb('centreon');
            expect($db->getDatabaseName())->toBe('centreon');
        }
    );

    it(
        'test DatabaseConnection : get connection config',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
            $connectionConfig = $db->getConnectionConfig();
            expect($connectionConfig)->toBeInstanceOf(ConnectionConfig::class)
                ->and($connectionConfig->getHost())->toBe($dbConfigCentreon->getHost())
                ->and($connectionConfig->getUser())->toBe($dbConfigCentreon->getUser())
                ->and($connectionConfig->getPassword())->toBe($dbConfigCentreon->getPassword())
                ->and($connectionConfig->getDatabaseName())->toBe($dbConfigCentreon->getDatabaseName())
                ->and($connectionConfig->getPort())->toBe($dbConfigCentreon->getPort());
        }
    );

    it(
        'test DatabaseConnection : get the database name of the current connection',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
            $dbName = $db->getDatabaseName();
            expect($dbName)->toBe('centreon');
        }
    );

    it('test DatabaseConnection : get native connection', function () use ($dbConfigCentreon): void {
        $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
        $pdo = $db->getNativeConnection();
        expect($pdo)->toBeInstanceOf(\PDO::class);
    });

    it('test DatabaseConnection : get last insert id', function () use ($dbConfigCentreon): void {
        $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
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

    it('test DatabaseConnection : is connected', function () use ($dbConfigCentreon): void {
        $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
        expect($db->isConnected())->toBeTrue();
    });

    it('test DatabaseConnection : quote string', function () use ($dbConfigCentreon): void {
        $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
        $quotedString = $db->quote("foo");
        expect($quotedString)->toBeString()->toBe("'foo'");
    });

    // --------------------------------------- CUD METHODS -----------------------------------------

    // -- executeStatement()

    it(
        'test DatabaseConnection : execute statement with a correct query without query parameters',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
            $inserted = $db->executeStatement(
                "INSERT INTO contact(contact_id, contact_name, contact_alias) VALUES(110, 'foo_name', 'foo_alias')"
            );
            expect($inserted)->toBeInt()->toBe(1);
            // clean up the database
            $deleted = $db->exec("DELETE FROM contact WHERE contact_id = 110");
            expect($deleted)->toBeInt()->toBe(1);
        }
    );

    it(
        'test DatabaseConnection : execute statement with a correct query with query parameters',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
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
            expect($inserted)->toBeInt()->toBe(1);
            // clean up the database
            $deleted = $db->exec("DELETE FROM contact WHERE contact_id = 110");
            expect($deleted)->toBeInt()->toBe(1);
        }
    );

    it('test DatabaseConnection : execute statement with a SELECT query', function () use ($dbConfigCentreon): void {
        $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
        $db->executeStatement("SELECT * FROM contact");
    })->throws(ConnectionException::class);

    it('test DatabaseConnection : execute statement with an empty query', function () use ($dbConfigCentreon): void {
        $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
        $db->executeStatement("");
    })->throws(ConnectionException::class);

    it(
        'test DatabaseConnection : execute statement with an incorrect query',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
            $db->executeStatement("foo");
        }
    )->throws(ConnectionException::class);

    it(
        'test DatabaseConnection : execute statement with an incorrect query parameters',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
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
        }
    )->throws(ConnectionException::class);

    // -- insert()

    it(
        'test DatabaseConnection : insert with a correct query with query parameters',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
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
            expect($inserted)->toBeInt()->toBe(1);
            // clean up the database
            $deleted = $db->exec("DELETE FROM contact WHERE contact_id = 110");
            expect($deleted)->toBeInt()->toBe(1);
        }
    );

    it('test DatabaseConnection : insert with a SELECT query', function () use ($dbConfigCentreon): void {
        $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
        $db->insert(
            "SELECT * FROM contact WHERE contact_id = :id",
            QueryParameters::create([QueryParameter::int('contact_id', 110)])
        );
    })->throws(ConnectionException::class);

    it('test DatabaseConnection : insert with an empty query', function () use ($dbConfigCentreon): void {
        $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
        $db->insert("", QueryParameters::create([QueryParameter::int('contact_id', 110)]));
    })->throws(ConnectionException::class);

    it('test DatabaseConnection : insert with an incorrect query', function () use ($dbConfigCentreon): void {
        $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
        $db->insert("foo", QueryParameters::create([QueryParameter::int('contact_id', 110)]));
    })->throws(ConnectionException::class);

    it(
        'test DatabaseConnection : insert with an incorrect query parameters',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
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
        }
    )->throws(ConnectionException::class);

    // -- batchInsert()

    it(
        'test DatabaseConnection : batch insert with a correct query with batch query parameters',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
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
            expect($inserted)->toBeInt()->toBe(3);
            // clean up the database
            $deleted = $db->exec("DELETE FROM contact WHERE contact_id IN (110,111,112)");
            expect($deleted)->toBeInt()->toBe(3);
        }
    );

    it(
        'test DatabaseConnection : batch insert with a correct query with empty batch query parameters',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
            $db->batchInsert(
                'contact',
                ['contact_id', 'contact_name', 'contact_alias'],
                BatchInsertParameters::create([])
            );
        }
    )->throws(ConnectionException::class);

    it(
        'test DatabaseConnection : batch insert with an incorrect batch query parameters',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
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
        }
    )->throws(ConnectionException::class);

    // -- update()

    it(
        'test DatabaseConnection : update with a correct query with query parameters',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
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
            expect($updated)->toBeInt()->toBe(1);
            // clean up the database
            $delete = $db->exec("DELETE FROM contact WHERE contact_id = 110");
            expect($delete)->toBeInt()->toBe(1);
        }
    );

    it('test DatabaseConnection : update with a SELECT query', function () use ($dbConfigCentreon): void {
        $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
        $db->update(
            "SELECT * FROM contact WHERE contact_id = :id",
            QueryParameters::create([QueryParameter::int('contact_id', 110)])
        );
    })->throws(ConnectionException::class);

    it('test DatabaseConnection : update with an empty query', function () use ($dbConfigCentreon): void {
        $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
        $db->update("", QueryParameters::create([QueryParameter::int('contact_id', 110)]));
    })->throws(ConnectionException::class);

    it('test DatabaseConnection : update with an incorrect query', function () use ($dbConfigCentreon): void {
        $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
        $db->update("foo", QueryParameters::create([QueryParameter::int('contact_id', 110)]));
    })->throws(ConnectionException::class);

    it(
        'test DatabaseConnection : update with an incorrect query parameters',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
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
        }
    )->throws(ConnectionException::class);

    // -- delete()

    it(
        'test DatabaseConnection : delete with a correct query with query parameters',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
            $inserted = $db->executeStatement(
                "INSERT INTO contact(contact_id, contact_name, contact_alias) VALUES(110, 'foo_name', 'foo_alias')"
            );
            expect($inserted)->toBeInt()->toBe(1);
            $queryParameters = QueryParameters::create([QueryParameter::int('id', 110)]);
            $deleted = $db->delete("DELETE FROM contact WHERE contact_id = :id", $queryParameters);
            expect($deleted)->toBeInt()->toBe(1);
        }
    );

    it('test DatabaseConnection : delete with a SELECT query', function () use ($dbConfigCentreon): void {
        $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
        $db->delete(
            "SELECT * FROM contact WHERE contact_id = :id",
            QueryParameters::create([QueryParameter::int('contact_id', 110)])
        );
    })->throws(ConnectionException::class);

    it('test DatabaseConnection : delete with an empty query', function () use ($dbConfigCentreon): void {
        $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
        $db->delete("", QueryParameters::create([QueryParameter::int('contact_id', 110)]));
    })->throws(ConnectionException::class);

    it('test DatabaseConnection : delete with an incorrect query', function () use ($dbConfigCentreon): void {
        $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
        $db->delete("foo", QueryParameters::create([QueryParameter::int('contact_id', 110)]));
    })->throws(ConnectionException::class);

    it(
        'test DatabaseConnection : delete with an incorrect query parameters',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
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
        }
    )->throws(ConnectionException::class);

    // ---------------------------------------- FETCH METHODS ----------------------------------------------

    // -- fetchNumeric()

    it(
        'test DatabaseConnection : fetchNumeric with a correct query with query parameters',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
            $contact = $db->fetchNumeric(
                "SELECT * FROM contact WHERE contact_id = :id",
                QueryParameters::create([QueryParameter::int('id', 1)])
            );
            expect($contact)->toBeArray()
                ->and($contact[0])->toBe(1);
        }
    );

    it('test DatabaseConnection : fetchNumeric with a CUD query', function () use ($dbConfigCentreon): void {
        $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
        $db->fetchNumeric(
            "DELETE FROM contact WHERE contact_id = :id",
            QueryParameters::create([QueryParameter::int('id', 1)])
        );
    })->throws(ConnectionException::class);

    it('test DatabaseConnection : fetchNumeric with an empty query', function () use ($dbConfigCentreon): void {
        $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
        $db->fetchNumeric("", QueryParameters::create([QueryParameter::int('id', 1)]));
    })->throws(ConnectionException::class);

    it('test DatabaseConnection : fetchNumeric with an incorrect query', function () use ($dbConfigCentreon): void {
        $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
        $db->fetchNumeric("foo", QueryParameters::create([QueryParameter::int('id', 1)]));
    })->throws(ConnectionException::class);

    it(
        'test DatabaseConnection : fetchNumeric with an incorrect query parameters',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
            $db->fetchNumeric(
                "SELECT * FROM contact WHERE contact_id = :id",
                QueryParameters::create([QueryParameter::string('name', 'foo_name')])
            );
        }
    )->throws(ConnectionException::class);

    // -- fetchAssociative()

    it(
        'test DatabaseConnection : fetchAssociative with a correct query with query parameters',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
            $contact = $db->fetchAssociative(
                "SELECT * FROM contact WHERE contact_id = :id",
                QueryParameters::create([QueryParameter::int('id', 1)])
            );
            expect($contact)->toBeArray()
                ->and($contact['contact_id'])->toBe(1);
        }
    );

    it('test DatabaseConnection : fetchAssociative with a CUD query', function () use ($dbConfigCentreon): void {
        $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
        $db->fetchAssociative(
            "DELETE FROM contact WHERE contact_id = :id",
            QueryParameters::create([QueryParameter::int('id', 1)])
        );
    })->throws(ConnectionException::class);

    it('test DatabaseConnection : fetchAssociative with an empty query', function () use ($dbConfigCentreon): void {
        $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
        $db->fetchAssociative("", QueryParameters::create([QueryParameter::int('id', 1)]));
    })->throws(ConnectionException::class);

    it('test DatabaseConnection : fetchAssociative with an incorrect query', function () use ($dbConfigCentreon): void {
        $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
        $db->fetchAssociative("foo", QueryParameters::create([QueryParameter::int('id', 1)]));
    })->throws(ConnectionException::class);

    it(
        'test DatabaseConnection : fetchAssociative with an incorrect query parameters',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
            $db->fetchAssociative(
                "SELECT * FROM contact WHERE contact_id = :id",
                QueryParameters::create([QueryParameter::string('name', 'foo_name')])
            );
        }
    )->throws(ConnectionException::class);

    // -- fetchOne()

    it(
        'test DatabaseConnection : fetchOne with a correct query with query parameters',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
            $alias = $db->fetchOne(
                "SELECT contact_alias FROM contact WHERE contact_id = :id",
                QueryParameters::create([QueryParameter::int('id', 1)])
            );
            expect($alias)->toBeString()->toBe('admin');
        }
    );

    it('test DatabaseConnection : fetchOne with a CUD query', function () use ($dbConfigCentreon): void {
        $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
        $db->fetchOne(
            "DELETE FROM contact WHERE contact_id = :id",
            QueryParameters::create([QueryParameter::int('id', 1)])
        );
    })->throws(ConnectionException::class);

    it('test DatabaseConnection : fetchOne with an empty query', function () use ($dbConfigCentreon): void {
        $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
        $db->fetchOne("", QueryParameters::create([QueryParameter::int('id', 1)]));
    })->throws(ConnectionException::class);

    it('test DatabaseConnection : fetchOne with an incorrect query', function () use ($dbConfigCentreon): void {
        $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
        $db->fetchOne("foo", QueryParameters::create([QueryParameter::int('id', 1)]));
    })->throws(ConnectionException::class);

    it(
        'test DatabaseConnection : fetchOne with an incorrect query parameters',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
            $db->fetchOne(
                "SELECT contact_alias FROM contact WHERE contact_id = :id",
                QueryParameters::create([QueryParameter::string('name', 'foo_name')])
            );
        }
    )->throws(ConnectionException::class);

    // -- fetchFirstColumn()

    it(
        'test DatabaseConnection : fetchFirstColumn with a correct query with query parameters',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
            $contact = $db->fetchFirstColumn(
                "SELECT contact_id FROM contact ORDER BY contact_id",
            );
            expect($contact)->toBeArray()
                ->and($contact[0])->toBeInt()->toBe(1);
        }
    );

    it(
        'test DatabaseConnection : fetchFirstColumn with a correct query with query parameters and another column',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
            $contact = $db->fetchFirstColumn("SELECT contact_alias FROM contact ORDER BY contact_id");
            expect($contact)->toBeArray()
                ->and($contact[0])->toBeString()->toBe('admin');
        }
    );

    it('test DatabaseConnection : fetchFirstColumn with a CUD query', function () use ($dbConfigCentreon): void {
        $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
        $db->fetchFirstColumn(
            "DELETE FROM contact WHERE contact_id = :id",
            QueryParameters::create([QueryParameter::int('id', 1)])
        );
    })->throws(ConnectionException::class);

    it('test DatabaseConnection : fetchFirstColumn with an empty query', function () use ($dbConfigCentreon): void {
        $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
        $db->fetchFirstColumn("", QueryParameters::create([QueryParameter::int('id', 1)]));
    })->throws(ConnectionException::class);

    it('test DatabaseConnection : fetchFirstColumn with an incorrect query', function () use ($dbConfigCentreon): void {
        $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
        $db->fetchFirstColumn("foo", QueryParameters::create([QueryParameter::int('id', 1)]));
    })->throws(ConnectionException::class);

    it(
        'test DatabaseConnection : fetchFirstColumn with an incorrect query parameters',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
            $db->fetchFirstColumn(
                "SELECT contact_alias FROM contact WHERE contact_id = :id",
                QueryParameters::create([QueryParameter::string('name', 'foo_name')])
            );
        }
    )->throws(ConnectionException::class);

    // -- fetchAllNumeric()

    it(
        'test DatabaseConnection : fetchAllNumeric with a correct query with query parameters',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
            $contact = $db->fetchAllNumeric(
                "SELECT * FROM contact WHERE contact_id = :id",
                QueryParameters::create([QueryParameter::int('id', 1)])
            );
            expect($contact)->toBeArray()
                ->and($contact[0][0])->toBe(1);
        }
    );

    it('test DatabaseConnection : fetchAllNumeric with a CUD query', function () use ($dbConfigCentreon): void {
        $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
        $db->fetchAllNumeric(
            "DELETE FROM contact WHERE contact_id = :id",
            QueryParameters::create([QueryParameter::int('id', 1)])
        );
    })->throws(ConnectionException::class);

    it('test DatabaseConnection : fetchAllNumeric with an empty query', function () use ($dbConfigCentreon): void {
        $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
        $db->fetchAllNumeric("", QueryParameters::create([QueryParameter::int('id', 1)]));
    })->throws(ConnectionException::class);

    it('test DatabaseConnection : fetchAllNumeric with an incorrect query', function () use ($dbConfigCentreon): void {
        $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
        $db->fetchAllNumeric("foo", QueryParameters::create([QueryParameter::int('id', 1)]));
    })->throws(ConnectionException::class);

    it(
        'test DatabaseConnection : fetchAllNumeric with an incorrect query parameters',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
            $db->fetchAllNumeric(
                "SELECT * FROM contact WHERE contact_id = :id",
                QueryParameters::create([QueryParameter::string('name', 'foo_name')])
            );
        }
    )->throws(ConnectionException::class);

    // -- fetchAllAssociative()

    it(
        'test DatabaseConnection : fetchAllAssociative with a correct query with query parameters',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
            $contact = $db->fetchAllAssociative(
                "SELECT * FROM contact WHERE contact_id = :id",
                QueryParameters::create([QueryParameter::int('id', 1)])
            );
            expect($contact)->toBeArray()
                ->and($contact[0]['contact_id'])->toBe(1);
        }
    );

    it('test DatabaseConnection : fetchAllAssociative with a CUD query', function () use ($dbConfigCentreon): void {
        $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
        $db->fetchAllAssociative(
            "DELETE FROM contact WHERE contact_id = :id",
            QueryParameters::create([QueryParameter::int('id', 1)])
        );
    })->throws(ConnectionException::class);

    it('test DatabaseConnection : fetchAllAssociative with an empty query', function () use ($dbConfigCentreon): void {
        $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
        $db->fetchAllAssociative("", QueryParameters::create([QueryParameter::int('id', 1)]));
    })->throws(ConnectionException::class);

    it(
        'test DatabaseConnection : fetchAllAssociative with an incorrect query',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
            $db->fetchAllAssociative("foo", QueryParameters::create([QueryParameter::int('id', 1)]));
        }
    )->throws(ConnectionException::class);

    it(
        'test DatabaseConnection : fetchAllAssociative with an incorrect query parameters',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
            $db->fetchAllAssociative(
                "SELECT * FROM contact WHERE contact_id = :id",
                QueryParameters::create([QueryParameter::string('name', 'foo_name')])
            );
        }
    )->throws(ConnectionException::class);

    // -- fetchAllKeyValue()

    it(
        'test DatabaseConnection : fetchAllKeyValue with a correct query with query parameters',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
            $contact = $db->fetchAllKeyValue(
                "SELECT contact_id, contact_alias FROM contact WHERE contact_id = :id",
                QueryParameters::create([QueryParameter::int('id', 1)])
            );
            expect($contact)->toBeArray()
                ->and($contact)->toBeArray()->toBe(['1' => 'admin']);
        }
    );

    it('test DatabaseConnection : fetchAllKeyValue with a CUD query', function () use ($dbConfigCentreon): void {
        $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
        $db->fetchAllKeyValue(
            "DELETE FROM contact WHERE contact_id = :id",
            QueryParameters::create([QueryParameter::int('id', 1)])
        );
    })->throws(ConnectionException::class);

    it('test DatabaseConnection : fetchAllKeyValue with an empty query', function () use ($dbConfigCentreon): void {
        $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
        $db->fetchAllKeyValue("", QueryParameters::create([QueryParameter::int('id', 1)]));
    })->throws(ConnectionException::class);

    it('test DatabaseConnection : fetchAllKeyValue with an incorrect query', function () use ($dbConfigCentreon): void {
        $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
        $db->fetchAllKeyValue("foo", QueryParameters::create([QueryParameter::int('id', 1)]));
    })->throws(ConnectionException::class);

    it(
        'test DatabaseConnection : fetchAllKeyValue with an incorrect query parameters',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
            $db->fetchAllKeyValue(
                "SELECT contact_id, contact_alias FROM contact WHERE contact_id = :id",
                QueryParameters::create([QueryParameter::string('name', 'foo_name')])
            );
        }
    )->throws(ConnectionException::class);

    // -- fetchAllAssociativeIndexed()

    it(
        'test DatabaseConnection : fetchAllAssociativeIndexed with a correct query with query parameters',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
            $contact = $db->fetchAllAssociativeIndexed(
                "SELECT contact_id, contact_name, contact_alias FROM contact WHERE contact_id = :id",
                QueryParameters::create([QueryParameter::int('id', 1)])
            );
            expect($contact)->toBeArray()
                ->and($contact[1])->toBeArray()->toBe(['contact_name' => 'admin admin', 'contact_alias' => 'admin']);
        }
    );

    it(
        'test DatabaseConnection : fetchAllAssociativeIndexed with a CUD query',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
            $db->fetchAllAssociativeIndexed(
                "DELETE FROM contact WHERE contact_id = :id",
                QueryParameters::create([QueryParameter::int('id', 1)])
            );
        }
    )->throws(ConnectionException::class);

    it(
        'test DatabaseConnection : fetchAllAssociativeIndexed with an empty query',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
            $db->fetchAllAssociativeIndexed("", QueryParameters::create([QueryParameter::int('id', 1)]));
        }
    )->throws(ConnectionException::class);

    it(
        'test DatabaseConnection : fetchAllAssociativeIndexed with an incorrect query',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
            $db->fetchAllAssociativeIndexed("foo", QueryParameters::create([QueryParameter::int('id', 1)]));
        }
    )->throws(ConnectionException::class);

    it(
        'test DatabaseConnection : fetchAllAssociativeIndexed with an incorrect query parameters',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
            $db->fetchAllAssociativeIndexed(
                "SELECT contact_id, contact_name, contact_alias FROM contact WHERE contact_id = :id",
                QueryParameters::create([QueryParameter::string('name', 'foo_name')])
            );
        }
    )->throws(ConnectionException::class);

    // ---------------------------------------- ITERATE METHODS ----------------------------------------------

    // -- iterateNumeric()

    it(
        'test DatabaseConnection : iterateNumeric with a correct query with query parameters',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
            $contacts = $db->iterateNumeric(
                "SELECT * FROM contact WHERE contact_id = :id",
                QueryParameters::create([QueryParameter::int('id', 1)])
            );
            foreach ($contacts as $contact) {
                expect($contact)->toBeArray()->and($contact[0])->toBe(1);
            }
        }
    );

    it('test DatabaseConnection : iterateNumeric with a CUD query', function () use ($dbConfigCentreon): void {
        $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
        $contacts = $db->iterateNumeric(
            "DELETE FROM contact WHERE contact_id = :id",
            QueryParameters::create([QueryParameter::int('id', 1)])
        );
        foreach ($contacts as $contact) {
            /* to avoid alert */
            $dummy = $contact;
        }
    })->throws(ConnectionException::class);

    it('test DatabaseConnection : iterateNumeric with an empty query', function () use ($dbConfigCentreon): void {
        $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
        $contacts = $db->iterateNumeric("", QueryParameters::create([QueryParameter::int('id', 1)]));
        foreach ($contacts as $contact) {
            /* to avoid alert */
            $dummy = $contact;
        }
    })->throws(ConnectionException::class);

    it('test DatabaseConnection : iterateNumeric with an incorrect query', function () use ($dbConfigCentreon): void {
        $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
        $contacts = $db->iterateNumeric("foo", QueryParameters::create([QueryParameter::int('id', 1)]));
        foreach ($contacts as $contact) {
            /* to avoid alert */
            $dummy = $contact;
        }
    })->throws(ConnectionException::class);

    it(
        'test DatabaseConnection : iterateNumeric with an incorrect query parameters',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
            $contacts = $db->iterateNumeric(
                "SELECT * FROM contact WHERE contact_id = :id",
                QueryParameters::create([QueryParameter::string('name', 'foo_name')])
            );
            foreach ($contacts as $contact) {
                /* to avoid alert */
                $dummy = $contact;
            }
        }
    )->throws(ConnectionException::class);

    // -- iterateAssociative()

    it(
        'test DatabaseConnection : iterateAssociative with a correct query with query parameters',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
            $contacts = $db->iterateAssociative(
                "SELECT * FROM contact WHERE contact_id = :id",
                QueryParameters::create([QueryParameter::int('id', 1)])
            );
            foreach ($contacts as $contact) {
                expect($contact)->toBeArray()->and($contact['contact_id'])->toBe(1);
            }
        }
    );

    it('test DatabaseConnection : iterateAssociative with a CUD query', function () use ($dbConfigCentreon): void {
        $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
        $contacts = $db->iterateAssociative(
            "DELETE FROM contact WHERE contact_id = :id",
            QueryParameters::create([QueryParameter::int('id', 1)])
        );
        foreach ($contacts as $contact) {
            /* to avoid alert */
            $dummy = $contact;
        }
    })->throws(ConnectionException::class);

    it('test DatabaseConnection : iterateAssociative with an empty query', function () use ($dbConfigCentreon): void {
        $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
        $contacts = $db->iterateAssociative("", QueryParameters::create([QueryParameter::int('id', 1)]));
        foreach ($contacts as $contact) {
            /* to avoid alert */
            $dummy = $contact;
        }
    })->throws(ConnectionException::class);

    it(
        'test DatabaseConnection : iterateAssociative with an incorrect query',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
            $contacts = $db->iterateAssociative("foo", QueryParameters::create([QueryParameter::int('id', 1)]));
            foreach ($contacts as $contact) {
                /* to avoid alert */
                $dummy = $contact;
            }
        }
    )->throws(ConnectionException::class);

    it(
        'test DatabaseConnection : iterateAssociative with an incorrect query parameters',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
            $contacts = $db->iterateAssociative(
                "SELECT * FROM contact WHERE contact_id = :id",
                QueryParameters::create([QueryParameter::string('name', 'foo_name')])
            );
            foreach ($contacts as $contact) {
                /* to avoid alert */
                $dummy = $contact;
            }
        }
    )->throws(ConnectionException::class);

    // -- iterateColumn()

    it(
        'test DatabaseConnection : iterateColumn with a correct query with query parameters',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
            $contacts = $db->iterateColumn(
                "SELECT contact_id FROM contact WHERE contact_id = :id",
                QueryParameters::create([QueryParameter::int('id', 1)])
            );
            foreach ($contacts as $contact) {
                expect($contact)->toBeInt()->toBe(1);
            }
        }
    );

    it(
        'test DatabaseConnection : iterateColumn with a correct query with query parameters and another column',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
            $contacts = $db->iterateColumn(
                "SELECT contact_alias FROM contact WHERE contact_id = :id",
                QueryParameters::create([QueryParameter::int('id', 1)])
            );
            foreach ($contacts as $contact) {
                expect($contact)->toBeString()->toBe('admin');
            }
        }
    );

    it('test DatabaseConnection : iterateColumn with a CUD query', function () use ($dbConfigCentreon): void {
        $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
        $contacts = $db->iterateColumn(
            "DELETE FROM contact WHERE contact_id = :id",
            QueryParameters::create([QueryParameter::int('id', 1)])
        );
        foreach ($contacts as $contact) {
            /* to avoid alert */
            $dummy = $contact;
        }
    })->throws(ConnectionException::class);

    it('test DatabaseConnection : iterateColumn with an empty query', function () use ($dbConfigCentreon): void {
        $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
        $contacts = $db->iterateColumn("", QueryParameters::create([QueryParameter::int('id', 1)]));
        foreach ($contacts as $contact) {
            /* to avoid alert */
            $dummy = $contact;
        }
    })->throws(ConnectionException::class);

    it('test DatabaseConnection : iterateColumn with an incorrect query', function () use ($dbConfigCentreon): void {
        $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
        $contacts = $db->iterateColumn("foo", QueryParameters::create([QueryParameter::int('id', 1)]));
        foreach ($contacts as $contact) {
            /* to avoid alert */
            $dummy = $contact;
        }
    })->throws(ConnectionException::class);

    it(
        'test DatabaseConnection : iterateColumn with an incorrect query parameters',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
            $contacts = $db->iterateColumn(
                "SELECT * FROM contact WHERE contact_id = :id",
                QueryParameters::create([QueryParameter::string('name', 'foo_name')])
            );
            foreach ($contacts as $contact) {
                /* to avoid alert */
                $dummy = $contact;
            }
        }
    )->throws(ConnectionException::class);

    // -- iterateKeyValue()

    it(
        'test DatabaseConnection : iterateKeyValue with a correct query with query parameters',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
            $contact = $db->iterateKeyValue(
                "SELECT contact_id, contact_alias FROM contact WHERE contact_id = :id",
                QueryParameters::create([QueryParameter::int('id', 1)])
            );
            foreach ($contact as $contactId => $contactAlias) {
                expect($contactId)->toBeInt()->toBe(1)
                    ->and($contactAlias)->toBeString()->toBe('admin');
            }
        }
    );

    it('test DatabaseConnection : iterateKeyValue with a CUD query', function () use ($dbConfigCentreon): void {
        $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
        $contacts = $db->iterateKeyValue(
            "DELETE FROM contact WHERE contact_id = :id",
            QueryParameters::create([QueryParameter::int('id', 1)])
        );
        foreach ($contacts as $contact) {
            /* to avoid alert */
            $dummy = $contact;
        }
    })->throws(ConnectionException::class);

    it('test DatabaseConnection : iterateKeyValue with an empty query', function () use ($dbConfigCentreon): void {
        $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
        $contacts = $db->iterateKeyValue("", QueryParameters::create([QueryParameter::int('id', 1)]));
        foreach ($contacts as $contact) {
            /* to avoid alert */
            $dummy = $contact;
        }
    })->throws(ConnectionException::class);

    it('test DatabaseConnection : iterateKeyValue with an incorrect query', function () use ($dbConfigCentreon): void {
        $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
        $contacts = $db->iterateKeyValue("foo", QueryParameters::create([QueryParameter::int('id', 1)]));
        foreach ($contacts as $contact) {
            /* to avoid alert */
            $dummy = $contact;
        }
    })->throws(ConnectionException::class);

    it(
        'test DatabaseConnection : iterateKeyValue with an incorrect query parameters',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
            $contacts = $db->iterateKeyValue(
                "SELECT contact_id, contact_alias FROM contact WHERE contact_id = :id",
                QueryParameters::create([QueryParameter::string('name', 'foo_name')])
            );
            foreach ($contacts as $contact) {
                /* to avoid alert */
                $dummy = $contact;
            }
        }
    )->throws(ConnectionException::class);

    // -- iterateAssociativeIndexed()

    it(
        'test DatabaseConnection : iterateAssociativeIndexed with a correct query with query parameters',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
            $contacts = $db->iterateAssociativeIndexed(
                "SELECT contact_id, contact_name, contact_alias FROM contact WHERE contact_id = :id",
                QueryParameters::create([QueryParameter::int('id', 1)])
            );
            foreach ($contacts as $contactId => $contact) {
                expect($contactId)->toBeInt()->toBe(1)
                    ->and($contact)->toBeArray()->toHaveKey('contact_name', 'admin admin');
            }
        }
    );

    it(
        'test DatabaseConnection : iterateAssociativeIndexed with a CUD query',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
            $contacts = $db->iterateAssociativeIndexed(
                "DELETE FROM contact WHERE contact_id = :id",
                QueryParameters::create([QueryParameter::int('id', 1)])
            );
            foreach ($contacts as $contact) {
                /* to avoid alert */
                $dummy = $contact;
            }
        }
    )->throws(ConnectionException::class);

    it(
        'test DatabaseConnection : iterateAssociativeIndexed with an empty query',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
            $contacts = $db->iterateAssociativeIndexed("", QueryParameters::create([QueryParameter::int('id', 1)]));
            foreach ($contacts as $contact) {
                /* to avoid alert */
                $dummy = $contact;
            }
        }
    )->throws(ConnectionException::class);

    it(
        'test DatabaseConnection : iterateAssociativeIndexed with an incorrect query',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
            $contacts = $db->iterateAssociativeIndexed("foo", QueryParameters::create([QueryParameter::int('id', 1)]));
            foreach ($contacts as $contact) {
                /* to avoid alert */
                $dummy = $contact;
            }
        }
    )->throws(ConnectionException::class);

    it(
        'test DatabaseConnection : iterateAssociativeIndexed with an incorrect query parameters',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
            $contacts = $db->iterateAssociativeIndexed(
                "SELECT contact_id, contact_name, contact_alias FROM contact WHERE contact_id = :id",
                QueryParameters::create([QueryParameter::string('name', 'foo_name')])
            );
            foreach ($contacts as $contact) {
                /* to avoid alert */
                $dummy = $contact;
            }
        }
    )->throws(ConnectionException::class);

    // ----------------------------------- QUERY ON SEVERAL DATABASES -------------------------------------

    it(
        'test DatabaseConnection : execute query on several databases with success',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
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

    it('test DatabaseConnection : execute startTransaction with success', function () use ($dbConfigCentreon): void {
        $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
        $db->startTransaction();
        expect($db->isTransactionActive())->toBeTrue();
    });

    it('test DatabaseConnection : execute commit with success', function () use ($dbConfigCentreon): void {
        $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
        $db->startTransaction();
        $response = $db->commitTransaction();
        expect($response)->toBeTrue()
            ->and($db->isTransactionActive())->toBeFalse();
    });

    it('test DatabaseConnection : execute rollback with success', function () use ($dbConfigCentreon): void {
        $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
        $db->startTransaction();
        $response = $db->rollBackTransaction();
        expect($response)->toBeTrue()
            ->and($db->isTransactionActive())->toBeFalse();
    });

    // ------------------------------------- UNBUFFERED QUERIES -----------------------------------------

    it('test DatabaseConnection : allow unbuffered query with success', function () use ($dbConfigCentreon): void {
        $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
        expect($db->allowUnbufferedQuery())->toBeTrue();
    });

    it(
        'test DatabaseConnection : execute unbufferedQuery with correct query',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
            $db->startUnbufferedQuery();
            expect($db->isUnbufferedQueryActive())->toBeTrue()
                ->and($db->getAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY))->toBe(0);
            $pdoStmt = $db->prepare("SELECT * FROM contact WHERE contact_id = 1");
            $pdoStmt->execute();
            $contact = $pdoStmt->fetch(\PDO::FETCH_ASSOC);
            expect($contact)->toBeArray()->toHaveKey('contact_id', 1)
                ->and($db->isUnbufferedQueryActive())->toBeTrue()
                ->and($db->getAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY))->toBe(0);
            $db->stopUnbufferedQuery();
            expect($db->isUnbufferedQueryActive())->toBeFalse()
                ->and($db->getAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY))->toBe(1);
        }
    );

    it(
        'test DatabaseConnection : stop unbuffered query without start unbuffered query',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);

            $db->stopUnbufferedQuery();
        }
    )->throws(ConnectionException::class);

    // ---------------------------------------- BASE METHOD ----------------------------------------------

    // -- closeQuery

    it(
        'test DatabaseConnection : execute closeQuery with success',
        function () use ($dbConfigCentreon): void {
            $db = DatabaseConnection::createFromConfig(connectionConfig: $dbConfigCentreon);
            $pdoSth = $db->prepare("select * from contact where contact_id = :contact_id");
            $pdoSth->execute(['contact_id' => 1]);
            $successClose = $db->closeQuery($pdoSth);
            expect($successClose)->toBeTrue();
        }
    );

} else {
    it('no centreon database available for testing the DatabaseConnection connector, so these tests were ignored');
}
