<?php

/*
 * Copyright 2005 - 2019 Centreon (https://www.centreon.com/)
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

namespace Centreon\Infrastructure;

use Adaptation\Database\Collection\BatchInsertParameters;
use Adaptation\Database\Collection\QueryParameters;
use Adaptation\Database\ConnectionInterface;
use Adaptation\Database\Enum\QueryParameterTypeEnum;
use Adaptation\Database\Exception\ConnectionException;
use Adaptation\Database\Model\ConnectionConfig;
use Adaptation\Database\ValueObject\QueryParameter;
use Centreon\Domain\Log\Logger;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * This class extend the PDO class and can be used to create a database
 * connection.
 * This class is used by all database repositories.
 *
 * @class   DatabaseConnection
 * @package Centreon\Infrastructure
 */
class DatabaseConnection extends \PDO implements ConnectionInterface
{
    /**
     * @var string Name of the configuration table
     */
    private string $centreonDbName;

    /**
     * @var string Name of the storage table
     */
    private string $storageDbName;

    /**
     * @var ConnectionConfig
     */
    private ConnectionConfig $connectionConfig;

    /**
     * By default, the queries are buffered.
     *
     * @var bool
     */
    private bool $isBufferedQueryActive = true;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * DatabaseConnection constructor.
     *
     * @param LoggerInterface $logger
     * @param string $host
     * @param string $basename
     * @param string $login
     * @param string $password
     * @param int $port
     * @param ConnectionConfig|null $connectionConfig
     *
     * @throws ConnectionException
     */
    public function __construct(
        LoggerInterface $logger,
        string $host = '',
        string $basename = '',
        string $login = '',
        string $password = '',
        int $port = 3306,
        ConnectionConfig $connectionConfig = null
    ) {
        try {
            if (is_null($connectionConfig)) {
                if (empty($host) || empty($login) || empty($password) || empty($basename)) {
                    throw ConnectionException::connectionBadUsage(
                        'Host, login, password and database name must not be empty',
                        [
                            'host' => $host,
                            'login' => $login,
                            'password' => $password,
                            'basename' => $basename,
                        ]
                    );
                }
                $this->connectionConfig = new ConnectionConfig(
                    host: $host,
                    user: $login,
                    password: $password,
                    databaseName: $basename,
                    port: $port
                );
            } else {
                $this->connectionConfig = $connectionConfig;
            }

            parent::__construct(
                $this->connectionConfig->getMysqlDsn(),
                $this->connectionConfig->getUser(),
                $this->connectionConfig->getPassword(),
                [
                    \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
                ]
            );
        } catch (\PDOException $exception) {
            if ($exception->getCode() === 2002) {
                $this->writeDbLog(
                    message: "Unable to connect to database",
                    previous: $exception,
                );

                throw ConnectionException::connectionFailed($exception);
            }
        }
        $this->logger = $logger;
    }

    /**
     * Factory
     *
     * @param ConnectionConfig $connectionConfig
     *
     * @throws ConnectionException
     * @return DatabaseConnection
     */
    public static function createFromConfig(ConnectionConfig $connectionConfig): DatabaseConnection
    {
        try {
            return new self(logger: new Logger(), connectionConfig: $connectionConfig);
        } catch (\Throwable $e) {
            throw ConnectionException::connectionFailed($e);
        }
    }

    /**
     * @return string
     */
    public function getCentreonDbName(): string
    {
        return $this->centreonDbName;
    }

    /**
     * @param string $centreonDbName
     */
    public function setCentreonDbName(string $centreonDbName): void
    {
        $this->centreonDbName = $centreonDbName;
    }

    /**
     * @return string
     */
    public function getStorageDbName(): string
    {
        return $this->storageDbName;
    }

    /**
     * @param string $storageDbName
     */
    public function setStorageDbName(string $storageDbName): void
    {
        $this->storageDbName = $storageDbName;
    }

    /**
     * switch connection to another database
     *
     * @param string $dbName
     *
     * @throws ConnectionException
     */
    public function switchToDb(string $dbName): void
    {
        $this->executeStatement('use ' . $dbName);
    }

    /**
     * Return the database name if it exists.
     *
     * @throws ConnectionException
     * @return string|null
     */
    public function getDatabaseName(): ?string
    {
        try {
            return $this->fetchByColumn('SELECT DATABASE()')[0] ?? null;
        } catch (\Throwable $e) {
            $this->writeDbLog(
                message: "Unable to get database name",
                previous: $e,
            );

            throw ConnectionException::getDatabaseNameFailed();
        }
    }

    /**
     * To get the used native connection by DBAL (PDO, mysqli, ...).
     *
     * @return object
     */
    public function getNativeConnection(): object
    {
        return $this;
    }

    /***
     * Returns the ID of the last inserted row.
     * If the underlying driver does not support identity columns, an exception is thrown.
     *
     * @throws ConnectionException
     * @return string
     */
    public function getLastInsertId(): string
    {
        try {
            return (string) $this->lastInsertId();
        } catch (\Throwable $e) {
            $this->writeDbLog(
                message: "Unable to get last insert id",
                previous: $e,
            );

            throw ConnectionException::getLastInsertFailed($e);
        }
    }

