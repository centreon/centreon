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

namespace Adaptation\Database\Adapter\Dbal;

use Adaptation\Database\ExpressionBuilderInterface;
use Adaptation\Database\QueryBuilderInterface;
use Doctrine\DBAL\Connection as DoctrineDbalConnection;
use Doctrine\DBAL\DriverManager as DoctrineDbalDriverManager;
use Doctrine\DBAL\Query\Expression\ExpressionBuilder as DoctrineDbalExpressionBuilder;
use Doctrine\DBAL\Query\QueryBuilder as DoctrineDbalQueryBuilder;
use PDO;
use Adaptation\Database\ConnectionInterface;
use Adaptation\Database\Enum\ParameterType;
use Adaptation\Database\Exception\ConnectionException;
use Adaptation\Database\Model\ConnectionConfig;
use Throwable;
use Traversable;
use UnexpectedValueException;

/**
 * Class
 *
 * @class   DbalConnectionAdapter
 * @package Adaptation\Database\Adapter\Dbal
 * @see     DoctrineDbalConnection
 */
class DbalConnectionAdapter implements ConnectionInterface
{
    /**
     * By default, the queries are buffered.
     *
     * @var bool
     */
    private bool $isBufferedQueryActive = true;

    /**
     * DbalConnectionAdapter constructor
     *
     * @param DoctrineDbalConnection $dbalConnection
     */
    public function __construct(
        private readonly DoctrineDbalConnection $dbalConnection
    ) {}

    /**
     * Factory
     *
     * @param ConnectionConfig $connectionConfig
     *
     * @return DbalConnectionAdapter
     *
     * @throws ConnectionException
     */
    public static function createFromConfig(ConnectionConfig $connectionConfig): ConnectionInterface
    {
        $dbalConnectionConfig = [
            'dbname' => $connectionConfig->getDatabaseName(),
            'user' => $connectionConfig->getUser(),
            'password' => $connectionConfig->getPassword(),
            'host' => $connectionConfig->getHost(),
            'driver' => $connectionConfig->getDriver()->value,
        ];
        if ($connectionConfig->getCharset() !== '') {
            $dbalConnectionConfig['charset'] = $connectionConfig->getCharset();
        }
        if ($connectionConfig->getPort() > 0) {
            $dbalConnectionConfig['port'] = $connectionConfig->getPort();
        }
        try {
            $dbalConnection = DoctrineDbalDriverManager::getConnection($dbalConnectionConfig);
            $dbalConnectionAdapter = new self($dbalConnection);
            if (! $dbalConnectionAdapter->isConnected()) {
                throw new UnexpectedValueException('The connection is not established.');
            }

            return $dbalConnectionAdapter;
        } catch (Throwable $e) {
            throw ConnectionException::connectionFailed($e);
        }
    }

    /**
     * Creates a new instance of a SQL query builder.
     *
     * @return DbalQueryBuilderAdapter
     */
    public function createQueryBuilder(): QueryBuilderInterface
    {
        $dbalQueryBuilder = new DoctrineDbalQueryBuilder($this->dbalConnection);

        return new DbalQueryBuilderAdapter($dbalQueryBuilder);
    }

    /**
     * Creates an expression builder for the connection.
     *
     * @return DbalExpressionBuilderAdapter
     */
    public function createExpressionBuilder(): ExpressionBuilderInterface
    {
        $dbalExpressionBuilder = new DoctrineDbalExpressionBuilder($this->dbalConnection);

        return new DbalExpressionBuilderAdapter($dbalExpressionBuilder);
    }

    /**
     * Return the database name if it exists.
     *
     * @return string|null
     *
     * @throws ConnectionException
     */
    public function getDatabaseName(): ?string
    {
        try {
            return $this->dbalConnection->getDatabase();
        } catch (Throwable $e) {
            throw ConnectionException::getDatabaseFailed($e);
        }
    }

    /**
     * @return DoctrineDbalConnection
     */
    public function getDbalConnection(): DoctrineDbalConnection
    {
        return $this->dbalConnection;
    }

    /**
     * To get the used native connection by DBAL (PDO, mysqli, ...).
     *
     * @return object
     *
     * @throws ConnectionException
     */
    public function getNativeConnection(): object
    {
        try {
            return $this->dbalConnection->getNativeConnection();
        } catch (Throwable $e) {
            throw ConnectionException::getNativeConnectionFailed($e);
        }
    }

