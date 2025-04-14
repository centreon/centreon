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

namespace Adaptation\Database\Connection\Adapter\Dbal;

use Adaptation\Database\Connection\Adapter\Dbal\Transformer\DbalParametersTransformer;
use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\ConnectionInterface;
use Adaptation\Database\Connection\Exception\ConnectionException;
use Adaptation\Database\Connection\Model\ConnectionConfig;
use Adaptation\Database\Connection\Trait\ConnectionTrait;
use Centreon\Domain\Log\Logger;
use Core\Common\Domain\Exception\UnexpectedValueException;
use Core\Common\Infrastructure\ExceptionLogger;
use Doctrine\DBAL\Connection as DoctrineDbalConnection;
use Doctrine\DBAL\DriverManager as DoctrineDbalDriverManager;
use Psr\Log\LogLevel;

/**
 * Class
 *
 * @class   DbalConnectionAdapter
 * @package Adaptation\Database\Adapter\Dbal
 * @see     DoctrineDbalConnection
 */
final class DbalConnectionAdapter implements ConnectionInterface
{
    use ConnectionTrait;

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
     * @param ConnectionConfig $connectionConfig
     */
    private function __construct(
        private readonly DoctrineDbalConnection $dbalConnection,
        private readonly ConnectionConfig $connectionConfig
    ) {}

    /**
     * Factory
     *
     * @param ConnectionConfig $connectionConfig
     *
     * @throws ConnectionException
     * @return DbalConnectionAdapter
     */
    public static function createFromConfig(ConnectionConfig $connectionConfig): ConnectionInterface
    {
        $dbalConnectionConfig = [
            'dbname' => $connectionConfig->getDatabaseNameConfiguration(),
            'user' => $connectionConfig->getUser(),
            'password' => $connectionConfig->getPassword(),
            'host' => $connectionConfig->getHost(),
            'port' => $connectionConfig->getPort(),
            'charset' => $connectionConfig->getCharset(),
            'driver' => $connectionConfig->getDriver()->value,
        ];

        try {
            $dbalConnection = DoctrineDbalDriverManager::getConnection($dbalConnectionConfig);
            $dbalConnectionAdapter = new self($dbalConnection, $connectionConfig);
            if (! $dbalConnectionAdapter->isConnected()) {
                throw new UnexpectedValueException('The connection is not established.');
            }

            return $dbalConnectionAdapter;
        } catch (\Throwable $exception) {
            throw ConnectionException::connectionFailed($exception);
        }
    }