    /**
     * Check if a connection with the database exist.
     *
     * @return bool
     */
    public function isConnected(): bool
    {
        try {
            $this->executeSelectQuery('SELECT 1');

            return true;
        } catch (ConnectionException $e) {
            $this->writeDbLog(
                message: "Unable to execute select query",
                query: 'SELECT 1',
                previous: $e,
            );

            return false;
        }
    }

    /**
     * The usage of this method is discouraged. Use prepared statements.
     *
     * @param string $value
     *
     * @return string
     */
    public function quoteString(string $value): string
    {
        return parent::quote($value);
    }

    // --------------------------------------- CUD METHODS -----------------------------------------

    /**
     * To execute all queries except the queries getting results (SELECT).
     *
     * Executes an SQL statement with the given parameters and returns the number of affected rows.
     *
     * Could be used for:
     *  - DML statements: INSERT, UPDATE, DELETE, etc.
     *  - DDL statements: CREATE, DROP, ALTER, etc.
     *  - DCL statements: GRANT, REVOKE, etc.
     *  - Session control statements: ALTER SESSION, SET, DECLARE, etc.
     *  - Other statements that don't yield a row set.
     *
     * @param string $query
     * @param QueryParameters|null $queryParameters
     *
     * @throws ConnectionException
     * @return int
     *
     * @example $queryParameters = QueryParameters::create([QueryParameter::int('id', 1), QueryParameter::string('name', 'John')]);
     *          $nbAffectedRows = $db->executeStatement('UPDATE table SET name = :name WHERE id = :id', $queryParameters);
     *          // $nbAffectedRows = 1
     */
    public function executeStatement(string $query, ?QueryParameters $queryParameters = null): int
    {
        try {
            if (empty($query)) {
                throw ConnectionException::notEmptyQuery();
            }

            if (str_starts_with($query, 'SELECT') || str_starts_with($query, 'select')) {
                throw ConnectionException::executeStatementBadFormat(
                    "Cannot use it with a SELECT query",
                    $query
                );
            }

            $pdoStatement = $this->prepare($query);

            if (! is_null($queryParameters) && ! $queryParameters->isEmpty()) {
                foreach ($queryParameters->getIterator() as $queryParameter) {
                    $pdoStatement->bindValue(
                        ":{$queryParameter->getName()}",
                        $queryParameter->getValue(),
                        ($queryParameter->getType() !== null) ?
                            $queryParameter->getType()->value : QueryParameterTypeEnum::STRING->value
                    );
                }
            }

            $pdoStatement->execute();

            return $pdoStatement->rowCount();
        } catch (\Throwable $e) {
            $this->writeDbLog(
                message: "Unable to execute statement",
                customContext: ['query_parameters' => $queryParameters],
                query: $query,
                previous: $e,
            );

            throw ConnectionException::executeStatementFailed($e, $query, $queryParameters);
        }
    }

    /**
     * Executes an SQL statement with the given parameters and returns the number of affected rows.
     *
     * Could be only used for INSERT.
     *
     * @param string $query
     * @param QueryParameters|null $queryParameters
     *
     * @throws ConnectionException
     * @return int
     *
     * @example $queryParameters = QueryParameters::create([QueryParameter::int('id', 1), QueryParameter::string('name', 'John')]);
     *          $nbAffectedRows = $db->insert('INSERT INTO table (id, name) VALUES (:id, :name)', $queryParameters);
     *          // $nbAffectedRows = 1
     */
    public function insert(string $query, ?QueryParameters $queryParameters = null): int
    {
        try {
            if (! str_starts_with($query, 'INSERT INTO ')
                && ! str_starts_with($query, 'insert into ')
            ) {
                throw ConnectionException::insertQueryBadFormat($query);
            }

            return $this->executeStatement($query, $queryParameters);
        } catch (\Throwable $e) {
            $this->writeDbLog(
                message: "Unable to execute insert query",
                customContext: ['query_parameters' => $queryParameters],
                query: $query,
                previous: $e,
            );

            throw ConnectionException::insertQueryFailed($e, $query, $queryParameters);
        }
    }

