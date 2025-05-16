<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * https://www.apache.org/licenses/LICENSE-2.0
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

namespace Adaptation\Database\Connection;

use Adaptation\Database\Connection\Collection\BatchInsertParameters;
use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\Exception\ConnectionException;
use Adaptation\Database\Connection\Model\ConnectionConfig;

/**
 * Interface.
 *
 * @class   ConnectionInterface
 */
interface ConnectionInterface
{
    /**
     * The list of drivers that allow the use of unbuffered queries.
     */
    public const DRIVER_ALLOWED_UNBUFFERED_QUERY = [
        'pdo_mysql',
    ];

    /**
     * Factory.
     *
     * @param ConnectionConfig $connectionConfig
     *
     * @throws ConnectionException
     *
     * @return ConnectionInterface
     */
    public static function createFromConfig(ConnectionConfig $connectionConfig): self;

    /**
     * @return ConnectionConfig
     */
    public function getConnectionConfig(): ConnectionConfig;

    /**
     * Return the database name if it exists.
     *
     * @throws ConnectionException
     *
     * @return string|null
     */
    public function getDatabaseName(): ?string;

    /**
     * To get the used native connection by DBAL (PDO, mysqli, ...).
     *
     * @throws ConnectionException
     *
     * @return object|resource
     */
    public function getNativeConnection(): mixed;

    /**
     * Returns the ID of the last inserted row.
     * If the underlying driver does not support identity columns, an exception is thrown.
     *
     * @throws ConnectionException
     *
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
     *
     * @return int
     *
     * @example $queryParameters = QueryParameters::create([QueryParameter::int('id', 1), QueryParameter::string('name', 'John')]);
     *          $nbAffectedRows = $db->executeStatement('UPDATE table SET name = :name WHERE id = :id', $queryParameters);
     *          // $nbAffectedRows = 1
     */
    public function executeStatement(string $query, ?QueryParameters $queryParameters = null): int;

    /**
     * Executes an SQL statement with the given parameters and returns the number of affected rows.
     *
     * Could be only used for INSERT.
     *
     * @param string $query
     * @param QueryParameters|null $queryParameters
     *
     * @throws ConnectionException
     *
     * @return int
     *
     * @example $queryParameters = QueryParameters::create([QueryParameter::int('id', 1), QueryParameter::string('name', 'John')]);
     *          $nbAffectedRows = $db->insert('INSERT INTO table (id, name) VALUES (:id, :name)', $queryParameters);
     *          // $nbAffectedRows = 1
     */
    public function insert(string $query, ?QueryParameters $queryParameters = null): int;

    /**
     * Executes an SQL statement with the given parameters and returns the number of affected rows for multiple inserts.
     *
     * Could be only used for several INSERT.
     *
     * @param string $tableName
     * @param array<string> $columns
     * @param BatchInsertParameters $batchInsertParameters
     *
     * @throws ConnectionException
     *
     * @return int
     *
     * @example $batchInsertParameters = BatchInsertParameters::create([
     *              QueryParameters::create([QueryParameter::int('id', 1), QueryParameter::string('name', 'John')]),
     *              QueryParameters::create([QueryParameter::int('id', 2), QueryParameter::string('name', 'Jean')]),
     *          ]);
     *          $nbAffectedRows = $db->batchInsert('table', ['id', 'name'], $batchInsertParameters);
     *          // $nbAffectedRows = 2
     */
    public function batchInsert(string $tableName, array $columns, BatchInsertParameters $batchInsertParameters): int;

    /**
     * Executes an SQL statement with the given parameters and returns the number of affected rows.
     *
     * Could be only used for UPDATE.
     *
     * @param string $query
     * @param QueryParameters|null $queryParameters
     *
     * @throws ConnectionException
     *
     * @return int
     *
     * @example $queryParameters = QueryParameters::create([QueryParameter::int('id', 1), QueryParameter::string('name', 'John')]);
     *          $nbAffectedRows = $db->update('UPDATE table SET name = :name WHERE id = :id', $queryParameters);
     *          // $nbAffectedRows = 1
     */
    public function update(string $query, ?QueryParameters $queryParameters = null): int;

