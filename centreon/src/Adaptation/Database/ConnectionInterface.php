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

use Adaptation\Database\Collection\QueryParameters;
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
     * Return the database name if it exists.
     *
     * @throws ConnectionException
     * @return string|null
     */
    public function getDatabaseName(): ?string;

    /**
     * To get the used native connection by DBAL (PDO, mysqli, ...).
     *
     * @throws ConnectionException
     * @return object
     */
    public function getNativeConnection(): object;

    /***
     * Returns the ID of the last inserted row.
     * If the underlying driver does not support identity columns, an exception is thrown.
     *
     * @throws ConnectionException
     * @return string
     */
    public function getLastInsertId(): string;

    /**
     * Check if a connection with the database exist.
     *
     * @return bool
     */
    public function isConnected(): bool;

    /**
     * The usage of this method is discouraged. Use prepared statements.
     *
     * @param string $value
     *
     * @return string
     */
    public function quoteString(string $value): string;

    // ----------------------------------------- CUD METHODS ------------------------------------------

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
     * @param string          $query
     * @param QueryParameters $queryParameters
     *
     * @throws ConnectionException
     * @return int
     */
    public function executeStatement(string $query, QueryParameters $queryParameters): int;

    /**
     * Executes an SQL statement with the given parameters and returns the number of affected rows.
     *
     * Could be only used for INSERT.
     *
     * @param string          $query
     * @param QueryParameters $queryParameters
     *
     * @throws ConnectionException
     * @return int
     */
    public function insert(string $query, QueryParameters $queryParameters): int;

    /**
     * Executes an SQL statement with the given parameters and returns the number of affected rows.
     *
     * Could be only used for UPDATE.
     *
     * @param string          $query
     * @param QueryParameters $queryParameters
     *
     * @throws ConnectionException
     * @return int
     */
    public function update(string $query, QueryParameters $queryParameters): int;

    /**
     * Executes an SQL statement with the given parameters and returns the number of affected rows.
     *
     * Could be only used for DELETE.
     *
     * @param string          $query
     * @param QueryParameters $queryParameters
     *
     * @throws ConnectionException
     * @return int
     */
    public function delete(string $query, QueryParameters $queryParameters): int;

    // --------------------------------------- FETCH METHODS -----------------------------------------

    /**
     * Prepares and executes an SQL query and returns the first row of the result as an associative array.
     *
     * Could be only used with SELECT.
     *
     * @param string          $query
     * @param QueryParameters $queryParameters
     *
     * @throws ConnectionException
     * @return array<string, mixed>|false False is returned if no rows are found.
     */
    public function fetchAssociative(string $query, QueryParameters $queryParameters): false | array;

    /**
     * Prepares and executes an SQL query and returns the first row of the result
     * as a numerically indexed array.
     *
     * Could be only used with SELECT.
     *
     * @param string          $query
     * @param QueryParameters $queryParameters
     *
     * @throws ConnectionException
     * @return array<string, mixed>|false False is returned if no rows are found.
     */
    public function fetchNumeric(string $query, QueryParameters $queryParameters): false | array;

    /**
     * Prepares and executes an SQL query and returns the value of a single column
     * of the first row of the result.
     *
     * Could be only used with SELECT.
     *
     * @param string          $query
     * @param QueryParameters $queryParameters
     *
     * @throws ConnectionException
     * @return mixed|false False is returned if no rows are found.
     */
    public function fetchOne(string $query, QueryParameters $queryParameters): mixed;

    /**
     * Prepares and executes an SQL query and returns the result as an array of numeric arrays.
     *
     * Could be only used with SELECT.
     *
     * @param string          $query
     * @param QueryParameters $queryParameters
     *
     * @throws ConnectionException
     * @return array<array<int,mixed>>
     */
    public function fetchAllNumeric(string $query, QueryParameters $queryParameters): array;

    /**
     * Prepares and executes an SQL query and returns the result as an array of associative arrays.
     *
     * Could be only used with SELECT.
     *
     * @param string          $query
     * @param QueryParameters $queryParameters
     *
     * @throws ConnectionException
     * @return array<array<string,mixed>>
     */
    public function fetchAllAssociative(string $query, QueryParameters $queryParameters): array;

    /**
     * Prepares and executes an SQL query and returns the result as an associative array with the keys
     * mapped to the first column and the values mapped to the second column.
     *
     * Could be only used with SELECT.
     *
     * @param string          $query
     * @param QueryParameters $queryParameters
     *
     * @throws ConnectionException
     * @return array<int|string,mixed>
     */
    public function fetchAllKeyValue(string $query, QueryParameters $queryParameters): array;

    /**
     * Prepares and executes an SQL query and returns the result as an associative array with the keys mapped
     * to the first column and the values being an associative array representing the rest of the columns
     * and their values.
     *
     * Could be only used with SELECT.
     *
     * @param string          $query
     * @param QueryParameters $queryParameters
     *
     * @throws ConnectionException
     * @return array<mixed,array<string,mixed>>
     */
    public function fetchAllAssociativeIndexed(string $query, QueryParameters $queryParameters): array;

    /**
     * Prepares and executes an SQL query and returns the result as an array of the first column values.
     *
     * Could be only used with SELECT.
     *
     * @param string          $query
     * @param QueryParameters $queryParameters
     *
     * @throws ConnectionException
     * @return list<mixed>
     */
    public function fetchFirstColumn(string $query, QueryParameters $queryParameters): array;

    // --------------------------------------- ITERATE METHODS -----------------------------------------

    /**
     * Prepares and executes an SQL query and returns the result as an iterator over rows represented as numeric arrays.
     *
     * Could be only used with SELECT.
     *
     * @param string          $query
     * @param QueryParameters $queryParameters
     *
     * @throws ConnectionException
     * @return Traversable<int,list<mixed>>
     */
    public function iterateNumeric(string $query, QueryParameters $queryParameters): Traversable;

    /**
     * Prepares and executes an SQL query and returns the result as an iterator over rows represented
     * as associative arrays.
     *
     * Could be only used with SELECT.
     *
     * @param string          $query
     * @param QueryParameters $queryParameters
     *
     * @throws ConnectionException
     * @return Traversable<int,array<string,mixed>>
     */
    public function iterateAssociative(string $query, QueryParameters $queryParameters): Traversable;

    /**
     * Prepares and executes an SQL query and returns the result as an iterator with the keys
     * mapped to the first column and the values mapped to the second column.
     *
     * Could be only used with SELECT.
     *
     * @param string          $query
     * @param QueryParameters $queryParameters
     *
     * @throws ConnectionException
     * @return Traversable<mixed,mixed>
     */
    public function iterateKeyValue(string $query, QueryParameters $queryParameters): Traversable;

    /**
     * Prepares and executes an SQL query and returns the result as an iterator with the keys mapped
     * to the first column and the values being an associative array representing the rest of the columns
     * and their values.
     *
     * Could be only used with SELECT.
     *
     * @param string          $query
     * @param QueryParameters $queryParameters
     *
     * @throws ConnectionException
     * @return Traversable<mixed,array<string,mixed>>
     */
    public function iterateAssociativeIndexed(string $query, QueryParameters $queryParameters): Traversable;

    /**
     * Prepares and executes an SQL query and returns the result as an iterator over the first column values.
     *
     * Could be only used with SELECT.
     *
     * @param string          $query
     * @param QueryParameters $queryParameters
     *
     * @throws ConnectionException
     * @return Traversable<int,mixed>
     */
    public function iterateColumn(string $query, QueryParameters $queryParameters): Traversable;

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
     * @throws ConnectionException
     * @return void
     *
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
     * @throws ConnectionException
     * @return void
     *
     */
    public function startTransaction(): void;

    /**
     * To validate a transaction.
     *
     * @throws ConnectionException
     * @return void
     *
     */
    public function commit(): void;

    /**
     * To cancel a transaction.
     *
     * @throws ConnectionException
     * @return void
     *
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
     * @throws ConnectionException
     * @return void
     *
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
     * @throws ConnectionException
     * @return void
     *
     */
    public function stopUnbufferedQuery(): void;
}