    /**
     * Executes an SQL statement with the given parameters and returns the number of affected rows for multiple inserts.
     *
     * Could be only used for several INSERT.
     *
     * $batchInsertParameters is a collection of QueryParameters, each QueryParameters is a collection of QueryParameter
     *
     * @param string $tableName
     * @param array $columns
     * @param BatchInsertParameters $batchInsertParameters
     *
     * @throws ConnectionException
     * @return int
     *
     * @example $batchInsertParameters = BatchInsertParameters::create([
     *              QueryParameters::create([QueryParameter::int('id', 1), QueryParameter::string('name', 'John')]),
     *              QueryParameters::create([QueryParameter::int('id', 2), QueryParameter::string('name', 'Jean')]),
     *          ]);
     *          $nbAffectedRows = $db->batchInsert('table', ['id', 'name'], $batchInsertParameters);
     *          // $nbAffectedRows = 2
     */
    public function batchInsert(string $tableName, array $columns, BatchInsertParameters $batchInsertParameters): int
    {
        try {
            if (empty($tableName)) {
                throw ConnectionException::batchInsertQueryBadUsage('Table name must not be empty');
            }
            if (empty($columns)) {
                throw ConnectionException::batchInsertQueryBadUsage('Columns must not be empty');
            }
            if ($batchInsertParameters->isEmpty()) {
                throw ConnectionException::batchInsertQueryBadUsage('Batch insert parameters must not be empty');
            }

            $query = "INSERT INTO $tableName (" . implode(', ', $columns) . ") VALUES";

            $valuesInsert = [];
            $queryParametersToInsert = new QueryParameters([]);

            $indexQueryParameterToInsert = 1;

            /*
             * $batchInsertParameters is a collection of QueryParameters, each QueryParameters is a collection of QueryParameter
             * We need to iterate over the QueryParameters to build the final query.
             * Then, for each QueryParameters, we need to iterate over the QueryParameter to build :
             *  - to check if the query parameters are not empty (queryParameters)
             *  - to check if the columns and query parameters have the same length (columns, queryParameters)
             *  - to rename the parameter name to avoid conflicts with a suffix (indexQueryParameterToInsert)
             *  - the values block of the query (valuesInsert)
             *  - the query parameters to insert (queryParametersToInsert)
             */

            foreach ($batchInsertParameters->getIterator() as $queryParameters) {
                if ($queryParameters->isEmpty()) {
                    throw ConnectionException::batchInsertQueryBadUsage('Query parameters must not be empty');
                }
                if (count($columns) !== $queryParameters->length()) {
                    throw ConnectionException::batchInsertQueryBadUsage(
                        'Columns and query parameters must have the same length'
                    );
                }

                $valuesInsertItem = '';

                foreach ($queryParameters->getIterator() as $queryParameter) {
                    if (! empty($valuesInsertItem)) {
                        $valuesInsertItem .= ', ';
                    }
                    $parameterName = "{$queryParameter->getName()}_{$indexQueryParameterToInsert}";
                    $queryParameterToInsert = QueryParameter::create(
                        $parameterName,
                        $queryParameter->getValue(),
                        $queryParameter->getType()
                    );
                    $valuesInsertItem .= ":$parameterName";
                    $queryParametersToInsert->add($queryParameterToInsert->getName(), $queryParameterToInsert);
                }

                $valuesInsert[] = "({$valuesInsertItem})";
                $indexQueryParameterToInsert++;
            }

            if (count($valuesInsert) === $queryParametersToInsert->length()) {
                throw ConnectionException::batchInsertQueryBadUsage(
                    'Error while building the final query : values block and query parameters have not the same length'
                );
            }

            $query .= implode(', ', $valuesInsert);

            return $this->executeStatement($query, $queryParametersToInsert);
        } catch (\Throwable $e) {
            $this->writeDbLog(
                message: "Unable to execute batch insert query",
                customContext: [
                    'table_name' => $tableName,
                    'columns' => $columns,
                    'batch_insert_parameters' => $batchInsertParameters
                ],
                query: $query ?? '',
                previous: $e,
            );

            throw ConnectionException::batchInsertQueryFailed(
                previous: $e,
                tableName: $tableName,
                columns: $columns,
                batchInsertParameters: $batchInsertParameters,
                query: $query ?? ''
            );
        }
    }

    /**
     * Executes an SQL statement with the given parameters and returns the number of affected rows.
     *
     * Could be only used for UPDATE.
     *
     * @param string $query
     * @param QueryParameters|null $queryParameters
     *
     * @throws ConnectionException
     * @return int
     *
     * @example $queryParameters = QueryParameters::create([QueryParameter::int('id', 1), QueryParameter::string('name', 'John')]);
     *          $nbAffectedRows = $db->update('UPDATE table SET name = :name WHERE id = :id', $queryParameters);
     *          // $nbAffectedRows = 1
     */
    public function update(string $query, ?QueryParameters $queryParameters = null): int
    {
        try {
            if (! str_starts_with($query, 'UPDATE ')
                && ! str_starts_with($query, 'update ')
            ) {
                throw ConnectionException::updateQueryBadFormat($query);
            }

            return $this->executeStatement($query, $queryParameters);
        } catch (\Throwable $e) {
            $this->writeDbLog(
                message: "Unable to execute update query",
                customContext: ['query_parameters' => $queryParameters],
                query: $query,
                previous: $e,
            );

            throw ConnectionException::updateQueryFailed($e, $query, $queryParameters);
        }
    }

    /**
     * Executes an SQL statement with the given parameters and returns the number of affected rows.
     *
     * Could be only used for DELETE.
     *
     * @param string $query
     * @param QueryParameters|null $queryParameters
     *
     * @throws ConnectionException
     * @return int
     *
     * @example $queryParameters = QueryParameters::create([QueryParameter::int('id', 1)]);
     *          $nbAffectedRows = $db->delete('DELETE FROM table WHERE id = :id', $queryParameters);
     *          // $nbAffectedRows = 1
     */
    public function delete(string $query, ?QueryParameters $queryParameters = null): int
    {
        try {
            if (! str_starts_with($query, 'DELETE ')
                && ! str_starts_with($query, 'delete ')
            ) {
                throw ConnectionException::deleteQueryBadFormat($query);
            }

            return $this->executeStatement($query, $queryParameters);
        } catch (\Throwable $e) {
            $this->writeDbLog(
                message: "Unable to execute insert query",
                customContext: ['query_parameters' => $queryParameters],
                query: $query,
                previous: $e,
            );

            throw ConnectionException::deleteQueryFailed($e, $query, $queryParameters);
        }
    }