    /**
     * @return ConnectionConfig
     */
    public function getConnectionConfig(): ConnectionConfig
    {
        return $this->connectionConfig;
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
     * @throws ConnectionException
     * @return object|resource
     */
    public function getNativeConnection(): mixed
    {
        try {
            return $this->dbalConnection->getNativeConnection();
        } catch (\Throwable $exception) {
            $this->writeDbLog(
                message: 'Unable to get native connection',
                previous: $exception
            );

            throw ConnectionException::getNativeConnectionFailed($exception);
        }
    }

    /**
     * Returns the ID of the last inserted row.
     * If the underlying driver does not support identity columns, an exception is thrown.
     *
     * @throws ConnectionException
     * @return string
     */
    public function getLastInsertId(): string
    {
        try {
            return (string) $this->dbalConnection->lastInsertId();
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
            return ! empty($this->dbalConnection->getServerVersion());
        } catch (\Throwable $exception) {
            $this->writeDbLog(
                message: 'Unable to check if the connection is established',
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
        return $this->dbalConnection->quote($value);
    }

    // ----------------------------------------- CRUD METHODS -----------------------------------------

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

            if ($queryParameters === null) {
                return (int) $this->dbalConnection->executeStatement($query);
            }

            [$params, $types] = DbalParametersTransformer::transformFromQueryParameters($queryParameters);

            return (int) $this->dbalConnection->executeStatement($query, $params, $types);
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

            if ($queryParameters === null) {
                return $this->dbalConnection->fetchNumeric($query);
            }

            [$params, $types] = DbalParametersTransformer::transformFromQueryParameters($queryParameters);

            return $this->dbalConnection->fetchNumeric($query, $params, $types);
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

            if ($queryParameters === null) {
                return $this->dbalConnection->fetchAssociative($query);
            }

            [$params, $types] = DbalParametersTransformer::transformFromQueryParameters($queryParameters);

            return $this->dbalConnection->fetchAssociative($query, $params, $types);
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

            if ($queryParameters === null) {
                return $this->dbalConnection->fetchOne($query);
            }

            [$params, $types] = DbalParametersTransformer::transformFromQueryParameters($queryParameters);

            return $this->dbalConnection->fetchOne($query, $params, $types);
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

            if ($queryParameters === null) {
                return $this->dbalConnection->fetchFirstColumn($query);
            }

            [$params, $types] = DbalParametersTransformer::transformFromQueryParameters($queryParameters);

            return $this->dbalConnection->fetchFirstColumn($query, $params, $types);
        } catch (\Throwable $exception) {
            $this->writeDbLog(
                message: 'Unable to fetch first column query',
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

            if ($queryParameters === null) {
                return $this->dbalConnection->fetchAllNumeric($query);
            }

            [$params, $types] = DbalParametersTransformer::transformFromQueryParameters($queryParameters);

            return $this->dbalConnection->fetchAllNumeric($query, $params, $types);
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

            if ($queryParameters === null) {
                return $this->dbalConnection->fetchAllAssociative($query);
            }

            [$params, $types] = DbalParametersTransformer::transformFromQueryParameters($queryParameters);

            return $this->dbalConnection->fetchAllAssociative($query, $params, $types);
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

            if ($queryParameters === null) {
                return $this->dbalConnection->fetchAllKeyValue($query);
            }

            [$params, $types] = DbalParametersTransformer::transformFromQueryParameters($queryParameters);

            return $this->dbalConnection->fetchAllKeyValue($query, $params, $types);
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

            if ($queryParameters === null) {
                return $this->dbalConnection->iterateNumeric($query);
            }

            [$params, $types] = DbalParametersTransformer::transformFromQueryParameters($queryParameters);

            return $this->dbalConnection->iterateNumeric($query, $params, $types);
        } catch (\Throwable $exception) {
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

            if ($queryParameters === null) {
                return $this->dbalConnection->iterateAssociative($query);
            }

            [$params, $types] = DbalParametersTransformer::transformFromQueryParameters($queryParameters);

            return $this->dbalConnection->iterateAssociative($query, $params, $types);
        } catch (\Throwable $exception) {
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
     *          $result = $db->iterateFirstColumn('SELECT name FROM table WHERE active = :active', $queryParameters);
     *          foreach ($result as $value) {
     *              // $value = 'John'
     *              // $value = 'Jean'
     *          }
     */
    public function iterateColumn(string $query, ?QueryParameters $queryParameters = null): \Traversable
    {
        try {
            $this->validateSelectQuery($query);

            if ($queryParameters === null) {
                return $this->dbalConnection->iterateColumn($query);
            }

            [$params, $types] = DbalParametersTransformer::transformFromQueryParameters($queryParameters);

            return $this->dbalConnection->iterateColumn($query, $params, $types);
        } catch (\Throwable $exception) {
            throw ConnectionException::iterateColumnQueryFailed($exception, $query, $queryParameters);
        }
    }

    // ----------------------------------------- TRANSACTIONS -----------------------------------------

    /**
     * Returns the current auto-commit mode for this connection.
     *
     * @return bool true if auto-commit mode is currently enabled for this connection, false otherwise
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
     * @param bool $autoCommit true to enable auto-commit mode; false to disable it
     *
     * @throws ConnectionException
     * @return void
     */
    public function setAutoCommit(bool $autoCommit): void
    {
        try {
            $this->dbalConnection->setAutoCommit($autoCommit);
        } catch (\Throwable $exception) {
            $this->writeDbLog(
                message: 'Unable to set auto commit',
                customContext: ['auto_commit' => $autoCommit],
                previous: $exception,
            );

            throw ConnectionException::setAutoCommitFailed($exception);
        }
    }

    /**
     * Checks whether a transaction is currently active.
     *
     * @return bool TRUE if a transaction is currently active, FALSE otherwise
     */
    public function isTransactionActive(): bool
    {
        return $this->dbalConnection->isTransactionActive();
    }

    /**
     * Opens a new transaction. This must be closed by calling one of the following methods:
     * {@see commitTransaction} or {@see rollBackTransaction}
     *
     * Note that it is possible to create nested transactions, but that data will only be written to the database when
     * the level 1 transaction is committed.
     *
     * Similarly, if a rollback occurs in a nested transaction, the level 1 transaction will also be rolled back and
     * no data will be updated.
     *
     * @throws ConnectionException
     * @return void
     */
    public function startTransaction(): void
    {
        try {
            // we check if the save points mode is available before run a nested transaction
            if ($this->isTransactionActive()) {
                if (! $this->dbalConnection->getDatabasePlatform()->supportsSavepoints()) {
                    throw new ConnectionException(
                        'Start nested transaction failed',
                        ConnectionException::ERROR_CODE_DATABASE_TRANSACTION
                    );
                }
            }
            $this->dbalConnection->beginTransaction();
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
            $this->dbalConnection->commit();

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
            $this->dbalConnection->rollBack();

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
     * @return bool
     */
    public function allowUnbufferedQuery(): bool
    {
        $nativeConnection = $this->getNativeConnection();
        if (is_object($nativeConnection)) {
            $driverName = match ($nativeConnection::class) {
                \PDO::class => "pdo_{$nativeConnection->getAttribute(\PDO::ATTR_DRIVER_NAME)}",
                default => '',
            };
            if (empty($driverName) || ! in_array($driverName, self::DRIVER_ALLOWED_UNBUFFERED_QUERY, true)) {
                $this->writeDbLog(
                    message: 'Unbuffered queries are not allowed with this driver',
                    customContext: ['driver_name' => $driverName]
                );

                throw ConnectionException::allowUnbufferedQueryFailed($nativeConnection::class, $driverName);
            }

            return true;
        }

        return false;
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
        $nativeConnection = $this->getNativeConnection();
        if ($nativeConnection instanceof \PDO) {
            if (! $nativeConnection->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false)) {
                $this->writeDbLog(message: 'Error while starting an unbuffered query');

                throw ConnectionException::startUnbufferedQueryFailed();
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
     * @throws ConnectionException
     * @return void
     */
    public function stopUnbufferedQuery(): void
    {
        $nativeConnection = $this->getNativeConnection();
        if (! $this->isUnbufferedQueryActive()) {
            $this->writeDbLog(
                message: 'Error while stopping an unbuffered query, no unbuffered query is currently active'
            );

            throw ConnectionException::stopUnbufferedQueryFailed(
                'Unbuffered query not active'
            );
        }
        if ($nativeConnection instanceof \PDO) {
            if (! $nativeConnection->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true)) {
                $this->writeDbLog(message: 'Error while stopping an unbuffered query');

                throw ConnectionException::stopUnbufferedQueryFailed(
                    'Unbuffered query failed'
                );
            }
        }
        $this->isBufferedQueryActive = true;
    }

    // ----------------------------------------- PRIVATE METHODS -----------------------------------------

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
        $context = [
            'database_name' => $this->connectionConfig->getDatabaseNameConfiguration(),
            'database_connector' => self::class,
            'query' => $query,
        ];

        if (! is_null($previous)) {
            ExceptionLogger::create()->log($previous, $context, LogLevel::CRITICAL);
        } else {
            Logger::create()->critical($message, $context);
        }
    }
}
