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

namespace Adaptation\Database;

use Adaptation\Database\Enum\ConnectionDriver;
use Adaptation\Database\Enum\ParameterType;
use Adaptation\Database\Exception\ConnectionException;
use Adaptation\Database\Model\ConnectionConfig;
use Traversable;

/**
 * Interface
 *
 * @class   ConnectionInterface
 * @package Adaptation\Database
 */
interface ConnectionInterface
{
    /**
     * The list of drivers that allow the use of unbuffered queries.
     */
    public const DRIVER_ALLOWED_UNBUFFERED_QUERY = [
        ConnectionDriver::DRIVER_MYSQL->value,
    ];

    /**
     * Factory
     *
     * @param ConnectionConfig $connectionConfig
     *
     * @return ConnectionInterface
     *
     * @throws ConnectionException
     */
    public static function createFromConfig(ConnectionConfig $connectionConfig): ConnectionInterface;

    /**
     * Creates a new instance of a SQL query builder.
     */
    public function createQueryBuilder(): QueryBuilderInterface;

    /**
     * Creates an expression builder for the connection.
     */
    public function createExpressionBuilder(): ExpressionBuilderInterface;

    /**
     * Return the database name if it exists.
     *
     * @return string|null
     *
     * @throws ConnectionException
     */
    public function getDatabaseName(): ?string;

    /**
     * To get the used native connection by DBAL (PDO, mysqli, ...).
     *
     * @return object
     *
     * @throws ConnectionException
     */
    public function getNativeConnection(): object;

    /***
     * Returns the ID of the last inserted row.
     * If the underlying driver does not support identity columns, an exception is thrown.
     *
     * @return string
     *
     * @throws ConnectionException
     */
    public function getLastInsertId(): string;

    /**
     * Check if a connection with the database exist.
     *
     * @return bool
     */
    public function isConnected(): bool;

    /**
     * Closes the connection.
     */
    public function close(): void;

    /**
     * The usage of this method is discouraged. Use prepared statements.
     *
     * @param string $value
     *
     * @return string
     */
    public function quote(string $value): string;

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
     */
    public function executeStatement(string $query, array $params = [], array $types = []): int;

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
     */
    public function insert(string $query, array $params = [], array $types = []): int;

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
     */
    public function update(string $query, array $params = [], array $types = []): int;

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
     */
    public function delete(string $query, array $params = [], array $types = []): int;

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
     */
    public function fetchAssociative(string $query, array $params = [], array $types = []): false | array;

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
     */
    public function fetchNumeric(string $query, array $params = [], array $types = []): false | array;

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
     */
    public function fetchOne(string $query, array $params = [], array $types = []): mixed;

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
     */
    public function fetchAllNumeric(string $query, array $params = [], array $types = []): array;

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
     */
    public function fetchAllAssociative(string $query, array $params = [], array $types = []): array;

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
     */
    public function fetchAllKeyValue(string $query, array $params = [], array $types = []): array;

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
     */
    public function fetchAllAssociativeIndexed(string $query, array $params = [], array $types = []): array;

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
     */
    public function fetchFirstColumn(string $query, array $params = [], array $types = []): array;

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
     */
    public function iterateNumeric(string $query, array $params = [], array $types = []): Traversable;

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
     */
    public function iterateAssociative(string $query, array $params = [], array $types = []): Traversable;

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
     */
    public function iterateKeyValue(string $query, array $params = [], array $types = []): Traversable;

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
     */
    public function iterateAssociativeIndexed(string $query, array $params = [], array $types = []): Traversable;

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
     */
    public function iterateColumn(string $query, array $params = [], array $types = []): Traversable;

    // ----------------------------------------- TRANSACTIONS -----------------------------------------

    /**
     * Returns the current auto-commit mode for this connection.
     *
     * @return bool True if auto-commit mode is currently enabled for this connection, false otherwise.
     *
     * @see setAutoCommit
     */
    public function isAutoCommit(): bool;

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
    public function setAutoCommit(bool $autoCommit): void;

    /**
     * Checks whether a transaction is currently active.
     *
     * @return bool TRUE if a transaction is currently active, FALSE otherwise.
     */
    public function isTransactionActive(): bool;

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
    public function startTransaction(): void;

    /**
     * To validate a transaction.
     *
     * @return void
     *
     * @throws ConnectionException
     */
    public function commit(): void;

    /**
     * To cancel a transaction.
     *
     * @return void
     *
     * @throws ConnectionException
     */
    public function rollBack(): void;

    // ------------------------------------- UNBUFFERED QUERIES -----------------------------------------

    /**
     * Checks that the connection instance allows the use of unbuffered queries.
     *
     * @throws ConnectionException
     */
    public function allowUnbufferedQuery(): void;

    /**
     * Prepares a statement to execute a query without buffering. Only works for SELECT queries.
     *
     * @return void
     *
     * @throws ConnectionException
     */
    public function startUnbufferedQuery(): void;

    /**
     * Checks whether an unbuffered query is currently active.
     *
     * @return bool
     */
    public function isUnbufferedQueryActive(): bool;

    /**
     * To close an unbuffered query.
     *
     * @return void
     *
     * @throws ConnectionException
     */
    public function stopUnbufferedQuery(): void;
}