    // --------------------------------------- FETCH METHODS -----------------------------------------

    /**
     * Prepares and executes an SQL query and returns the first row of the result
     * as a numerically indexed array.
     *
     * Could be only used with SELECT.
     *
     * @param string $query
     * @param QueryParameters|null $queryParameters
     *
     * @throws ConnectionException
     * @return array<string, mixed>|false False is returned if no rows are found.
     *
     * @example $queryParameters = QueryParameters::create([QueryParameter::int('id', 1)]);
     *          $result = $db->fetchNumeric('SELECT * FROM table WHERE id = :id', $queryParameters);
     *          // $result = [0 => 1, 1 => 'John', 2 => 'Doe']
     */
    public function fetchNumeric(string $query, ?QueryParameters $queryParameters = null): false|array
    {
        try {
            $this->validateSelectQuery($query);
            $pdoStatement = $this->executeSelectQuery($query, $queryParameters, \PDO::FETCH_NUM);

            return $pdoStatement->fetch();
        } catch (\Throwable $e) {
            $this->writeDbLog(
                message: "Unable to fetch numeric query",
                customContext: ['query_parameters' => $queryParameters],
                query: $query,
                previous: $e,
            );

            throw ConnectionException::fetchNumericQueryFailed($e, $query, $queryParameters);
        }
    }

    /**
     * Prepares and executes an SQL query and returns the first row of the result as an associative array.
     *
     * Could be only used with SELECT.
     *
     * @param string $query
     * @param QueryParameters|null $queryParameters
     *
     * @throws ConnectionException
     * @return array<string, mixed>|false False is returned if no rows are found.
     *
     * @example $queryParameters = QueryParameters::create([QueryParameter::int('id', 1)]);
     *          $result = $db->fetchAssociative('SELECT * FROM table WHERE id = :id', $queryParameters);
     *          // $result = ['id' => 1, 'name' => 'John', 'surname' => 'Doe']
     */
    public function fetchAssociative(string $query, ?QueryParameters $queryParameters = null): false|array
    {
        try {
            $this->validateSelectQuery($query);
            $pdoStatement = $this->executeSelectQuery($query, $queryParameters, \PDO::FETCH_ASSOC);

            return $pdoStatement->fetch();
        } catch (\Throwable $e) {
            $this->writeDbLog(
                message: "Unable to fetch associative query",
                customContext: ['query_parameters' => $queryParameters],
                query: $query,
                previous: $e,
            );

            throw ConnectionException::fetchAssociativeQueryFailed($e, $query, $queryParameters);
        }
    }

    /**
     * Prepares and executes an SQL query and returns the value of a single column
     * of the first row of the result.
     *
     * Could be only used with SELECT.
     *
     * @param string $query
     * @param QueryParameters|null $queryParameters
     *
     * @throws ConnectionException
     * @return mixed|false False is returned if no rows are found.
     *
     * @example $queryParameters = QueryParameters::create([QueryParameter::string('name', 'John')]);
     *          $result = $db->fetchOne('SELECT name FROM table WHERE name = :name', $queryParameters);
     *          // $result = 'John'
     */
    public function fetchOne(string $query, ?QueryParameters $queryParameters = null): mixed
    {
        try {
            $this->validateSelectQuery($query);
            $pdoStatement = $this->executeSelectQuery($query, $queryParameters, \PDO::FETCH_COLUMN);

            return $pdoStatement->fetch()[0] ?? false;
        } catch (\Throwable $e) {
            $this->writeDbLog(
                message: "Unable to fetch one query",
                customContext: ['query_parameters' => $queryParameters],
                query: $query,
                previous: $e,
            );

            throw ConnectionException::fetchOneQueryFailed($e, $query, $queryParameters);
        }
    }

    /**
     * Prepares and executes an SQL query and returns the result as an array of the column values.
     *
     * Could be only used with SELECT.
     *
     * @param string $query
     * @param QueryParameters|null $queryParameters
     * @param int $column
     *
     * @throws ConnectionException
     * @return list<mixed>
     *
     * @example $queryParameters = QueryParameters::create([QueryParameter::bool('active', true)]);
     *          $result = $db->fetchByColumn('SELECT * FROM table WHERE active = :active', $queryParameters);
     *          // $result = ['John', 'Jean']
     */
    public function fetchByColumn(string $query, ?QueryParameters $queryParameters = null, int $column = 0): array
    {
        try {
            $this->validateSelectQuery($query);
            $pdoStatement = $this->executeSelectQuery($query, $queryParameters, \PDO::FETCH_COLUMN, [$column]);

            return $pdoStatement->fetchAll();
        } catch (\Throwable $e) {
            $this->writeDbLog(
                message: "Unable to fetch by column query",
                customContext: ['query_parameters' => $queryParameters, 'column' => $column],
                query: $query,
                previous: $e,
            );

            throw ConnectionException::fetchByColumnQueryFailed($e, $query, $column, $queryParameters);
        }
    }