    /**
     * Executes an SQL statement with the given parameters and returns the number of affected rows.
     *
     * Could be only used for DELETE.
     *
     * @param string $query
     * @param QueryParameters|null $queryParameters
     *
     * @throws ConnectionException
     *
     * @return int
     *
     * @example $queryParameters = QueryParameters::create([QueryParameter::int('id', 1)]);
     *          $nbAffectedRows = $db->delete('DELETE FROM table WHERE id = :id', $queryParameters);
     *          // $nbAffectedRows = 1
     */
    public function delete(string $query, ?QueryParameters $queryParameters = null): int;

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
     *
     * @return array<int, mixed>|false false is returned if no rows are found
     *
     * @example $queryParameters = QueryParameters::create([QueryParameter::int('id', 1)]);
     *          $result = $db->fetchNumeric('SELECT * FROM table WHERE id = :id', $queryParameters);
     *          // $result = [0 => 1, 1 => 'John', 2 => 'Doe']
     */
    public function fetchNumeric(string $query, ?QueryParameters $queryParameters = null): false|array;

    /**
     * Prepares and executes an SQL query and returns the first row of the result as an associative array.
     *
     * Could be only used with SELECT.
     *
     * @param string $query
     * @param QueryParameters|null $queryParameters
     *
     * @throws ConnectionException
     *
     * @return array<string, mixed>|false false is returned if no rows are found
     *
     * @example $queryParameters = QueryParameters::create([QueryParameter::int('id', 1)]);
     *          $result = $db->fetchAssociative('SELECT * FROM table WHERE id = :id', $queryParameters);
     *          // $result = ['id' => 1, 'name' => 'John', 'surname' => 'Doe']
     */
    public function fetchAssociative(string $query, ?QueryParameters $queryParameters = null): false|array;

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
     *
     * @return mixed|false false is returned if no rows are found
     *
     * @example $queryParameters = QueryParameters::create([QueryParameter::string('name', 'John')]);
     *          $result = $db->fetchOne('SELECT name FROM table WHERE name = :name', $queryParameters);
     *          // $result = 'John'
     */
    public function fetchOne(string $query, ?QueryParameters $queryParameters = null): mixed;

    /**
     * Prepares and executes an SQL query and returns the result as an array of the first column values.
     *
     * Could be only used with SELECT.
     *
     * @param string $query
     * @param QueryParameters|null $queryParameters
     *
     * @throws ConnectionException
     *
     * @return list<mixed>
     *
     * @example $queryParameters = QueryParameters::create([QueryParameter::bool('active', true)]);
     *          $result = $db->fetchFirstColumn('SELECT name FROM table WHERE active = :active', $queryParameters);
     *          // $result = ['John', 'Jean']
     */
    public function fetchFirstColumn(string $query, ?QueryParameters $queryParameters = null): array;

    /**
     * Prepares and executes an SQL query and returns the result as an array of numeric arrays.
     *
     * Could be only used with SELECT.
     *
     * @param string $query
     * @param QueryParameters|null $queryParameters
     *
     * @throws ConnectionException
     *
     * @return array<array<int,mixed>>
     *
     * @example $queryParameters = QueryParameters::create([QueryParameter::bool('active', true)]);
     *          $result = $db->fetchAllNumeric('SELECT * FROM table WHERE active = :active', $queryParameters);
     *          // $result = [[0 => 1, 1 => 'John', 2 => 'Doe'], [0 => 2, 1 => 'Jean', 2 => 'Dupont']]
     */
    public function fetchAllNumeric(string $query, ?QueryParameters $queryParameters = null): array;

    /**
     * Prepares and executes an SQL query and returns the result as an array of associative arrays.
     *
     * Could be only used with SELECT.
     *
     * @param string $query
     * @param QueryParameters|null $queryParameters
     *
     * @throws ConnectionException
     *
     * @return array<array<string,mixed>>
     *
     * @example $queryParameters = QueryParameters::create([QueryParameter::bool('active', true)]);
     *          $result = $db->fetchAllAssociative('SELECT * FROM table WHERE active = :active', $queryParameters);
     *          // $result = [['id' => 1, 'name' => 'John', 'surname' => 'Doe'], ['id' => 2, 'name' => 'Jean', 'surname' => 'Dupont']]
     */
    public function fetchAllAssociative(string $query, ?QueryParameters $queryParameters = null): array;

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
     *
     * @return array<int|string,mixed>
     *
     * @example $queryParameters = QueryParameters::create([QueryParameter::bool('active', true)]);
     *          $result = $db->fetchAllKeyValue('SELECT name, surname FROM table WHERE active = :active', $queryParameters);
     *          // $result = ['John' => 'Doe', 'Jean' => 'Dupont']
     */
    public function fetchAllKeyValue(string $query, ?QueryParameters $queryParameters = null): array;

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
     *
     * @return array<mixed,array<string,mixed>>
     *
     * @example $queryParameters = QueryParameters::create([QueryParameter::bool('active', true)]);
     *          $result = $db->fetchAllAssociativeIndexed('SELECT id, name, surname FROM table WHERE active = :active', $queryParameters);
     *          // $result = [1 => ['name' => 'John', 'surname' => 'Doe'], 2 => ['name' => 'Jean', 'surname' => 'Dupont']]
     */
    public function fetchAllAssociativeIndexed(string $query, ?QueryParameters $queryParameters = null): array;

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
     *
     * @return \Traversable<int,list<mixed>>
     *
     * @example $queryParameters = QueryParameters::create([QueryParameter::bool('active', true)]);
     *          $result = $db->iterateNumeric('SELECT * FROM table WHERE active = :active', $queryParameters);
     *          foreach ($result as $row) {
     *              // $row = [0 => 1, 1 => 'John', 2 => 'Doe']
     *              // $row = [0 => 2, 1 => 'Jean', 2 => 'Dupont']
     *          }
     */
    public function iterateNumeric(string $query, ?QueryParameters $queryParameters = null): \Traversable;

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
     *
     * @return \Traversable<int,array<string,mixed>>
     *
     * @example $queryParameters = QueryParameters::create([QueryParameter::bool('active', true)]);
     *          $result = $db->iterateAssociative('SELECT * FROM table WHERE active = :active', $queryParameters);
     *          foreach ($result as $row) {
     *              // $row = ['id' => 1, 'name' => 'John', 'surname' => 'Doe']
     *              // $row = ['id' => 2, 'name' => 'Jean', 'surname' => 'Dupont']
     *          }
     */
    public function iterateAssociative(string $query, ?QueryParameters $queryParameters = null): \Traversable;