    /***
     * Returns the ID of the last inserted row.
     * If the underlying driver does not support identity columns, an exception is thrown.
     *
     * @return string
     *
     * @throws ConnectionException
     */
    public function getLastInsertId(): string
    {
        try {
            return (string) $this->dbalConnection->lastInsertId();
        } catch (Throwable $e) {
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
            return ! empty($this->dbalConnection->getServerVersion());
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Closes the connection.
     */
    public function close(): void
    {
        $this->dbalConnection->close();
    }

    /**
     * The usage of this method is discouraged. Use prepared statements.
     *
     * @param string $value
     *
     * @return string
     */
    public function quote(string $value): string
    {
        return $this->dbalConnection->quote($value);
    }

    // ----------------------------------------- CRUD METHODS -----------------------------------------

    /**
     * To execute all queries except the queries getting results.
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
     * This method supports PDO binding types as well as DBAL mapping types.
     *
     * @param string                   $query
     * @param array<string,int|string> $params
     * @param array<string,int|string> $types
     *
     * @return int
     *
     * @throws ConnectionException
     *
     * @example bind with named parameters
     *          $sqlInsertQuery : 'INSERT INTO table (field1, field2) VALUES (:value1,:value2)
     *          $params : ['value1'=>'foo','value2'=>1]
     *          $types : [] ou ['value1'=>ParameterType::STRING, 'value2'=>ParameterType::INT]
     * @example bind with ?
     *          $sqlInsertQuery : 'INSERT INTO table (field1, field2) VALUES (?,?)
     *          $params : ['foo', 1]
     *          $types : [] ou [ParameterType::STRING, ParameterType::INT]
     */
    public function executeStatement(string $query, array $params = [], array $types = []): int
    {
        try {
            return (int) $this->dbalConnection->executeStatement($query, $params, $types);
        } catch (Throwable $e) {
            throw ConnectionException::executeQueryFailed($e, $query, $params, $types);
        }
    }

    /**
     * Executes an SQL statement with the given parameters and returns the number of affected rows.
     *
     * Could be only used for INSERT.
     *
     * This method supports PDO binding types as well as DBAL mapping types.
     *
     * @param string                   $query
     * @param array<string,int|string> $params
     * @param array<string,int|string> $types {@see ParameterType}
     *
     * @return int
     *
     * @throws ConnectionException
     *
     * @example bind with named parameters
     *         $sqlInsertQuery : 'INSERT INTO table (field1, field2) VALUES (:value1,:value2)
     *         $params : ['value1'=>'foo','value2'=>1]
     *         $types : [] ou ['value1'=>ParameterType::STRING, 'value2'=>ParameterType::INT]
     * @example bind with ?
     *         $sqlInsertQuery : 'INSERT INTO table (field1, field2) VALUES (?,?)
     *         $params : ['foo', 1]
     *         $types : [] ou [ParameterType::STRING, ParameterType::INT]
     */
    public function insert(string $query, array $params = [], array $types = []): int
    {
        if (! str_starts_with($query, 'INSERT INTO ')) {
            throw ConnectionException::insertQueryBadFormat($query);
        }

        try {
            return (int) $this->dbalConnection->executeStatement($query, $params, $types);
        } catch (Throwable $e) {
            throw ConnectionException::executeQueryFailed($e, $query, $params, $types);
        }
    }

    /**
     * Executes an SQL statement with the given parameters and returns the number of affected rows.
     *
     * Could be only used for UPDATE.
     *
     * This method supports PDO binding types as well as DBAL mapping types.
     *
     * @param string                   $query
     * @param array<string,int|string> $params
     * @param array<string,int|string> $types {@see ParameterType}
     *
     * @return int
     *
     * @throws ConnectionException
     *
     * @example bind with named parameters
     *         $sqlUpdateQuery : 'UPDATE table SET field1=:field1
     *         $params : ['field1'=>'1']
     *         $types :  ['field1'=>ParameterType::INT]
     * @example bind with ?
     *         $sqlUpdateQuery : 'UPDATE table SET field1=?
     *         $params : ['1']
     *         $types :  [ParameterType::INT]
     */
    public function update(string $query, array $params = [], array $types = []): int
    {
        if (! str_starts_with($query, 'UPDATE ')) {
            throw ConnectionException::updateQueryBadFormat($query);
        }

        try {
            return (int) $this->dbalConnection->executeStatement($query, $params, $types);
        } catch (Throwable $e) {
            throw ConnectionException::executeQueryFailed($e, $query, $params, $types);
        }
    }

    /**
     * Executes an SQL statement with the given parameters and returns the number of affected rows.
     *
     * Could be only used for DELETE.
     *
     * This method supports PDO binding types as well as DBAL mapping types.
     *
     * @param string                   $query
     * @param array<string,int|string> $params
     * @param array<string,int|string> $types {@see ParameterType}
     *
     * @return int
     *
     * @throws ConnectionException
     *
     * @example bind with named parameters
     *         $sqlDeleteQuery : 'DELETE table WHERE field1=:field1
     *         $params : ['field1'=>'1']
     *         $types :  ['field1'=>ParameterType::INT]
     * @example bind with ?
     *         $sqlDeleteQuery : 'DELETE table WHERE field1=?
     *         $params : ['1']
     *         $types :  [ParameterType::INT]
     */
    public function delete(string $query, array $params = [], array $types = []): int
    {
        if (! str_starts_with($query, 'DELETE ')) {
            throw ConnectionException::deleteQueryBadFormat($query);
        }

        try {
            return (int) $this->dbalConnection->executeStatement($query, $params, $types);
        } catch (Throwable $e) {
            throw ConnectionException::executeQueryFailed($e, $query, $params, $types);
        }
    }

    // --------------------------------------- FETCH METHODS -----------------------------------------

    /**
     * Prepares and executes an SQL query and returns the first row of the result as an associative array.
     *
     * Could be only used with SELECT.
     *
     * This method supports PDO binding types as well as DBAL mapping types.
     *
     * @param string                   $query
     * @param array<string,int|string> $params
     * @param array<string,int|string> $types {@see ParameterType}.
     *
     * @return array<string, mixed>|false False is returned if no rows are found.
     *
     * @throws ConnectionException
     *
     * @example to bind with named parameters
     *         $selectQuery : 'SELECT * FROM table WHERE field1=:name
     *         $params : ['field1'=>'foo']
     *         $types :  ['field1'=>ParameterType::STRING]
     *
     * @example to bind with ?
     *         $selectQuery : 'SELECT * FROM table WHERE field1=?
     *         $params : ['foo']
     *         $types :  [ParameterType::STRING]
     */
    public function fetchAssociative(string $query, array $params = [], array $types = []): false | array
    {
        try {
            return $this->dbalConnection->fetchAssociative($query, $params, $types);
        } catch (Throwable $e) {
            throw ConnectionException::executeQueryFailed($e, $query, $params, $types);
        }
    }

    /**
     * Prepares and executes an SQL query and returns the first row of the result
     * as a numerically indexed array.
     *
     * Could be only used with SELECT.
     *
     * This method supports PDO binding types as well as DBAL mapping types.
     *
     * @param string                   $query
     * @param array<string,int|string> $params
     * @param array<string,int|string> $types {@see ParameterType}.
     *
     * @return array<string, mixed>|false False is returned if no rows are found.
     *
     * @throws ConnectionException
     *
     * @example to bind with named parameters
     *         $selectQuery : 'SELECT * FROM table WHERE field1=:name
     *         $params : ['field1' => 'foo']
     *         $types :  ['field1' => ParameterType::STRING]
     *
     * @example to bind with ?
     *         $selectQuery : 'SELECT * FROM table WHERE field1=?
     *         $params : ['foo']
     *         $types :  [ParameterType::STRING]
     */
    public function fetchNumeric(string $query, array $params = [], array $types = []): false | array
    {
        try {
            return $this->dbalConnection->fetchNumeric($query, $params, $types);
        } catch (Throwable $e) {
            throw ConnectionException::executeQueryFailed($e, $query, $params, $types);
        }
    }

    /**
     * Prepares and executes an SQL query and returns the value of a single column
     * of the first row of the result.
     *
     * Could be only used with SELECT.
     *
     * This method supports PDO binding types as well as DBAL mapping types.
     *
     * @param string                   $query
     * @param array<string,int|string> $params
     * @param array<string,int|string> $types {@see ParameterType}.
     *
     * @return mixed|false False is returned if no rows are found.
     *
     * @throws ConnectionException
     *
     * @example to bind with named parameters
     *         $selectQuery : 'SELECT * FROM table WHERE field1=:name
     *         $params : ['field1' => 'foo']
     *         $types :  ['field1' => ParameterType::STRING]
     *
     * @example to bind with ?
     *         $selectQuery : 'SELECT * FROM table WHERE field1=?
     *         $params : ['foo']
     *         $types :  [ParameterType::STRING]
     */
    public function fetchOne(string $query, array $params = [], array $types = []): mixed
    {
        try {
            return $this->dbalConnection->fetchOne($query, $params, $types);
        } catch (Throwable $e) {
            throw ConnectionException::executeQueryFailed($e, $query, $params, $types);
        }
    }

    /**
     * Prepares and executes an SQL query and returns the result as an array of numeric arrays.
     *
     * Could be only used with SELECT.
     *
     * This method supports PDO binding types as well as DBAL mapping types.
     *
     * @param string                   $query
     * @param array<string,int|string> $params
     * @param array<string,int|string> $types {@see ParameterType}.
     *
     * @return array<array<int,mixed>>
     *
     * @throws ConnectionException
     *
     * @example to bind with named parameters
     *         $selectQuery : 'SELECT * FROM table WHERE field1=:name
     *         $params : ['field1' => 'foo']
     *         $types :  ['field1' => ParameterType::STRING]
     *
     * @example to bind with ?
     *         $selectQuery : 'SELECT * FROM table WHERE field1=?
     *         $params : ['foo']
     *         $types :  [ParameterType::STRING]
     */
    public function fetchAllNumeric(string $query, array $params = [], array $types = []): array
    {
        try {
            return $this->dbalConnection->fetchAllNumeric($query, $params, $types);
        } catch (Throwable $e) {
            throw ConnectionException::executeQueryFailed($e, $query, $params, $types);
        }
    }

    /**
     * Prepares and executes an SQL query and returns the result as an array of associative arrays.
     *
     * Could be only used with SELECT.
     *
     * This method supports PDO binding types as well as DBAL mapping types.
     *
     * @param string                   $query
     * @param array<string,int|string> $params
     * @param array<string,int|string> $types {@see ParameterType}.
     *
     * @return array<array<string,mixed>>
     *
     * @throws ConnectionException
     *
     * @example to bind with named parameters
     *         $selectQuery : 'SELECT * FROM table WHERE field1=:name
     *         $params : ['field1' => 'foo']
     *         $types :  ['field1' => ParameterType::STRING]
     *
     * @example to bind with ?
     *         $selectQuery : 'SELECT * FROM table WHERE field1=?
     *         $params : ['foo']
     *         $types :  [ParameterType::STRING]
     */
    public function fetchAllAssociative(string $query, array $params = [], array $types = []): array
    {
        try {
            return $this->dbalConnection->fetchAllAssociative($query, $params, $types);
        } catch (Throwable $e) {
            throw ConnectionException::executeQueryFailed($e, $query, $params, $types);
        }
    }

    /**
     * Prepares and executes an SQL query and returns the result as an associative array with the keys
     * mapped to the first column and the values mapped to the second column.
     *
     * Could be only used with SELECT.
     *
     * This method supports PDO binding types as well as DBAL mapping types.
     *
     * @param string                   $query
     * @param array<string,int|string> $params
     * @param array<string,int|string> $types {@see ParameterType}.
     *
     * @return array<int|string,mixed>
     *
     * @throws ConnectionException
     *
     * @example to bind with named parameters
     *         $selectQuery : 'SELECT * FROM table WHERE field1=:name
     *         $params : ['field1' => 'foo']
     *         $types :  ['field1' => ParameterType::STRING]
     *
     * @example to bind with ?
     *         $selectQuery : 'SELECT * FROM table WHERE field1=?
     *         $params : ['foo']
     *         $types :  [ParameterType::STRING]
     */
    public function fetchAllKeyValue(string $query, array $params = [], array $types = []): array
    {
        try {
            return $this->dbalConnection->fetchAllKeyValue($query, $params, $types);
        } catch (Throwable $e) {
            throw ConnectionException::executeQueryFailed($e, $query, $params, $types);
        }
    }

    /**
     * Prepares and executes an SQL query and returns the result as an associative array with the keys mapped
     * to the first column and the values being an associative array representing the rest of the columns
     * and their values.
     *
     * Could be only used with SELECT.
     *
     * This method supports PDO binding types as well as DBAL mapping types.
     *
     * @param string                   $query
     * @param array<string,int|string> $params
     * @param array<string,int|string> $types {@see ParameterType}.
     *
     * @return array<mixed,array<string,mixed>>
     *
     * @throws ConnectionException
     *
     * @example to bind with named parameters
     *         $selectQuery : 'SELECT * FROM table WHERE field1=:name
     *         $params : ['field1' => 'foo']
     *         $types :  ['field1' => ParameterType::STRING]
     *
     * @example to bind with ?
     *         $selectQuery : 'SELECT * FROM table WHERE field1=?
     *         $params : ['foo']
     *         $types :  [ParameterType::STRING]
     */
    public function fetchAllAssociativeIndexed(string $query, array $params = [], array $types = []): array
    {
        try {
            return $this->dbalConnection->fetchAllAssociativeIndexed($query, $params, $types);
        } catch (Throwable $e) {
            throw ConnectionException::executeQueryFailed($e, $query, $params, $types);
        }
    }

    /**
     * Prepares and executes an SQL query and returns the result as an array of the first column values.
     *
     * Could be only used with SELECT.
     *
     * This method supports PDO binding types as well as DBAL mapping types.
     *
     * @param string                   $query
     * @param array<string,int|string> $params
     * @param array<string,int|string> $types {@see ParameterType}.
     *
     * @return list<mixed>
     *
     * @throws ConnectionException
     *
     * @example to bind with named parameters
     *         $selectQuery : 'SELECT * FROM table WHERE field1=:name
     *         $params : ['field1' => 'foo']
     *         $types :  ['field1' => ParameterType::STRING]
     *
     * @example to bind with ?
     *         $selectQuery : 'SELECT * FROM table WHERE field1=?
     *         $params : ['foo']
     *         $types :  [ParameterType::STRING]
     */
    public function fetchFirstColumn(string $query, array $params = [], array $types = []): array
    {
        try {
            return $this->dbalConnection->fetchFirstColumn($query, $params, $types);
        } catch (Throwable $e) {
            throw ConnectionException::executeQueryFailed($e, $query, $params, $types);
        }
    }

    // --------------------------------------- ITERATE METHODS -----------------------------------------

    /**
     * Prepares and executes an SQL query and returns the result as an iterator over rows represented as numeric arrays.
     *
     * Could be only used with SELECT.
     *
     * This method supports PDO binding types as well as DBAL mapping types.
     *
     * @param string                   $query
     * @param array<string,int|string> $params
     * @param array<string,int|string> $types {@see ParameterType}.
     *
     * @return Traversable<int,list<mixed>>
     *
     * @throws ConnectionException
     *
     * @example to bind with named parameters
     *         $selectQuery : 'SELECT * FROM table WHERE field1=:name
     *         $params : ['field1' => 'foo']
     *         $types :  ['field1' => ParameterType::STRING]
     *
     * @example to bind with ?
     *         $selectQuery : 'SELECT * FROM table WHERE field1=?
     *         $params : ['foo']
     *         $types :  [ParameterType::STRING]
     */
    public function iterateNumeric(string $query, array $params = [], array $types = []): Traversable
    {
        try {
            return $this->dbalConnection->iterateNumeric($query, $params, $types);
        } catch (Throwable $e) {
            throw ConnectionException::executeQueryFailed($e, $query, $params, $types);
        }
    }

    /**
     * Prepares and executes an SQL query and returns the result as an iterator over rows represented
     * as associative arrays.
     *
     * Could be only used with SELECT.
     *
     * This method supports PDO binding types as well as DBAL mapping types.
     *
     * @param string                   $query
     * @param array<string,int|string> $params
     * @param array<string,int|string> $types {@see ParameterType}.
     *
     * @return Traversable<int,array<string,mixed>>
     *
     * @throws ConnectionException
     *
     * @example to bind with named parameters
     *         $selectQuery : 'SELECT * FROM table WHERE field1=:name
     *         $params : ['field1' => 'foo']
     *         $types :  ['field1' => ParameterType::STRING]
     *
     * @example to bind with ?
     *         $selectQuery : 'SELECT * FROM table WHERE field1=?
     *         $params : ['foo']
     *         $types :  [ParameterType::STRING]
     */
    public function iterateAssociative(string $query, array $params = [], array $types = []): Traversable
    {
        try {
            return $this->dbalConnection->iterateAssociative($query, $params, $types);
        } catch (Throwable $e) {
            throw ConnectionException::executeQueryFailed($e, $query, $params, $types);
        }
    }

    /**
     * Prepares and executes an SQL query and returns the result as an iterator with the keys
     * mapped to the first column and the values mapped to the second column.
     *
     * Could be only used with SELECT.
     *
     * This method supports PDO binding types as well as DBAL mapping types.
     *
     * @param string                   $query
     * @param array<string,int|string> $params
     * @param array<string,int|string> $types {@see ParameterType}.
     *
     * @return Traversable<mixed,mixed>
     *
     * @throws ConnectionException
     *
     * @example to bind with named parameters
     *         $selectQuery : 'SELECT * FROM table WHERE field1=:name
     *         $params : ['field1' => 'foo']
     *         $types :  ['field1' => ParameterType::STRING]
     *
     * @example to bind with ?
     *         $selectQuery : 'SELECT * FROM table WHERE field1=?
     *         $params : ['foo']
     *         $types :  [ParameterType::STRING]
     */
    public function iterateKeyValue(string $query, array $params = [], array $types = []): Traversable
    {
        try {
            return $this->dbalConnection->iterateKeyValue($query, $params, $types);
        } catch (Throwable $e) {
            throw ConnectionException::executeQueryFailed($e, $query, $params, $types);
        }
    }

    /**
     * Prepares and executes an SQL query and returns the result as an iterator with the keys mapped
     * to the first column and the values being an associative array representing the rest of the columns
     * and their values.
     *
     * Could be only used with SELECT.
     *
     * This method supports PDO binding types as well as DBAL mapping types.
     *
     * @param string                   $query
     * @param array<string,int|string> $params
     * @param array<string,int|string> $types {@see ParameterType}.
     *
     * @return Traversable<mixed,array<string,mixed>>
     *
     * @throws ConnectionException
     *
     * @example to bind with named parameters
     *         $selectQuery : 'SELECT * FROM table WHERE field1=:name
     *         $params : ['field1' => 'foo']
     *         $types :  ['field1' => ParameterType::STRING]
     *
     * @example to bind with ?
     *         $selectQuery : 'SELECT * FROM table WHERE field1=?
     *         $params : ['foo']
     *         $types :  [ParameterType::STRING]
     */
    public function iterateAssociativeIndexed(string $query, array $params = [], array $types = []): Traversable
    {
        try {
            return $this->dbalConnection->iterateAssociativeIndexed($query, $params, $types);
        } catch (Throwable $e) {
            throw ConnectionException::executeQueryFailed($e, $query, $params, $types);
        }
    }

    /**
     * Prepares and executes an SQL query and returns the result as an iterator over the first column values.
     *
     * Could be only used with SELECT.
     *
     * This method supports PDO binding types as well as DBAL mapping types.
     *
     * @param string                   $query
     * @param array<string,int|string> $params
     * @param array<string,int|string> $types {@see ParameterType}.
     *
     * @return Traversable<int,mixed>
     *
     * @throws ConnectionException
     *
     * @example to bind with named parameters
     *         $selectQuery : 'SELECT * FROM table WHERE field1=:name
     *         $params : ['field1' => 'foo']
     *         $types :  ['field1' => ParameterType::STRING]
     *
     * @example to bind with ?
     *         $selectQuery : 'SELECT * FROM table WHERE field1=?
     *         $params : ['foo']
     *         $types :  [ParameterType::STRING]
     */
    public function iterateColumn(string $query, array $params = [], array $types = []): Traversable
    {
        try {
            return $this->dbalConnection->iterateColumn($query, $params, $types);
        } catch (Throwable $e) {
            throw ConnectionException::executeQueryFailed($e, $query, $params, $types);
        }
    }

    // ----------------------------------------- TRANSACTIONS -----------------------------------------

    /**
     * Returns the current auto-commit mode for this connection.
     *
     * @return bool True if auto-commit mode is currently enabled for this connection, false otherwise.
     *
     * @see setAutoCommit
     */
    public function isAutoCommit(): bool
    {
        return $this->dbalConnection->isAutoCommit();
    }

    /**
     * Sets auto-commit mode for this connection.
     *
     * If a connection is in auto-commit mode, then all its SQL statements will be executed and committed as individual
     * transactions. Otherwise, its SQL statements are grouped into transactions that are terminated by a call to either
     * the method commit or the method rollback. By default, new connections are in auto-commit mode.
     *
     * NOTE: If this method is called during a transaction and the auto-commit mode is changed, the transaction is
     * committed. If this method is called and the auto-commit mode is not changed, the call is a no-op.
     *
     * @param bool $autoCommit True to enable auto-commit mode; false to disable it.
     *
     * @return void
     *
     * @throws ConnectionException
     */
    public function setAutoCommit(bool $autoCommit): void
    {
        try {
            $this->dbalConnection->setAutoCommit($autoCommit);
        } catch (Throwable $e) {
            throw ConnectionException::setAutoCommitFailed($e);
        }
    }

    /**
     * Checks whether a transaction is currently active.
     *
     * @return bool TRUE if a transaction is currently active, FALSE otherwise.
     */
    public function isTransactionActive(): bool
    {
        return $this->dbalConnection->isTransactionActive();
    }

    /**
     * Opens a new transaction. This must be closed by calling one of the following methods:
     * {@see commit} or {@see rollBack}
     *
     * Note that it is possible to create nested transactions, but that data will only be written to the database when
     * the level 1 transaction is committed.
     *
     * Similarly, if a rollback occurs in a nested transaction, the level 1 transaction will also be rolled back and
     * no data will be updated.
     *
     * @return void
     *
     * @throws ConnectionException
     */
    public function startTransaction(): void
    {
        try {
            // we check if the save points mode is available before run a nested transaction
            if ($this->isTransactionActive()) {
                if (! $this->dbalConnection->getDatabasePlatform()->supportsSavepoints()) {
                    throw ConnectionException::startNestedTransactionFailed();
                }
            }
            $this->dbalConnection->beginTransaction();
        } catch (Throwable $e) {
            throw ConnectionException::startTransactionFailed($e);
        }
    }

    /**
     * To validate a transaction.
     *
     * @return void
     *
     * @throws ConnectionException
     */
    public function commit(): void
    {
        try {
            $this->dbalConnection->commit();
        } catch (Throwable $e) {
            throw ConnectionException::commitTransactionFailed($e);
        }
    }

    /**
     * To cancel a transaction.
     *
     * @return void
     *
     * @throws ConnectionException
     */
    public function rollBack(): void
    {
        try {
            $this->dbalConnection->rollBack();
        } catch (Throwable $e) {
            throw ConnectionException::rollbackTransactionFailed($e);
        }
    }

    // ------------------------------------- UNBUFFERED QUERIES -----------------------------------------

    /**
     * Checks that the connection instance allows the use of unbuffered queries.
     *
     * For the moment, only pdo_mysql.
     *
     * @throws ConnectionException
     *
     * @todo to complete with mysqli, pdo_oracle
     */
    public function allowUnbufferedQuery(): void
    {
        $nativeConnection = $this->getNativeConnection();
        $driverName = match ($nativeConnection::class) {
            PDO::class => "pdo_{$nativeConnection->getAttribute(PDO::ATTR_DRIVER_NAME)}",
            default => "",
        };
        if (empty($driverName) || ! in_array($driverName, self::DRIVER_ALLOWED_UNBUFFERED_QUERY, true)) {
            throw ConnectionException::allowUnbufferedQueryFailed($nativeConnection::class);
        }
    }

    /**
     * Prepares a statement to execute a query without buffering. Only works for SELECT queries.
     *
     * @return void
     *
     * @throws ConnectionException
     */
    public function startUnbufferedQuery(): void
    {
        $this->allowUnbufferedQuery();
        $nativeConnection = $this->getNativeConnection();
        if ($nativeConnection instanceof PDO) {
            if (! $nativeConnection->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false)) {
                throw ConnectionException::startUnbufferedQueryFailed($nativeConnection::class);
            }
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
     * @return void
     *
     * @throws ConnectionException
     */
    public function stopUnbufferedQuery(): void
    {
        $nativeConnection = $this->getNativeConnection();
        if (! $this->isUnbufferedQueryActive()) {
            throw ConnectionException::stopUnbufferedQueryFailed(
                "Unbuffered query not active",
                $nativeConnection::class
            );
        }
        if ($nativeConnection instanceof PDO) {
            if (! $nativeConnection->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false)) {
                throw ConnectionException::stopUnbufferedQueryFailed(
                    "Unbuffered query failed",
                    $nativeConnection::class
                );
            }
        }
        $this->isBufferedQueryActive = true;
    }
}