    /**
     * Prepares and executes an SQL query and returns the result as an array of numeric arrays.
     *
     * Could be only used with SELECT.
     *
     * @param string $query
     * @param QueryParameters|null $queryParameters
     *
     * @throws ConnectionException
     * @return array<array<int,mixed>>
     *
     * @example $queryParameters = QueryParameters::create([QueryParameter::bool('active', true)]);
     *          $result = $db->fetchAllNumeric('SELECT * FROM table WHERE active = :active', $queryParameters);
     *          // $result = [[0 => 1, 1 => 'John', 2 => 'Doe'], [0 => 2, 1 => 'Jean', 2 => 'Dupont']]
     */
    public function fetchAllNumeric(string $query, ?QueryParameters $queryParameters = null): array
    {
        try {
            $this->validateSelectQuery($query);
            $pdoStatement = $this->executeSelectQuery($query, $queryParameters, \PDO::FETCH_NUM);

            return $pdoStatement->fetchAll();
        } catch (\Throwable $e) {
            $this->writeDbLog(
                message: "Unable to fetch all numeric query",
                customContext: ['query_parameters' => $queryParameters],
                query: $query,
                previous: $e,
            );

            throw ConnectionException::fetchAllNumericQueryFailed($e, $query, $queryParameters);
        }
    }

    /**
     * Prepares and executes an SQL query and returns the result as an array of associative arrays.
     *
     * Could be only used with SELECT.
     *
     * @param string $query
     * @param QueryParameters|null $queryParameters
     *
     * @throws ConnectionException
     * @return array<array<string,mixed>>
     *
     * @example $queryParameters = QueryParameters::create([QueryParameter::bool('active', true)]);
     *          $result = $db->fetchAllAssociative('SELECT * FROM table WHERE active = :active', $queryParameters);
     *          // $result = [['id' => 1, 'name' => 'John', 'surname' => 'Doe'], ['id' => 2, 'name' => 'Jean', 'surname' => 'Dupont']]
     */
    public function fetchAllAssociative(string $query, ?QueryParameters $queryParameters = null): array
    {
        try {
            $this->validateSelectQuery($query);
            $pdoStatement = $this->executeSelectQuery($query, $queryParameters, \PDO::FETCH_ASSOC);

            return $pdoStatement->fetchAll();
        } catch (\Throwable $e) {
            $this->writeDbLog(
                message: "Unable to fetch all associative query",
                customContext: ['query_parameters' => $queryParameters],
                query: $query,
                previous: $e,
            );

            throw ConnectionException::fetchAllAssociativeQueryFailed($e, $query, $queryParameters);
        }
    }

    /**
     * Prepares and executes an SQL query and returns the result as an associative array with the keys
     * mapped to the first column and the values mapped to the second column.
     *
     * Could be only used with SELECT.
     *
     * @param string $query
     * @param QueryParameters|null $queryParameters
     *
     * @throws ConnectionException
     * @return array<int|string,mixed>
     *
     * @example $queryParameters = QueryParameters::create([QueryParameter::bool('active', true)]);
     *          $result = $db->fetchAllKeyValue('SELECT name, surname FROM table WHERE active = :active', $queryParameters);
     *          // $result = ['John' => 'Doe', 'Jean' => 'Dupont']
     */
    public function fetchAllKeyValue(string $query, ?QueryParameters $queryParameters = null): array
    {
        try {
            $this->validateSelectQuery($query);
            $pdoStatement = $this->executeSelectQuery($query, $queryParameters, \PDO::FETCH_KEY_PAIR);

            return $pdoStatement->fetchAll();
        } catch (\Throwable $e) {
            $this->writeDbLog(
                message: "Unable to fetch all key value query",
                customContext: ['query_parameters' => $queryParameters],
                query: $query,
                previous: $e,
            );

            throw ConnectionException::fetchAllKeyValueQueryFailed($e, $query, $queryParameters);
        }
    }

    /**
     * Prepares and executes an SQL query and returns the result as an associative array with the keys mapped
     * to the first column and the values being an associative array representing the rest of the columns
     * and their values.
     *
     * Could be only used with SELECT.
     *
     * @param string $query
     * @param QueryParameters|null $queryParameters
     *
     * @throws ConnectionException
     * @return array<mixed,array<string,mixed>>
     *
     * @example $queryParameters = QueryParameters::create([QueryParameter::bool('active', true)]);
     *          $result = $db->fetchAllAssociativeIndexed('SELECT id, name, surname FROM table WHERE active = :active', $queryParameters);
     *          // $result = [1 => ['name' => 'John', 'surname' => 'Doe'], 2 => ['name' => 'Jean', 'surname' => 'Dupont']]
     */
    public function fetchAllAssociativeIndexed(string $query, ?QueryParameters $queryParameters = null): array
    {
        try {
            $this->validateSelectQuery($query);
            $data = [];
            foreach ($this->fetchAllAssociative($query, $queryParameters) as $row) {
                $data[array_shift($row)] = $row;
            }

            return $data;
        } catch (\Throwable $e) {
            $this->writeDbLog(
                message: "Unable to fetch all associative indexed query",
                customContext: ['query_parameters' => $queryParameters],
                query: $query,
                previous: $e,
            );

            throw ConnectionException::fetchAllAssociativeIndexedQueryFailed($e, $query, $queryParameters);
        }
    }