    /**
     * Prepares and executes an SQL query and returns the result as an iterator over the column values.
     *
     * Could be only used with SELECT.
     *
     * @param string $query
     * @param QueryParameters|null $queryParameters
     *
     * @throws ConnectionException
     *
     * @return \Traversable<int,list<mixed>>
     *
     * @example $queryParameters = QueryParameters::create([QueryParameter::bool('active', true)]);
     *          $result = $db->iterateFirstColumn('SELECT name FROM table WHERE active = :active', $queryParameters);
     *          foreach ($result as $value) {
     *              // $value = 'John'
     *              // $value = 'Jean'
     *          }
     */
    public function iterateColumn(string $query, ?QueryParameters $queryParameters = null): \Traversable;

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
     *
     * @return \Traversable<mixed,mixed>
     *
     * @example $queryParameters = QueryParameters::create([QueryParameter::bool('active', true)]);
     *          $result = $db->iterateKeyValue('SELECT name, surname FROM table WHERE active = :active', $queryParameters);
     *          foreach ($result as $key => $value) {
     *              // $key = 'John', $value = 'Doe'
     *              // $key = 'Jean', $value = 'Dupont'
     *          }
     */
    public function iterateKeyValue(string $query, ?QueryParameters $queryParameters = null): \Traversable;

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
     *
     * @return \Traversable<mixed,array<string,mixed>>
     *
     * @example $queryParameters = QueryParameters::create([QueryParameter::bool('active', true)]);
     *          $result = $db->iterateAssociativeIndexed('SELECT id, name, surname FROM table WHERE active = :active', $queryParameters);
     *          foreach ($result as $key => $row) {
     *              // $key = 1, $row = ['name' => 'John', 'surname' => 'Doe']
     *              // $key = 2, $row = ['name' => 'Jean', 'surname' => 'Dupont']
     *          }
     */
    public function iterateAssociativeIndexed(string $query, ?QueryParameters $queryParameters = null): \Traversable;

    // ----------------------------------------- TRANSACTIONS -----------------------------------------

    /**
     * Checks whether a transaction is currently active.
     *
     * @return bool TRUE if a transaction is currently active, FALSE otherwise
     */
    public function isTransactionActive(): bool;

    /**
     * Opens a new transaction. This must be closed by calling one of the following methods:
     * {@see commitTransaction} or {@see rollBackTransaction}.
     *
     * @throws ConnectionException
     */
    public function startTransaction(): void;

    /**
     * To validate a transaction.
     *
     * @throws ConnectionException
     *
     * @return bool
     */
    public function commitTransaction(): bool;

    /**
     * To cancel a transaction.
     *
     * @throws ConnectionException
     *
     * @return bool
     */
    public function rollBackTransaction(): bool;

    // ------------------------------------- UNBUFFERED QUERIES -----------------------------------------

    /**
     * Checks that the connection instance allows the use of unbuffered queries.
     *
     * @throws ConnectionException
     *
     * @return bool
     */
    public function allowUnbufferedQuery(): bool;

    /**
     * Prepares a statement to execute a query without buffering. Only works for SELECT queries.
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
     * @throws ConnectionException
     */
    public function stopUnbufferedQuery(): void;
}
