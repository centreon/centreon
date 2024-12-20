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

namespace Centreon\Infrastructure;

use Centreon\Domain\Log\Logger;
use PDO;
use PDOException;
use PDOStatement;
use Psr\Log\LoggerInterface;
use Throwable;
use Traversable;

/**
 * This class extend the PDO class and can be used to create a database
 * connection.
 * This class is used by all database repositories.
 *
 * @package Centreon\Infrastructure
 */
class DatabaseConnection extends PDO
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
     * Initialize the PDO connection
     *
     * @param LoggerInterface $logger
     * @param string          $host
     * @param string          $basename
     * @param string          $login
     * @param string          $password
     * @param int             $port
     *
     * @throws DatabaseConnectionException
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        string $host,
        private readonly string $basename,
        string $login,
        string $password,
        int $port = 3306
    ) {
        try {
            $dsn = "mysql:dbname={$basename};host={$host};port={$port}";
            $options = [
                PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ];
            parent::__construct($dsn, $login, $password, $options);
        } catch (PDOException $e) {
            $this->exceptionHandler(
                "Unable to connect to database",
                ['trace' => $e->getTraceAsString()],
                $dsn,
                $e
            );
        }
    }

    /**
     * Factory to connect to the database
     *
     * @param DatabaseConnectionConfig $connectionConfig
     *
     * @return DatabaseConnection
     *
     * @throws DatabaseConnectionException
     */
    public static function connect(DatabaseConnectionConfig $connectionConfig): self
    {
        return new self(
            new Logger(),
            $connectionConfig->dbHost,
            $connectionConfig->dbName,
            $connectionConfig->dbUser,
            $connectionConfig->dbPassword,
            $connectionConfig->dbPort
        );
    }

    /**
     * @return string
     */
    public function getCentreonDbName()
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
    public function getStorageDbName()
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
     */
    public function switchToDb(string $dbName): void
    {
        $this->query('use ' . $dbName);
    }

    /**
     * @return string
     */
    public function getCurrentDatabaseName(): string
    {
        return $this->basename;
    }

    /**
     * To know if the connection is established
     *
     * @return bool
     */
    public function isConnected(): bool
    {
        try {
            $this->executeQuery('SELECT 1');

            return true;
        } catch (DatabaseConnectionException $e) {
            return false;
        }
    }

    // --------------------------------------- CUD METHODS -----------------------------------------

    /**
     * Executes an SQL statement with the given parameters and returns the number of affected rows.
     *
     * Could be only used for DELETE.
     *
     * This method supports PDO binding types.
     *
     * @param string $query
     * @param array  $bindParams
     * @param bool   $withParamType
     *
     * @return int
     * @throws DatabaseConnectionException
     */
    public function delete(string $query, array $bindParams, bool $withParamType = false): int
    {
        try {
            $this->validateQueryString($query, 'DELETE', true);
            $stmt = $this->prepareQuery($query);
            $this->executePreparedQuery($stmt, $bindParams, $withParamType);

            return $stmt->rowCount();
        } catch (DatabaseConnectionException $e) {
            $this->exceptionHandler(
                "Error while deleting data : {$e->getMessage()}",
                [
                    'bind_params' => $bindParams,
                    'with_param_type' => $withParamType,
                ],
                $query,
                $e
            );
        }
    }

    /**
     * Executes an SQL statement with the given parameters and returns the number of affected rows.
     *
     * Could be only used for INSERT.
     *
     * This method supports PDO binding types.
     *
     * @param string $query
     * @param array  $bindParams
     * @param bool   $withParamType
     *
     * @return int
     * @throws DatabaseConnectionException
     */
    public function insert(string $query, array $bindParams, bool $withParamType = false): int
    {
        try {
            $this->validateQueryString($query, 'INSERT INTO', true);
            $stmt = $this->prepareQuery($query);
            $this->executePreparedQuery($stmt, $bindParams, $withParamType);

            return $stmt->rowCount();
        } catch (DatabaseConnectionException $e) {
            $this->exceptionHandler(
                "Error while inserting data : {$e->getMessage()}",
                [
                    'bind_params' => $bindParams,
                    'with_param_type' => $withParamType,
                ],
                $query,
                $e
            );
        }
    }

    /**
     * Executes an SQL statement with the given parameters and returns the number of affected rows.
     *
     * Could be only used for several INSERT.
     *
     * This method supports PDO binding types.
     *
     * @param string $tableName
     * @param array  $columns
     * @param array  $bindParams    An array of arrays of bind parameters wth the same length as $columns. The keys must
     *                              be the same as the columns.
     * @param bool   $withParamType If true, $bindParams must have an array of arrays like
     *                              ['column => ['value', PDO::PARAM_*]]
     *
     * @return int
     * @throws DatabaseConnectionException
     */
    public function iterateInsert(
        string $tableName,
        array $columns,
        array $bindParams,
        bool $withParamType = false
    ): int {
        try {
            if (empty($tableName)) {
                throw new DatabaseConnectionException(
                    'Table name must not be empty',
                    DatabaseConnectionException::ERROR_CODE_BAD_USAGE,
                    ['table_name' => $tableName]
                );
            }
            if (empty($columns)) {
                throw new DatabaseConnectionException(
                    'Columns must not be empty',
                    DatabaseConnectionException::ERROR_CODE_BAD_USAGE,
                    ['columns' => $columns]
                );
            }
            if (empty($bindParams)) {
                throw new DatabaseConnectionException(
                    'Bind parameters must not be empty',
                    DatabaseConnectionException::ERROR_CODE_BAD_USAGE,
                    ['bind_params' => $bindParams]
                );
            }
            $bindParamsToExecute = [];
            $query = "INSERT INTO $tableName (" . implode(', ', $columns) . ") VALUES";
            for ($i = 0, $iMax = count($bindParams); $i < $iMax; $i++) {
                if (! is_array($bindParams[$i])) {
                    throw new DatabaseConnectionException(
                        '$bindParams must be an array of arrays',
                        DatabaseConnectionException::ERROR_CODE_BAD_USAGE,
                        ['bin_params_in_error' => $bindParams[$i], 'bind_params' => $bindParams]
                    );
                }
                if (count($columns) !== count($bindParams[$i])) {
                    throw new DatabaseConnectionException(
                        'Columns and bind parameters must have the same length',
                        DatabaseConnectionException::ERROR_CODE_BAD_USAGE,
                        ['columns' => $columns, 'bin_params_in_error' => $bindParams[$i], 'bind_params' => $bindParams]
                    );
                }
                if ($i > 0) {
                    $query .= ',';
                }
                $query .= '(:' . implode('_' . $i . ', :', $columns) . '_' . $i . ')';
                foreach ($columns as $column) {
                    if (! isset($bindParams[$i][$column])) {
                        throw new DatabaseConnectionException(
                            "Column $column is not set in bindParams",
                            DatabaseConnectionException::ERROR_CODE_BAD_USAGE,
                            ['column' => $column, 'bind_params_in_error' => $bindParams[$i]]
                        );
                    }
                    if (! $withParamType) {
                        $bindParamsToExecute[$column . '_' . $i] = $bindParams[$i][$column];
                    } else {
                        if (! is_array($bindParams[$i][$column]) || count($bindParams[$i][$column]) !== 2) {
                            throw new DatabaseConnectionException(
                                "Column $column is not set correctly in bindParams, it must be an array with value and type",
                                DatabaseConnectionException::ERROR_CODE_BAD_USAGE,
                                ['column' => $column, 'bind_params_in_error' => $bindParams[$i]]
                            );
                        }
                        $bindParamsToExecute[$column . '_' . $i] = [
                            $bindParams[$i][$column][0],
                            $bindParams[$i][$column][1]
                        ];
                    }
                }
            }
            $stmt = $this->prepareQuery($query);
            $this->executePreparedQuery($stmt, $bindParamsToExecute, $withParamType);

            return $stmt->rowCount();
        } catch (Throwable $e) {
            $this->exceptionHandler(
                "Error while iterating insert datas : {$e->getMessage()}",
                [
                    'bind_params' => $bindParams,
                    'with_param_type' => $withParamType,
                ],
                $query ?? '',
                $e
            );
        }
    }

    /**
     * Executes an SQL statement with the given parameters and returns the number of affected rows.
     *
     * Could be only used for UPDATE.
     *
     * This method supports PDO binding types.
     *
     * @param string $query
     * @param array  $bindParams
     * @param bool   $withParamType
     *
     * @return int
     * @throws DatabaseConnectionException
     */
    public function update(string $query, array $bindParams, bool $withParamType = false): int
    {
        try {
            $this->validateQueryString($query, 'UPDATE', true);
            $stmt = $this->prepareQuery($query);
            $this->executePreparedQuery($stmt, $bindParams, $withParamType);

            return $stmt->rowCount();
        } catch (Throwable $e) {
            $this->exceptionHandler(
                "Error while updating data : {$e->getMessage()}",
                [
                    'bind_params' => $bindParams,
                    'with_param_type' => $withParamType,
                ],
                $query,
                $e
            );
        }
    }

    // --------------------------------------- FETCH METHODS -----------------------------------------

    /**
     * Prepares and executes an SQL query and returns the first row of the result as an associative array.
     *
     * Could be only used with SELECT.
     *
     * This method supports PDO binding types.
     *
     * @param string $query
     * @param array  $bindParams
     * @param bool   $withParamType
     *
     * @return array<string, mixed>|false False is returned if no rows are found.
     *
     * @throws DatabaseConnectionException
     */
    public function fetchAssociative(string $query, array $bindParams = [], bool $withParamType = false): false | array
    {
        try {
            $this->validateQueryString($query, 'SELECT', true);
            $pdoStatement = $this->prepareQuery($query);
            $pdoStatement = $this->executePreparedQuery($pdoStatement, $bindParams, $withParamType, PDO::FETCH_ASSOC);

            return $this->fetch($pdoStatement);
        } catch (Throwable $e) {
            $this->exceptionHandler(
                "Error while fetching data with fetchAssociative() : {$e->getMessage()}",
                [
                    'bind_params' => $bindParams,
                    'with_param_type' => $withParamType,
                ],
                $query,
                $e
            );
        }
    }

    /**
     * Prepares and executes an SQL query and returns the first row of the result
     * as a numerically indexed array.
     *
     * Could be only used with SELECT.
     *
     * This method supports PDO binding types.
     *
     * @param string $query
     * @param array  $bindParams
     * @param bool   $withParamType
     *
     * @return array<string, mixed>|false False is returned if no rows are found.
     *
     * @throws DatabaseConnectionException
     */
    public function fetchNumeric(string $query, array $bindParams = [], bool $withParamType = false): false | array
    {
        try {
            $this->validateQueryString($query, 'SELECT', true);
            $pdoStatement = $this->prepareQuery($query);
            $pdoStatement = $this->executePreparedQuery($pdoStatement, $bindParams, $withParamType, PDO::FETCH_NUM);

            return $this->fetch($pdoStatement);
        } catch (Throwable $e) {
            $this->exceptionHandler(
                "Error while fetching data with fetchNumeric() : {$e->getMessage()}",
                [
                    'bind_params' => $bindParams,
                    'with_param_type' => $withParamType,
                ],
                $query,
                $e
            );
        }
    }

    /**
     * Prepares and executes an SQL query and returns the first row of the result as a value of a single column.
     *
     * Could be only used with SELECT.
     *
     * This method supports PDO binding types.
     *
     * @param string $query
     * @param array  $bindParams
     * @param bool   $withParamType
     * @param int    $column
     *
     * @return array|bool
     * @throws DatabaseConnectionException
     */
    public function fetchByColumn(
        string $query,
        array $bindParams = [],
        int $column = 0,
        bool $withParamType = false
    ): mixed {
        try {
            $this->validateQueryString($query, 'SELECT', true);
            $pdoStatement = $this->prepareQuery($query);
            $pdoStatement = $this->executePreparedQuery(
                $pdoStatement,
                $bindParams,
                $withParamType,
                PDO::FETCH_COLUMN,
                [$column]
            );

            return $this->fetch($pdoStatement);
        } catch (Throwable $e) {
            $this->exceptionHandler(
                "Error while fetching data with fetchByColumn() : {$e->getMessage()}",
                [
                    'bind_params' => $bindParams,
                    'with_param_type' => $withParamType,
                    'column' => $column,
                ],
                $query,
                $e
            );
        }
    }

    /**
     * Prepares and executes an SQL query and returns the result as an array of numeric arrays.
     *
     * Could be only used with SELECT.
     *
     * This method supports PDO binding types.
     *
     * @param string $query
     * @param array  $bindParams
     * @param bool   $withParamType
     *
     * @return array<array<int,mixed>>
     *
     * @throws DatabaseConnectionException
     */
    public function fetchAllNumeric(string $query, array $bindParams = [], bool $withParamType = false): array
    {
        try {
            $this->validateQueryString($query, 'SELECT', true);
            $pdoStatement = $this->prepareQuery($query);
            $pdoStatement = $this->executePreparedQuery($pdoStatement, $bindParams, $withParamType, PDO::FETCH_NUM);

            return $this->fetchAll($pdoStatement);
        } catch (Throwable $e) {
            $this->exceptionHandler(
                "Error while fetching data with fetchAllNumeric() : {$e->getMessage()}",
                [
                    'bind_params' => $bindParams,
                    'with_param_type' => $withParamType,
                ],
                $query,
                $e
            );
        }
    }

    /**
     * Prepares and executes an SQL query and returns the result as an array of associative arrays.
     *
     * Could be only used with SELECT.
     *
     * This method supports PDO binding types.
     *
     * @param string $query
     * @param array  $bindParams
     * @param bool   $withParamType
     *
     * @return array<array<string,mixed>>
     *
     * @throws DatabaseConnectionException
     */
    public function fetchAllAssociative(string $query, array $bindParams = [], bool $withParamType = false): array
    {
        try {
            $this->validateQueryString($query, 'SELECT', true);
            $pdoStatement = $this->prepareQuery($query);
            $pdoStatement = $this->executePreparedQuery($pdoStatement, $bindParams, $withParamType, PDO::FETCH_ASSOC);

            return $this->fetchAll($pdoStatement);
        } catch (Throwable $e) {
            $this->exceptionHandler(
                "Error while fetching data with fetchAllAssociative() : {$e->getMessage()}",
                [
                    'bind_params' => $bindParams,
                    'with_param_type' => $withParamType,
                ],
                $query,
                $e
            );
        }
    }

    /**
     * Prepares and executes an SQL query and returns the result as an array of associative arrays with the name of the
     * column as key.
     *
     * Could be only used with SELECT.
     *
     * This method supports PDO binding types.
     *
     * @param string $query
     * @param array  $bindParams
     * @param bool   $withParamType
     * @param int    $column
     *
     * @return array<array<string,mixed>>
     *
     * @throws DatabaseConnectionException
     */
    public function fetchAllByColumn(
        string $query,
        array $bindParams = [],
        int $column = 0,
        bool $withParamType = false
    ): array {
        try {
            $this->validateQueryString($query, 'SELECT', true);
            $pdoStatement = $this->prepareQuery($query);
            $pdoStatement = $this->executePreparedQuery(
                $pdoStatement,
                $bindParams,
                $withParamType,
                PDO::FETCH_COLUMN,
                [$column]
            );

            return $this->fetchAll($pdoStatement);
        } catch (Throwable $e) {
            $this->exceptionHandler(
                "Error while fetching data with fetchAllByColumn() : {$e->getMessage()}",
                [
                    'bind_params' => $bindParams,
                    'with_param_type' => $withParamType,
                    'column' => $column,
                ],
                $query,
                $e
            );
        }
    }

    /**
     * Prefer to use fetchNumeric() or fetchAssociative() instead of this method.
     *
     * @param PDOStatement $pdoStatement
     *
     * @return mixed
     *
     * @throws DatabaseConnectionException
     *
     * @see fetchNumeric(), fetchAssociative()
     */
    public function fetch(PDOStatement $pdoStatement): mixed
    {
        try {
            return $pdoStatement->fetch();
        } catch (Throwable $e) {
            $this->closeQuery($pdoStatement);
            $this->exceptionHandler(
                "Error while fetching the row : {$e->getMessage()}",
                [],
                $pdoStatement->queryString,
                $e
            );
        }
    }

    /**
     * Prefer to use fetchAllNumeric() or fetchAllAssociative() instead of this method.
     *
     * @param PDOStatement $pdoStatement
     *
     * @return array
     *
     * @throws DatabaseConnectionException
     *
     * @see fetchAllNumeric(), fetchAllAssociative()
     */
    public function fetchAll(PDOStatement $pdoStatement): array
    {
        try {
            return $pdoStatement->fetchAll();
        } catch (Throwable $e) {
            $this->exceptionHandler(
                "Error while fetching all the rows : {$e->getMessage()}",
                [],
                $pdoStatement->queryString,
                $e
            );
        } finally {
            $this->closeQuery($pdoStatement);
        }
    }

    // --------------------------------------- ITERATE METHODS -----------------------------------------

    /**
     * Prepares and executes an SQL query and returns the result as an iterator over rows represented
     * as associative arrays.
     *
     * Could be only used with SELECT.
     *
     * This method supports PDO binding types.
     *
     * @param string $query
     * @param array  $bindParams
     * @param bool   $withParamType
     *
     * @return Traversable<int,array<string,mixed>>
     *
     * @throws DatabaseConnectionException
     */
    public function iterateAssociative(string $query, array $bindParams = [], bool $withParamType = false): Traversable
    {
        try {
            $this->validateQueryString($query, 'SELECT', true);
            $pdoStatement = $this->prepareQuery($query);
            $pdoStatement = $this->executePreparedQuery($pdoStatement, $bindParams, $withParamType, PDO::FETCH_ASSOC);
            while (($row = $this->fetch($pdoStatement)) !== false) {
                yield $row;
            }
        } catch (Throwable $e) {
            $this->exceptionHandler(
                "Error while fetching data with iterateAssociative() : {$e->getMessage()}",
                ['bind_params' => $bindParams, 'with_param_type' => $withParamType],
                $query,
                $e
            );
        }
    }

    /**
     * Prepares and executes an SQL query and returns the result as an iterator over rows represented as numeric arrays.
     *
     * Could be only used with SELECT.
     *
     * This method supports PDO binding types.
     *
     * @param string $query
     * @param array  $bindParams
     * @param bool   $withParamType
     *
     * @return Traversable<int,list<mixed>>
     *
     * @throws DatabaseConnectionException
     */
    public function iterateNumeric(string $query, array $bindParams = [], bool $withParamType = false): Traversable
    {
        try {
            $this->validateQueryString($query, 'SELECT', true);
            $pdoStatement = $this->prepareQuery($query);
            $pdoStatement = $this->executePreparedQuery($pdoStatement, $bindParams, $withParamType, PDO::FETCH_NUM);
            while (($row = $this->fetch($pdoStatement)) !== false) {
                yield $row;
            }
        } catch (Throwable $e) {
            $this->exceptionHandler(
                "Error while fetching data with iterateNumeric() : {$e->getMessage()}",
                ['bind_params' => $bindParams, 'with_param_type' => $withParamType],
                $query,
                $e
            );
        }
    }

    // --------------------------------------- DDL METHODS -----------------------------------------

    /**
     * Only for DDL queries (ALTER TABLE, CREATE TABLE, DROP TABLE, CREATE DATABASE, and TRUNCATE TABLE...)
     *
     * @param string $query
     *
     * @return bool
     * @throws DatabaseConnectionException
     */
    public function updateDatabase(string $query): bool
    {
        try {
            if (empty($query)) {
                throw new DatabaseConnectionException(
                    'Query must not be empty',
                    DatabaseConnectionException::ERROR_CODE_BAD_USAGE,
                    ['query' => $query]
                );
            }
            $standardQueryStarts = ['SELECT ', 'UPDATE ', 'DELETE ', 'INSERT INTO '];
            foreach ($standardQueryStarts as $standardQueryStart) {
                if (
                    str_starts_with($query, strtolower($standardQueryStart))
                    || str_starts_with($query, strtoupper($standardQueryStart))
                ) {
                    throw new DatabaseConnectionException(
                        'Query must not to start by SELECT, UPDATE, DELETE or INSERT INTO, this method is only for DDL queries',
                        DatabaseConnectionException::ERROR_CODE_BAD_USAGE,
                        ['query' => $query]
                    );
                }
            }

            return $this->exec($query) !== false;
        } catch (Throwable $e) {
            $this->exceptionHandler("Error while updating the database: {$e->getMessage()}", [], $query, $e);
        }
    }

    // --------------------------------------- BASE METHODS -----------------------------------------

    /**
     * @param string $query
     * @param array  $options
     *
     * @return PDOStatement|false Returns a PDOStatement object, or false on failure.
     * @throws DatabaseConnectionException
     */
    public function prepareQuery(string $query, array $options = []): PDOStatement | false
    {
        try {
            if (empty($query)) {
                throw new DatabaseConnectionException(
                    'Error while preparing query, query must not be empty',
                    DatabaseConnectionException::ERROR_CODE_BAD_USAGE,
                    ['query' => $query]
                );
            }

            return parent::prepare($query, $options);
        } catch (Throwable $e) {
            $this->exceptionHandler(
                "Error while preparing the query: {$e->getMessage()}",
                ['options' => $options],
                $query,
                $e
            );
        }
    }

    /**
     * Prepared query.
     *
     * Not for DDL queries
     *
     * @param PDOStatement $pdoStatement
     * @param array        $bindParams    It's optional only for SELECT queries
     * @param bool         $withParamType When $withParamType is true, $bindParams must have an array as value like
     *                                    ['value', PDO::PARAM_*]
     *                                    Allowed types : PDO::PARAM_STR, PDO::PARAM_BOOL, PDO::PARAM_INT, PDO::PARAM_NULL
     * @param int          $fetchMode     Only for the SELECT queries
     * @param array        $fetchModeArgs
     *
     * @return bool|PDOStatement If the query is a CUD query, it returns a boolean, if it's a SELECT query, it returns
     *                           a PDOStatement
     *
     * @throws DatabaseConnectionException
     */
    public function executePreparedQuery(
        PDOStatement $pdoStatement,
        array $bindParams = [],
        bool $withParamType = false,
        int $fetchMode = PDO::FETCH_ASSOC,
        array $fetchModeArgs = []
    ): bool | PDOStatement {
        try {
            $isCUD = (
                str_starts_with($pdoStatement->queryString, 'INSERT INTO ')
                || str_starts_with($pdoStatement->queryString, 'insert into ')
                || str_starts_with($pdoStatement->queryString, 'UPDATE ')
                || str_starts_with($pdoStatement->queryString, 'update ')
                || str_starts_with($pdoStatement->queryString, 'DELETE ')
                || str_starts_with($pdoStatement->queryString, 'delete ')
            );

            if (! $isCUD) {
                $this->validateQueryString($pdoStatement->queryString, 'SELECT', true);
            }

            if (($withParamType && $bindParams === []) || ($isCUD && $bindParams === [])) {
                throw new DatabaseConnectionException(
                    "Binding parameters are empty",
                    DatabaseConnectionException::ERROR_CODE_BAD_USAGE,
                    ['bind_params' => $bindParams]
                );
            }

            if ($withParamType) {
                foreach ($bindParams as $paramName => $bindParam) {
                    if (is_array($bindParam) && $bindParam !== [] && count($bindParam) === 2) {
                        $paramValue = $bindParam[0];
                        $paramType = $bindParam[1];
                        if (
                            ! in_array(
                                $paramType,
                                [PDO::PARAM_STR, PDO::PARAM_BOOL, PDO::PARAM_INT, PDO::PARAM_NULL],
                                true
                            )
                        ) {
                            throw new DatabaseConnectionException(
                                "Error for the param type, it's not an integer or a value of PDO::PARAM_*",
                                DatabaseConnectionException::ERROR_CODE_BAD_USAGE,
                                ['bind_param' => $bindParam]
                            );
                        }
                        $this->makeBindValue($pdoStatement, $paramName, $paramValue, $paramType);
                    } else {
                        throw new DatabaseConnectionException(
                            "Incorrect format for bindParam values, it must to be an array like ['value', PDO::PARAM_*]",
                            DatabaseConnectionException::ERROR_CODE_BAD_USAGE,
                            ['bind_param' => $bindParam]
                        );
                    }
                }
            }

            if ($isCUD) {
                return ($withParamType) ? $pdoStatement->execute() : $pdoStatement->execute($bindParams);
            } else {
                ($withParamType) ? $pdoStatement->execute() : $pdoStatement->execute($bindParams);
                $pdoStatement->setFetchMode($fetchMode, ...$fetchModeArgs);

                return $pdoStatement;
            }
        } catch (Throwable $e) {
            $this->exceptionHandler(
                "Error while executing the prepared query: {$e->getMessage()}",
                ['bind_params' => $bindParams],
                $pdoStatement->queryString,
                $e
            );
        }
    }

    /**
     * @param PDOStatement $pdoStatement
     * @param array|null   $bindParams
     *
     * @return bool (no signature for this method because of a bug with tests with \Centreon\Test\Mock\CentreonDb::execute())
     * @throws DatabaseConnectionException
     */
    public function execute(PDOStatement $pdoStatement, ?array $bindParams = null): bool
    {
        try {
            if ($bindParams === []) {
                throw new DatabaseConnectionException(
                    "To execute the query, bindParams must to be an array filled or null, empty array given",
                    DatabaseConnectionException::ERROR_CODE_BAD_USAGE,
                    ['bind_params' => $bindParams]
                );
            }

            return $pdoStatement->execute($bindParams);
        } catch (Throwable $e) {
            $this->exceptionHandler(
                "Error while executing the query: {$e->getMessage()}",
                ['bind_params' => $bindParams],
                $pdoStatement->queryString,
                $e
            );
        }
    }

    /**
     * Without prepared query, only for SELECT queries.
     *
     * Not used for DDL queries.
     *
     * This method does not support PDO binding types.
     *
     * @param       $query
     * @param int   $fetchMode
     * @param array $fetchModeArgs
     *
     * @return PDOStatement|bool
     *
     * @throws DatabaseConnectionException
     */
    public function executeQuery(
        $query,
        int $fetchMode = PDO::FETCH_ASSOC,
        array $fetchModeArgs = []
    ): PDOStatement | false {
        try {
            $this->validateQueryString($query, 'SELECT', true);
            $stmt = $this->prepare($query);
            $stmt->execute();
            $stmt->setFetchMode($fetchMode, ...$fetchModeArgs);

            return $stmt;
        } catch (Throwable $e) {
            $this->exceptionHandler(
                "Error while executing the query: {$e->getMessage()}",
                ['fetch_mode' => $fetchMode, 'fetch_mode_args' => $fetchModeArgs],
                $query,
                $e
            );
        }
    }

    /**
     *  Allowed types : PDO::PARAM_STR, PDO::PARAM_BOOL, PDO::PARAM_INT, PDO::PARAM_NULL
     *
     * @param PDOStatement $pdoStatement
     * @param int|string   $paramName
     * @param mixed        $value
     * @param int          $type
     *
     * @return bool
     * @throws DatabaseConnectionException
     */
    public function makeBindValue(
        PDOStatement $pdoStatement,
        int | string $paramName,
        mixed $value,
        int $type = PDO::PARAM_STR
    ): bool {
        try {
            if (empty($paramName)) {
                throw new DatabaseConnectionException(
                    "paramName must to be filled, empty given",
                    DatabaseConnectionException::ERROR_CODE_BAD_USAGE,
                    ['param_name' => $paramName]
                );
            }
            if (
                ! in_array(
                    $type,
                    [PDO::PARAM_STR, PDO::PARAM_BOOL, PDO::PARAM_INT, PDO::PARAM_NULL],
                    true
                )
            ) {
                throw new DatabaseConnectionException(
                    "Error for the param type, it's not an integer or a value of PDO::PARAM_*",
                    DatabaseConnectionException::ERROR_CODE_BAD_USAGE,
                    ['param_name' => $paramName]
                );
            }

            return $pdoStatement->bindValue($paramName, $value, $type);
        } catch (Throwable $e) {
            $this->exceptionHandler(
                "Error while binding value for param {$paramName} : {$e->getMessage()}",
                [
                    'param_name' => $paramName,
                    'param_value' => $value,
                    'param_type' => $type
                ],
                $pdoStatement->queryString,
                $e
            );
        }
    }

    /**
     * @param PDOStatement $pdoStatement
     * @param int|string   $paramName
     * @param mixed        $var
     * @param int          $type
     * @param int          $maxLength
     *
     * @return bool
     * @throws DatabaseConnectionException
     */
    public function makeBindParam(
        PDOStatement $pdoStatement,
        int | string $paramName,
        mixed &$var,
        int $type = PDO::PARAM_STR,
        int $maxLength = 0
    ): bool {
        try {
            if (empty($paramName)) {
                throw new DatabaseConnectionException(
                    "paramName must to be filled, empty given",
                    DatabaseConnectionException::ERROR_CODE_BAD_USAGE,
                    ['param_name' => $paramName]
                );
            }
            if (
                ! in_array(
                    $type,
                    [PDO::PARAM_STR, PDO::PARAM_BOOL, PDO::PARAM_INT, PDO::PARAM_NULL],
                    true
                )
            ) {
                throw new DatabaseConnectionException(
                    "Error for the param type, it's not an integer or a value of PDO::PARAM_*",
                    DatabaseConnectionException::ERROR_CODE_BAD_USAGE,
                    ['param_name' => $paramName]
                );
            }

            return $pdoStatement->bindParam($paramName, $var, $type, $maxLength);
        } catch (Throwable $e) {
            $this->exceptionHandler(
                "Error while binding param {$paramName} : {$e->getMessage()}",
                [
                    'param_name' => $paramName,
                    'param_var' => $var,
                    'param_type' => $type,
                    'param_max_length' => $maxLength
                ],
                $pdoStatement->queryString,
                $e
            );
        }
    }

    /**
     * @param PDOStatement $pdoStatement
     *
     * @return bool
     * @throws DatabaseConnectionException
     */
    public function closeQuery(PDOStatement $pdoStatement): bool
    {
        try {
            return $pdoStatement->closeCursor();
        } catch (Throwable $e) {
            $message = "Error while closing the PDOStatement cursor: {$e->getMessage()}";
            $this->writeDbLog($message, query: $pdoStatement->queryString, exception: $e);
            $exceptionOptions = ['query' => $pdoStatement->queryString,];
            if ($e instanceof PDOException) {
                $exceptionOptions['pdo_error_code'] = $e->getCode();
                $exceptionOptions['pdo_error_infos'] = $e->errorInfo;
            }
            throw new DatabaseConnectionException(
                $message,
                DatabaseConnectionException::ERROR_CODE_DATABASE,
                $exceptionOptions,
                $e
            );
        }
    }

    /**
     * @param string $string
     * @param int    $type
     *
     * @return string
     * @throws DatabaseConnectionException
     */
    public function escapeString(string $string, int $type = PDO::PARAM_STR): string
    {
        $quotedString = parent::quote($string, $type);
        if ($quotedString === false) {
            throw new DatabaseConnectionException(
                "Error while quoting the string: {$string}",
                DatabaseConnectionException::ERROR_CODE_DATABASE
            );
        }

        return $quotedString;
    }

    // --------------------------------------- PRIVATE METHODS -----------------------------------------

    /**
     * @param string         $message
     * @param array          $context
     * @param string         $query
     * @param Throwable|null $exception
     *
     * @return void
     * @throws DatabaseConnectionException
     */
    private function exceptionHandler(
        string $message,
        array $context = [],
        string $query = '',
        ?Throwable $exception = null
    ): void {
        $code = DatabaseConnectionException::ERROR_CODE_DATABASE;
        $this->writeDbLog(
            $message,
            $context,
            query: $query,
            exception: $exception
        );
        $exceptionOptions = array_merge(['query' => $query], $context);
        if ($exception instanceof PDOException) {
            $exceptionOptions['pdo_error_code'] = $exception->getCode();
            $exceptionOptions['pdo_error_infos'] = $exception->errorInfo;
        } elseif ($exception instanceof DatabaseConnectionException) {
            $code = $exception->getCode();
        }

        throw new DatabaseConnectionException($message, $code, $exceptionOptions, $exception);
    }

    /**
     * Write SQL errors messages
     *
     * @param string         $message
     * @param array          $customContext
     * @param string         $query
     * @param Throwable|null $exception
     */
    private function writeDbLog(
        string $message,
        array $customContext = [],
        string $query = '',
        ?Throwable $exception = null
    ): void {
        // context for the logger
        $defaultContext = ['db_name' => $this->getCurrentDatabaseName()];
        if (! empty($query)) {
            $defaultContext['query'] = $query;
        }
        if ($exception instanceof PDOException) {
            $defaultContext['pdo_error_infos'] = $exception->errorInfo;
            $defaultContext['pdo_error_code'] = $exception->getCode();
        }
        $context = array_merge($defaultContext, $customContext);
        // if the logger only has a context to log
        if ($exception === null) {
            $this->logger->error("[DatabaseConnection] $message", ['context' => $context]);

            return;
        }
        // if the logger has an exception to log
        $exceptionInfos = [
            'exception_type' => $exception::class,
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'code' => $exception->getCode(),
            'message' => $exception->getMessage()
        ];
        $this->logger->error(
            "[DatabaseConnection] $message",
            ['context' => $context, 'exception' => $exceptionInfos]
        );
    }

    /**
     * Validate a SELECT query
     *
     * @param string $query
     * @param string $queryKeyword
     * @param bool   $checkEmptyQuery
     *
     * @throws DatabaseConnectionException
     */
    private function validateQueryString(string $query, string $queryKeyword, bool $checkEmptyQuery): void
    {
        if ($checkEmptyQuery && empty($query)) {
            throw new DatabaseConnectionException(
                'Query must not be empty',
                DatabaseConnectionException::ERROR_CODE_BAD_USAGE,
                ['query' => $query]
            );
        }
        if (
            ! str_starts_with($query, mb_strtoupper($queryKeyword) . ' ')
            && ! str_starts_with($query, mb_strtolower($queryKeyword) . ' ')
        ) {
            throw new DatabaseConnectionException(
                'The query must to start by ' . mb_strtoupper($queryKeyword),
                DatabaseConnectionException::ERROR_CODE_BAD_USAGE,
                ['query' => $query]
            );
        }
    }

}