    // --------------------------------------- ITERATE METHODS -----------------------------------------

    /**
     * Prepares and executes an SQL query and returns the result as an iterator over rows represented as numeric arrays.
     *
     * Could be only used with SELECT.
     *
     * @param string $query
     * @param QueryParameters|null $queryParameters
     *
     * @throws ConnectionException
     * @return \Traversable<int,list<mixed>>
     *
     * @example $queryParameters = QueryParameters::create([QueryParameter::bool('active', true)]);
     *          $result = $db->iterateNumeric('SELECT * FROM table WHERE active = :active', $queryParameters);
     *          foreach ($result as $row) {
     *              // $row = [0 => 1, 1 => 'John', 2 => 'Doe']
     *              // $row = [0 => 2, 1 => 'Jean', 2 => 'Dupont']
     *          }
     */
    public function iterateNumeric(string $query, ?QueryParameters $queryParameters = null): \Traversable
    {
        try {
            $this->validateSelectQuery($query);
            $pdoStatement = $this->executeSelectQuery($query, $queryParameters, \PDO::FETCH_NUM);
            while (($row = $pdoStatement->fetch()) !== false) {
                yield $row;
            }
        } catch (\Throwable $e) {
            $this->writeDbLog(
                message: "Unable to iterate numeric query",
                customContext: ['query_parameters' => $queryParameters],
                query: $query,
                previous: $e,
            );

            throw ConnectionException::iterateNumericQueryFailed($e, $query, $queryParameters);
        }
    }

    /**
     * Prepares and executes an SQL query and returns the result as an iterator over rows represented
     * as associative arrays.
     *
     * Could be only used with SELECT.
     *
     * @param string $query
     * @param QueryParameters|null $queryParameters
     *
     * @throws ConnectionException
     * @return \Traversable<int,array<string,mixed>>
     *
     * @example $queryParameters = QueryParameters::create([QueryParameter::bool('active', true)]);
     *          $result = $db->iterateAssociative('SELECT * FROM table WHERE active = :active', $queryParameters);
     *          foreach ($result as $row) {
     *              // $row = ['id' => 1, 'name' => 'John', 'surname' => 'Doe']
     *              // $row = ['id' => 2, 'name' => 'Jean', 'surname' => 'Dupont']
     *          }
     */
    public function iterateAssociative(string $query, ?QueryParameters $queryParameters = null): \Traversable
    {
        try {
            $this->validateSelectQuery($query);
            $pdoStatement = $this->executeSelectQuery($query, $queryParameters, \PDO::FETCH_ASSOC);
            while (($row = $pdoStatement->fetch()) !== false) {
                yield $row;
            }
        } catch (\Throwable $e) {
            $this->writeDbLog(
                message: "Unable to iterate associative query",
                customContext: ['query_parameters' => $queryParameters],
                query: $query,
                previous: $e,
            );

            throw ConnectionException::iterateAssociativeQueryFailed($e, $query, $queryParameters);
        }
    }

    /**
     * Prepares and executes an SQL query and returns the result as an iterator over the column values.
     *
     * Could be only used with SELECT.
     *
     * @param string $query
     * @param QueryParameters|null $queryParameters
     * @param int $column
     *
     * @throws ConnectionException
     * @return \Traversable<int,list<mixed>>
     *
     * @example $queryParameters = QueryParameters::create([QueryParameter::bool('active', true)]);
     *          $result = $db->iterateByColumn('SELECT name FROM table WHERE active = :active', $queryParameters);
     *          foreach ($result as $value) {
     *              // $value = 'John'
     *              // $value = 'Jean'
     *          }
     */
    public function iterateByColumn(
        string $query,
        ?QueryParameters $queryParameters = null,
        int $column = 0
    ): \Traversable {
        try {
            $this->validateSelectQuery($query);
            $pdoStatement = $this->executeSelectQuery($query, $queryParameters, \PDO::FETCH_COLUMN, [$column]);
            while (($row = $pdoStatement->fetch()) !== false) {
                yield $row;
            }
        } catch (\Throwable $e) {
            $this->writeDbLog(
                message: "Unable to iterate by column query",
                customContext: ['query_parameters' => $queryParameters, 'column' => $column],
                query: $query,
                previous: $e,
            );

            throw ConnectionException::iterateByColumnQueryFailed($e, $query, $column, $queryParameters);
        }
    }

