<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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

use Adaptation\Database\Connection\Adapter\Pdo\Transformer\PdoParameterTypeTransformer;
use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\ConnectionInterface;
use Adaptation\Database\Connection\Exception\ConnectionException;
use Adaptation\Database\Connection\Model\ConnectionConfig;
use Adaptation\Database\Connection\Trait\ConnectionTrait;
use Centreon\Domain\Log\Logger;

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
    use ConnectionTrait;

    /**
     * By default, the queries are buffered.
     *
     * @var bool
     */
    private bool $isBufferedQueryActive = true;

    /**
     * DatabaseConnection constructor.
     *
     * @param ConnectionConfig $connectionConfig
     *
     * @throws ConnectionException
     */
    public function __construct(
        private readonly ConnectionConfig $connectionConfig,
    ) {
        try {
            parent::__construct(
                $this->connectionConfig->getMysqlDsn(),
                $this->connectionConfig->getUser(),
                $this->connectionConfig->getPassword(),
                [
                    \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$this->connectionConfig->getCharset()}",
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                ]
            );
        } catch (\PDOException $exception) {
            $this->writeDbLog(
                message: "Unable to connect to database : {$exception->getMessage()}",
                customContext: ['dsn_mysql' => $this->connectionConfig->getMysqlDsn()],
                previous: $exception,
            );

            throw ConnectionException::connectionFailed($exception);
        }
    }

    /**
     * Factory
     *
     * @param ConnectionConfig $connectionConfig
     *
     * @throws ConnectionException
     * @return DatabaseConnection
     */
    public static function createFromConfig(ConnectionConfig $connectionConfig): self
    {
        return new self($connectionConfig);
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
     * @return ConnectionConfig
     */
    public function getConnectionConfig(): ConnectionConfig
    {
        return $this->connectionConfig;
    }

    /**
     * To get the used native connection by DBAL (PDO, mysqli, ...).
     *
     * @return \PDO
     */
    public function getNativeConnection(): \PDO
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
        } catch (\Throwable $exception) {
            $this->writeDbLog(
                message: 'Unable to get last insert id',
                previous: $exception,
            );

            throw ConnectionException::getLastInsertFailed($exception);
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
        } catch (ConnectionException $exception) {
            $this->writeDbLog(
                message: 'Unable to establish the connection.',
                query: 'SELECT 1',
                previous: $exception,
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
                    'Cannot use it with a SELECT query',
                    $query
                );
            }

            $pdoStatement = $this->prepare($query);

            if (! is_null($queryParameters) && ! $queryParameters->isEmpty()) {
                foreach ($queryParameters->getIterator() as $queryParameter) {
                    $pdoStatement->bindValue(
                        ":{$queryParameter->getName()}",
                        $queryParameter->getValue(),
                        ($queryParameter->getType() !== null)
                            ? PdoParameterTypeTransformer::transformFromQueryParameterType(
                            $queryParameter->getType()
                        ) : \PDO::PARAM_STR
                    );
                }
            }

            $pdoStatement->execute();

            return $pdoStatement->rowCount();
        } catch (\Throwable $exception) {
            $this->writeDbLog(
                message: 'Unable to execute statement',
                customContext: ['query_parameters' => $queryParameters],
                query: $query,
                previous: $exception,
            );

            throw ConnectionException::executeStatementFailed($exception, $query, $queryParameters);
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
     * @return array<int, mixed>|false false is returned if no rows are found
     *
     * @example $queryParameters = QueryParameters::create([QueryParameter::int('id', 1)]);
     *          $result = $db->fetchNumeric('SELECT * FROM table WHERE id = :id', $queryParameters);
     *          // $result = [0 => 1, 1 => 'John', 2 => 'Doe']
     */
    public function fetchNumeric(string $query, ?QueryParameters $queryParameters = null): false|array
    {
        try {
            $this->validateSelectQuery($query);
            $pdoStatement = $this->executeSelectQuery($query, $queryParameters);

            return $pdoStatement->fetch(\PDO::FETCH_NUM);
        } catch (\Throwable $exception) {
            $this->writeDbLog(
                message: 'Unable to fetch numeric query',
                customContext: ['query_parameters' => $queryParameters],
                query: $query,
                previous: $exception,
            );

            throw ConnectionException::fetchNumericQueryFailed($exception, $query, $queryParameters);
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
     * @return array<string, mixed>|false false is returned if no rows are found
     *
     * @example $queryParameters = QueryParameters::create([QueryParameter::int('id', 1)]);
     *          $result = $db->fetchAssociative('SELECT * FROM table WHERE id = :id', $queryParameters);
     *          // $result = ['id' => 1, 'name' => 'John', 'surname' => 'Doe']
     */
    public function fetchAssociative(string $query, ?QueryParameters $queryParameters = null): false|array
    {
        try {
            $this->validateSelectQuery($query);
            $pdoStatement = $this->executeSelectQuery($query, $queryParameters);

            return $pdoStatement->fetch(\PDO::FETCH_ASSOC);
        } catch (\Throwable $exception) {
            $this->writeDbLog(
                message: 'Unable to fetch associative query',
                customContext: ['query_parameters' => $queryParameters],
                query: $query,
                previous: $exception,
            );

            throw ConnectionException::fetchAssociativeQueryFailed($exception, $query, $queryParameters);
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
     * @return mixed|false false is returned if no rows are found
     *
     * @example $queryParameters = QueryParameters::create([QueryParameter::string('name', 'John')]);
     *          $result = $db->fetchOne('SELECT name FROM table WHERE name = :name', $queryParameters);
     *          // $result = 'John'
     */
    public function fetchOne(string $query, ?QueryParameters $queryParameters = null): mixed
    {
        try {
            $this->validateSelectQuery($query);
            $pdoStatement = $this->executeSelectQuery($query, $queryParameters);

            return $pdoStatement->fetch(\PDO::FETCH_COLUMN);
        } catch (\Throwable $exception) {
            $this->writeDbLog(
                message: 'Unable to fetch one query',
                customContext: ['query_parameters' => $queryParameters],
                query: $query,
                previous: $exception,
            );

            throw ConnectionException::fetchOneQueryFailed($exception, $query, $queryParameters);
        }
    }

    /**
     * Prepares and executes an SQL query and returns the result as an array of the first column values.
     *
     * Could be only used with SELECT.
     *
     * @param string $query
     * @param QueryParameters|null $queryParameters
     *
     * @throws ConnectionException
     * @return list<mixed>
     *
     * @example $queryParameters = QueryParameters::create([QueryParameter::bool('active', true)]);
     *          $result = $db->fetchFirstColumn('SELECT name FROM table WHERE active = :active', $queryParameters);
     *          // $result = ['John', 'Jean']
     */
    public function fetchFirstColumn(string $query, ?QueryParameters $queryParameters = null): array
    {
        try {
            $this->validateSelectQuery($query);
            $pdoStatement = $this->executeSelectQuery($query, $queryParameters);

            return $pdoStatement->fetchAll(\PDO::FETCH_COLUMN);
        } catch (\Throwable $exception) {
            $this->writeDbLog(
                message: 'Unable to fetch by column query',
                customContext: ['query_parameters' => $queryParameters],
                query: $query,
                previous: $exception,
            );

            throw ConnectionException::fetchFirstColumnQueryFailed($exception, $query, $queryParameters);
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
            $pdoStatement = $this->executeSelectQuery($query, $queryParameters);

            return $pdoStatement->fetchAll(\PDO::FETCH_NUM);
        } catch (\Throwable $exception) {
            $this->writeDbLog(
                message: 'Unable to fetch all numeric query',
                customContext: ['query_parameters' => $queryParameters],
                query: $query,
                previous: $exception,
            );

            throw ConnectionException::fetchAllNumericQueryFailed($exception, $query, $queryParameters);
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
            $pdoStatement = $this->executeSelectQuery($query, $queryParameters);

            return $pdoStatement->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $exception) {
            $this->writeDbLog(
                message: 'Unable to fetch all associative query',
                customContext: ['query_parameters' => $queryParameters],
                query: $query,
                previous: $exception,
            );

            throw ConnectionException::fetchAllAssociativeQueryFailed($exception, $query, $queryParameters);
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
            $pdoStatement = $this->executeSelectQuery($query, $queryParameters);

            return $pdoStatement->fetchAll(\PDO::FETCH_KEY_PAIR);
        } catch (\Throwable $exception) {
            $this->writeDbLog(
                message: 'Unable to fetch all key value query',
                customContext: ['query_parameters' => $queryParameters],
                query: $query,
                previous: $exception,
            );

            throw ConnectionException::fetchAllKeyValueQueryFailed($exception, $query, $queryParameters);
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
            $pdoStatement = $this->executeSelectQuery($query, $queryParameters);
            while (($row = $pdoStatement->fetch(\PDO::FETCH_NUM)) !== false) {
                yield $row;
            }
        } catch (\Throwable $exception) {
            $this->writeDbLog(
                message: 'Unable to iterate numeric query',
                customContext: ['query_parameters' => $queryParameters],
                query: $query,
                previous: $exception,
            );

            throw ConnectionException::iterateNumericQueryFailed($exception, $query, $queryParameters);
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
            $pdoStatement = $this->executeSelectQuery($query, $queryParameters);
            while (($row = $pdoStatement->fetch(\PDO::FETCH_ASSOC)) !== false) {
                yield $row;
            }
        } catch (\Throwable $exception) {
            $this->writeDbLog(
                message: 'Unable to iterate associative query',
                customContext: ['query_parameters' => $queryParameters],
                query: $query,
                previous: $exception,
            );

            throw ConnectionException::iterateAssociativeQueryFailed($exception, $query, $queryParameters);
        }
    }

    /**
     * Prepares and executes an SQL query and returns the result as an iterator over the column values.
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
     *          $result = $db->iterateColumn('SELECT name FROM table WHERE active = :active', $queryParameters);
     *          foreach ($result as $value) {
     *              // $value = 'John'
     *              // $value = 'Jean'
     *          }
     */
    public function iterateColumn(string $query, ?QueryParameters $queryParameters = null): \Traversable
    {
        try {
            $this->validateSelectQuery($query);
            $pdoStatement = $this->executeSelectQuery($query, $queryParameters);
            while (($row = $pdoStatement->fetch(\PDO::FETCH_COLUMN)) !== false) {
                yield $row;
            }
        } catch (\Throwable $exception) {
            $this->writeDbLog(
                message: 'Unable to iterate by column query',
                customContext: ['query_parameters' => $queryParameters],
                query: $query,
                previous: $exception,
            );

            throw ConnectionException::iterateColumnQueryFailed($exception, $query, $queryParameters);
        }
    }

    // ----------------------------------------- TRANSACTIONS -----------------------------------------

    /**
     * Checks whether a transaction is currently active.
     *
     * @return bool TRUE if a transaction is currently active, FALSE otherwise
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
     */
    public function startTransaction(): void
    {
        try {
            $this->beginTransaction();
        } catch (\Throwable $exception) {
            $this->writeDbLog(
                message: 'Unable to start transaction',
                previous: $exception,
            );

            throw ConnectionException::startTransactionFailed($exception);
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
        } catch (\Throwable $exception) {
            $this->writeDbLog(
                message: 'Unable to commit transaction',
                previous: $exception,
            );

            throw ConnectionException::commitTransactionFailed($exception);
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
        } catch (\Throwable $exception) {
            $this->writeDbLog(
                message: 'Unable to rollback transaction',
                previous: $exception,
            );

            throw ConnectionException::rollbackTransactionFailed($exception);
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
        $currentDriverName = "pdo_{$this->getAttribute(\PDO::ATTR_DRIVER_NAME)}";
        if (! in_array($currentDriverName, self::DRIVER_ALLOWED_UNBUFFERED_QUERY, true)) {
            $this->writeDbLog(
                message: 'Unbuffered queries are not allowed with this driver',
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
            $this->writeDbLog(message: 'Error while starting an unbuffered query');

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
     */
    public function stopUnbufferedQuery(): void
    {
        if (! $this->isUnbufferedQueryActive()) {
            $this->writeDbLog(
                message: 'Error while stopping an unbuffered query, no unbuffered query is currently active'
            );

            throw ConnectionException::stopUnbufferedQueryFailed(
                'Error while stopping an unbuffered query, no unbuffered query is currently active'
            );
        }
        if (! $this->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true)) {
            $this->writeDbLog(message: 'Error while stopping an unbuffered query');

            throw ConnectionException::stopUnbufferedQueryFailed('Error while stopping an unbuffered query');
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
        } catch (\Throwable $exception) {
            $this->writeDbLog(
                message: "Error while closing the \PDOStatement cursor: {$exception->getMessage()}",
                query: $pdoStatement->queryString,
                previous: $exception,
            );

            throw ConnectionException::closeQueryFailed($exception, $pdoStatement->queryString);
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
     *
     * @throws ConnectionException
     * @return \PDOStatement
     */
    private function executeSelectQuery(
        string $query,
        ?QueryParameters $queryParameters = null
    ): \PDOStatement {
        try {
            $this->validateSelectQuery($query);
            $pdoStatement = $this->prepare($query);

            if (! is_null($queryParameters) && ! $queryParameters->isEmpty()) {
                foreach ($queryParameters->getIterator() as $queryParameter) {
                    $pdoStatement->bindValue(
                        $queryParameter->getName(),
                        $queryParameter->getValue(),
                        ($queryParameter->getType() !== null)
                            ? PdoParameterTypeTransformer::transformFromQueryParameterType(
                            $queryParameter->getType()
                        ) : \PDO::PARAM_STR
                    );
                }
            }

            $pdoStatement->execute();

            return $pdoStatement;
        } catch (\Throwable $exception) {
            $this->writeDbLog(
                message: 'Error while executing the select query',
                customContext: ['query_parameters' => $queryParameters],
                query: $query,
                previous: $exception,
            );

            throw ConnectionException::selectQueryFailed(
                previous: $exception,
                query: $query,
                queryParameters: $queryParameters
            );
        }
    }

    /**
     * Write SQL errors messages
     *
     * @param string $message
     * @param array<string,mixed> $customContext
     * @param string $query
     * @param \Throwable|null $previous
     */
    protected function writeDbLog(
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
                'pdo_error_info' => $previous->errorInfo,
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
            ['default' => $defaultContext],
            ['custom' => $customContext],
            ['exception' => $dbExceptionContext]
        );

        Logger::create()->critical(
            "[DatabaseConnection] {$message}",
            $context
        );
    }
}