    /**
     * Prepares and executes an SQL query and returns the result as an iterator with the keys
     * mapped to the first column and the values mapped to the second column.
     *
     * Could be only used with SELECT.
     *
     * @param string $query
     * @param QueryParameters|null $queryParameters
     *
     * @throws ConnectionException
     * @return \Traversable<mixed,mixed>
     *
     * @example $queryParameters = QueryParameters::create([QueryParameter::bool('active', true)]);
     *          $result = $db->iterateKeyValue('SELECT name, surname FROM table WHERE active = :active', $queryParameters);
     *          foreach ($result as $key => $value) {
     *              // $key = 'John', $value = 'Doe'
     *              // $key = 'Jean', $value = 'Dupont'
     *          }
     */
    public function iterateKeyValue(string $query, ?QueryParameters $queryParameters = null): \Traversable
    {
        try {
            $this->validateSelectQuery($query);
            $pdoStatement = $this->executeSelectQuery($query, $queryParameters, \PDO::FETCH_KEY_PAIR);
            while (($row = $pdoStatement->fetch()) !== false) {
                yield $row;
            }
        } catch (\Throwable $e) {
            $this->writeDbLog(
                message: "Unable to iterate key value query",
                customContext: ['query_parameters' => $queryParameters],
                query: $query,
                previous: $e,
            );

            throw ConnectionException::iterateKeyValueQueryFailed($e, $query, $queryParameters);
        }
    }

    /**
     * Prepares and executes an SQL query and returns the result as an iterator with the keys mapped
     * to the first column and the values being an associative array representing the rest of the columns
     * and their values.
     *
     * Could be only used with SELECT.
     *
     * @param string $query
     * @param QueryParameters|null $queryParameters
     *
     * @throws ConnectionException
     * @return \Traversable<mixed,array<string,mixed>>
     *
     * @example $queryParameters = QueryParameters::create([QueryParameter::bool('active', true)]);
     *          $result = $db->iterateAssociativeIndexed('SELECT id, name, surname FROM table WHERE active = :active', $queryParameters);
     *          foreach ($result as $key => $row) {
     *              // $key = 1, $row = ['name' => 'John', 'surname' => 'Doe']
     *              // $key = 2, $row = ['name' => 'Jean', 'surname' => 'Dupont']
     *          }
     */
    public function iterateAssociativeIndexed(string $query, ?QueryParameters $queryParameters = null): \Traversable
    {
        try {
            $this->validateSelectQuery($query);
            foreach ($this->iterateAssociative($query, $queryParameters) as $row) {
                yield array_shift($row) => $row;
            }
        } catch (\Throwable $e) {
            $this->writeDbLog(
                message: "Unable to iterate associative indexed query",
                customContext: ['query_parameters' => $queryParameters],
                query: $query,
                previous: $e,
            );

            throw ConnectionException::iterateAssociativeIndexedQueryFailed($e, $query, $queryParameters);
        }
    }

    // ----------------------------------------- TRANSACTIONS -----------------------------------------

    /**
     * Checks whether a transaction is currently active.
     *
     * @return bool TRUE if a transaction is currently active, FALSE otherwise.
     */
    public function isTransactionActive(): bool
    {
        return parent::inTransaction();
    }

    /**
     * Opens a new transaction. This must be closed by calling one of the following methods:
     * {@see commitTransaction} or {@see rollBackTransaction}
     *
     * @throws ConnectionException
     * @return void
     *
     */
    public function startTransaction(): void
    {
        try {
            $this->beginTransaction();
        } catch (\Throwable $e) {
            $this->writeDbLog(
                message: "Unable to start transaction",
                previous: $e,
            );

            throw ConnectionException::startTransactionFailed($e);
        }
    }

    /**
     * To validate a transaction.
     *
     * @throws ConnectionException
     * @return bool
     */
    public function commitTransaction(): bool
    {
        try {
            if (! parent::commit()) {
                throw ConnectionException::commitTransactionFailed();
            }

            return true;
        } catch (\Throwable $e) {
            $this->writeDbLog(
                message: "Unable to commit transaction",
                previous: $e,
            );

            throw ConnectionException::commitTransactionFailed($e);
        }
    }

    /**
     * To cancel a transaction.
     *
     * @throws ConnectionException
     * @return bool
     */
    public function rollBackTransaction(): bool
    {
        try {
            if (! parent::rollBack()) {
                throw ConnectionException::rollbackTransactionFailed();
            }

            return true;
        } catch (\Throwable $e) {
            $this->writeDbLog(
                message: "Unable to rollback transaction",
                previous: $e,
            );

            throw ConnectionException::rollbackTransactionFailed($e);
        }
    }

    // ------------------------------------- UNBUFFERED QUERIES -----------------------------------------

    /**
     * Checks that the connection instance allows the use of unbuffered queries.
     *
     * @throws ConnectionException
     */
    public function allowUnbufferedQuery(): bool
    {
        $currentDriverName = $this->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if (! in_array($currentDriverName, self::DRIVER_ALLOWED_UNBUFFERED_QUERY)) {
            $this->writeDbLog(
                message: "Unbuffered queries are not allowed with this driver",
                customContext: ['driver_name' => $currentDriverName]
            );

            throw ConnectionException::allowUnbufferedQueryFailed(parent::class, $currentDriverName);
        }

        return true;
    }

    /**
     * Prepares a statement to execute a query without buffering. Only works for SELECT queries.
     *
     * @throws ConnectionException
     * @return void
     */
    public function startUnbufferedQuery(): void
    {
        $this->allowUnbufferedQuery();
        if (! $this->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false)) {
            $this->writeDbLog(message: "Error while starting an unbuffered query");

            throw ConnectionException::startUnbufferedQueryFailed();
        }
        $this->isBufferedQueryActive = false;
    }

    /**
     * Checks whether an unbuffered query is currently active.
     *
     * @return bool
     */
    public function isUnbufferedQueryActive(): bool
    {
        return $this->isBufferedQueryActive === false;
    }

    /**
     * To close an unbuffered query.
     *
     * @throws ConnectionException
     * @return void
     *
     */
    public function stopUnbufferedQuery(): void
    {
        if (! $this->isUnbufferedQueryActive()) {
            $this->writeDbLog(
                message: "Error while stopping an unbuffered query, no unbuffered query is currently active"
            );

            throw ConnectionException::stopUnbufferedQueryFailed(
                "Error while stopping an unbuffered query, no unbuffered query is currently active"
            );
        }
        if (! $this->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true)) {
            $this->writeDbLog(message: "Error while stopping an unbuffered query");

            throw ConnectionException::stopUnbufferedQueryFailed("Error while stopping an unbuffered query");
        }
        $this->isBufferedQueryActive = true;
    }

    // --------------------------------------- BASE METHODS -----------------------------------------

    /**
     * @param \PDOStatement $pdoStatement
     *
     * @throws ConnectionException
     * @return bool
     */
    public function closeQuery(\PDOStatement $pdoStatement): bool
    {
        try {
            return $pdoStatement->closeCursor();
        } catch (\Throwable $e) {
            $this->writeDbLog(
                message: "Error while closing the \PDOStatement cursor: {$e->getMessage()}",
                query: $pdoStatement->queryString,
                previous: $e,
            );

            throw ConnectionException::closeQueryFailed($e, $pdoStatement->queryString);
        }
    }

    // --------------------------------------- PRIVATE METHODS -----------------------------------------

    /**
     * To execute all queries starting with SELECT.
     *
     * Only for SELECT queries.
     *
     * @param string $query
     * @param QueryParameters|null $queryParameters
     * @param int $fetchMode
     * @param array $fetchModeArgs
     *
     * @throws ConnectionException
     * @return \PDOStatement|false
     */
    private function executeSelectQuery(
        string $query,
        ?QueryParameters $queryParameters = null,
        int $fetchMode = \PDO::FETCH_ASSOC,
        array $fetchModeArgs = []
    ): \PDOStatement|false {
        try {
            $this->validateSelectQuery($query);
            $pdoStatement = $this->prepare($query);

            if (! is_null($queryParameters) && ! $queryParameters->isEmpty()) {
                foreach ($queryParameters->getIterator() as $queryParameter) {
                    $pdoStatement->bindValue(
                        $queryParameter->getName(),
                        $queryParameter->getValue(),
                        ($queryParameter->getType() !== null) ?
                            $queryParameter->getType()->value : QueryParameterTypeEnum::STRING->value
                    );
                }
            }

            $pdoStatement->execute();
            $pdoStatement->setFetchMode($fetchMode, ...$fetchModeArgs);

            return $pdoStatement;
        } catch (\Throwable $e) {
            $this->writeDbLog(
                message: "Error while executing the select query",
                customContext: ['query_parameters' => $queryParameters],
                query: $query,
                previous: $e,
            );

            throw ConnectionException::selectQueryFailed(
                previous: $e,
                query: $query,
                queryParameters: $queryParameters,
                context: ['fetch_mode' => $fetchMode, 'fetch_mode_args' => $fetchModeArgs]
            );
        }
    }

    /**
     * @param string $query
     *
     * @throws ConnectionException
     * @return void
     */
    private function validateSelectQuery(string $query): void
    {
        if (empty($query)) {
            throw ConnectionException::notEmptyQuery();
        }
        if (! str_starts_with($query, 'SELECT') && ! str_starts_with($query, 'select')) {
            throw ConnectionException::selectQueryBadFormat($query);
        }
    }

    /**
     * Write SQL errors messages
     *
     * @param string $message
     * @param array $customContext
     * @param string $query
     * @param \Throwable|null $previous
     */
    private function writeDbLog(
        string $message,
        array $customContext = [],
        string $query = '',
        ?\Throwable $previous = null
    ): void {
        // prepare context of the database exception
        if ($previous instanceof ConnectionException) {
            $dbExceptionContext = $previous->getContext();
        } elseif ($previous instanceof \PDOException) {
            $dbExceptionContext = [
                'exception_type' => \PDOException::class,
                'file' => $previous->getFile(),
                'line' => $previous->getLine(),
                'code' => $previous->getCode(),
                'message' => $previous->getMessage(),
                'pdo_error_info' => $previous->errorInfo
            ];
        } else {
            $dbExceptionContext = [];
        }
        if (isset($dbExceptionContext['query'])) {
            unset($dbExceptionContext['query']);
        }

        // prepare default context
        $defaultContext = ['database_name' => $this->connectionConfig->getDatabaseName()];
        if (! empty($query)) {
            $defaultContext['query'] = $query;
        }

        $context = array_merge(
            ["default" => $defaultContext],
            ["custom" => $customContext],
            ['exception' => $dbExceptionContext]
        );

        $this->logger->critical(
            "[DatabaseConnection] $message",
            $context
        );
    }

}
