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

use Adaptation\Database\Connection\Adapter\Pdo\Transformer\PdoParameterTypeTransformer;
use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\ConnectionInterface;
use Adaptation\Database\Connection\Exception\ConnectionException;
use Adaptation\Database\Connection\Model\ConnectionConfig;
use Adaptation\Database\Connection\Trait\ConnectionTrait;

// file centreon.config.php may not exist in test environment
$configFile = realpath(__DIR__ . "/../../config/centreon.config.php");
if ($configFile !== false) {
    require_once $configFile;
}

require_once __DIR__ . '/centreonDBStatement.class.php';
require_once __DIR__ . '/centreonLog.class.php';

/**
 * Class
 *
 * @class       CentreonDB
 * @description used to manage DB connection
 */
class CentreonDB extends PDO implements ConnectionInterface
{
    use ConnectionTrait;

    public const DRIVER_PDO_MYSQL = "mysql";
    public const LABEL_DB_CONFIGURATION = 'centreon';
    public const LABEL_DB_REALTIME = 'centstorage';

    /** @var int */
    private const RETRY = 3;

    /** @var array<string,CentreonDB> */
    private static $instance = [];

    /** @var int */
    protected $retry;

    /** @var array<int, array<int, mixed>|int|bool|string> */
    protected $options;

    /** @var string */
    protected $centreon_path;

    /** @var CentreonLog */
    protected $logger;

    /*
     * Statistics
     */

    /** @var int */
    protected $requestExecuted;

    /** @var int */
    protected $requestSuccessful;

    /** @var int */
    protected $lineRead;

    /** @var int */
    private $queryNumber;

    /** @var int */
    private $successQueryNumber;

    /** @var ConnectionConfig */
    private ConnectionConfig $connectionConfig;

    /**
     * By default, the queries are buffered.
     *
     * @var bool
     */
    private bool $isBufferedQueryActive = true;

    /**
     * CentreonDB constructor.
     *
     * @param string $dbLabel LABEL_DB_* constants
     * @param int $retry
     * @param ConnectionConfig|null $connectionConfig
     *
     * @throws \Exception
     */
    public function __construct(
        string $dbLabel = self::LABEL_DB_CONFIGURATION,
        int $retry = self::RETRY,
        ?ConnectionConfig $connectionConfig = null
    ) {
        if (is_null($connectionConfig)) {
            $this->connectionConfig = new ConnectionConfig(
                host: $dbLabel === self::LABEL_DB_CONFIGURATION ? hostCentreon : hostCentstorage,
                user: user,
                password: password,
                databaseName: $dbLabel === self::LABEL_DB_CONFIGURATION ? db : dbcstg,
                databaseNameStorage: dbcstg,
                port: port ?? 3306
            );
        } else {
            $this->connectionConfig = $connectionConfig;
        }

        $this->logger = CentreonLog::create();

        $this->centreon_path = _CENTREON_PATH_;
        $this->retry = $retry;

        $this->options = [
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_STATEMENT_CLASS => [
                CentreonDBStatement::class,
                [$this->logger],
            ],
            \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$this->connectionConfig->getCharset()}",
            \PDO::MYSQL_ATTR_LOCAL_INFILE => true,
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
        ];

        /*
         * Init request statistics
         */
        $this->requestExecuted = 0;
        $this->requestSuccessful = 0;
        $this->lineRead = 0;

        try {
            parent::__construct(
                $this->connectionConfig->getMysqlDsn(),
                $this->connectionConfig->getUser(),
                $this->connectionConfig->getPassword(),
                $this->options
            );
        } catch (\Exception $e) {
            $this->writeDbLog(
                message: "Unable to connect to database : {$e->getMessage()}",
                customContext: ['dsn_mysql' => $this->connectionConfig->getMysqlDsn()],
                previous: $e,
            );
            if (PHP_SAPI !== "cli") {
                $this->displayConnectionErrorPage(
                    $e->getCode() === 2002 ? "Unable to connect to database" : $e->getMessage()
                );
            } else {
                throw new \Exception($e->getMessage());
            }
        }
    }

    /**
     * Factory
     *
     * @param ConnectionConfig $connectionConfig
     *
     * @throws Exception
     * @return CentreonDB
     */
    public static function connectToCentreonDb(ConnectionConfig $connectionConfig): CentreonDB
    {
        return new self(dbLabel: self::LABEL_DB_CONFIGURATION, connectionConfig: $connectionConfig);
    }

    /**
     * Factory
     *
     * @param ConnectionConfig $connectionConfig
     *
     * @throws Exception
     * @return CentreonDB
     */
    public static function connectToCentreonStorageDb(ConnectionConfig $connectionConfig): CentreonDB
    {
        return new self(dbLabel: self::LABEL_DB_REALTIME, connectionConfig: $connectionConfig);
    }

    /**
     * Factory
     *
     * @param ConnectionConfig $connectionConfig
     *
     * @throws ConnectionException
     * @return ConnectionInterface
     */
    public static function createFromConfig(ConnectionConfig $connectionConfig): ConnectionInterface
    {
        try {
            return new static(connectionConfig: $connectionConfig);
        } catch (Exception $e) {
            throw ConnectionException::connectionFailed($e);
        }
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
                message: 'Unable to check if the connection is established',
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

            // here we don't want to use CentreonDbStatement, instead used PDOStatement
            $this->setAttribute(\PDO::ATTR_STATEMENT_CLASS, [\PDOStatement::class]);

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
        } finally {
            // here we restart CentreonDbStatement for the other requests
            $this->setAttribute(\PDO::ATTR_STATEMENT_CLASS, [
                CentreonDBStatement::class,
                [$this->logger],
            ]);
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

    // --------------------------------------- OTHER METHODS -----------------------------------------

    /**
     * Display error page
     *
     * @param mixed $msg
     */
    protected function displayConnectionErrorPage($msg = null): void
    {
        if (! $msg) {
            $msg = _("Connection failed, please contact your administrator");
        }
        echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
            "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
              <html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en-US" lang="en-US">
                <head>
                <style type="text/css">
                       div.Error{background-color:#fa6f6c;border:1px #AEAEAE solid;width: 500px;}
                       div.Error{border-radius:4px;}
                       div.Error{padding: 15px;}
                       a, div.Error{font-family:"Bitstream Vera Sans", arial, Tahoma, "Sans serif",serif;font-weight: bold;}
                </style>
                <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
                <title>Centreon</title>
              </head>
                <body>
                  <center>
                  <div style="padding-top:150px;padding-bottom:50px;">
                        <img src="./img/centreon.png" alt="Centreon"/><br/>
                  </div>
                  <div class="Error">' . $msg . '</div>
                  <div style="padding: 50px;"><a href="#" onclick="location.reload();">Refresh Here</a></div>
                  </center>
                </body>
              </html>';
        exit;
    }

    /**
     * return database Properties
     *
     * <code>
     * $dataCentreon = getProperties();
     * </code>
     *
     * @return array<mixed> dbsize, numberOfRow, freeSize
     */
    public function getProperties()
    {
        $unitMultiple = 1024 * 1024;

        $info = [
            'version' => null,
            'engine' => null,
            'dbsize' => 0,
            'rows' => 0,
            'datafree' => 0,
            'indexsize' => 0
        ];
        /*
         * Get Version
         */
        if ($res = $this->query("SELECT VERSION() AS mysql_version")) {
            $row = $res->fetch();
            $versionInformation = explode('-', $row['mysql_version']);
            $info["version"] = $versionInformation[0];
            $info["engine"] = $versionInformation[1] ?? 'MySQL';
            if ($dbResult = $this->query(
                "SHOW TABLE STATUS FROM `" . $this->connectionConfig->getDatabaseName() . "`"
            )) {
                while ($data = $dbResult->fetch()) {
                    $info['dbsize'] += $data['Data_length'] + $data['Index_length'];
                    $info['indexsize'] += $data['Index_length'];
                    $info['rows'] += $data['Rows'];
                    $info['datafree'] += $data['Data_free'];
                }
                $dbResult->closeCursor();
            }
            foreach ($info as $key => $value) {
                if ($key != "rows" && $key != "version" && $key != "engine") {
                    $info[$key] = round($value / $unitMultiple, 2);
                }
            }
        }

        return $info;
    }

    /**
     * As 'ALTER TABLE IF NOT EXIST' queries are supported only by mariaDB (no more by mysql),
     * This method check if a column was already added in a previous upgrade script.
     *
     * @param string|null $table - the table on which we'll search the column
     * @param string|null $column - the column name to be checked
     *
     * @return int
     */
    public function isColumnExist(string $table = null, string $column = null): int
    {
        if (! $table || ! $column) {
            return -1;
        }

        $table = HtmlAnalyzer::sanitizeAndRemoveTags($table);
        $column = HtmlAnalyzer::sanitizeAndRemoveTags($column);

        $query = "SELECT COLUMN_NAME
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = :dbName
            AND TABLE_NAME = :tableName
            AND COLUMN_NAME = :columnName";

        $stmt = $this->prepare($query);

        try {
            $stmt->bindValue(':dbName', $this->connectionConfig->getDatabaseName(), PDO::PARAM_STR);
            $stmt->bindValue(':tableName', $table, PDO::PARAM_STR);
            $stmt->bindValue(':columnName', $column, PDO::PARAM_STR);
            $stmt->execute();
            $stmt->fetch();

            if ($stmt->rowCount()) {
                return 1; // column already exist
            }

            return 0; // column to add
        } catch (PDOException $e) {
            $this->writeDbLog(
                message: 'Error while checking if the column exists',
                customContext: [
                    'table' => $table,
                    'column' => $column,
                ],
                query: $stmt->queryString,
                previous: $e
            );

            return -1;
        }
    }

    /**
     * Indicates whether an index exists or not.
     *
     * @param string $table
     * @param string $indexName
     *
     * @throws PDOException
     * @return bool
     */
    public function isIndexExists(string $table, string $indexName): bool
    {
        $statement = $this->prepare(
            <<<'SQL'
            SELECT 1
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = :db_name
              AND TABLE_NAME = :table_name
              AND INDEX_NAME = :index_name;
            SQL
        );
        $statement->bindValue(':db_name', $this->connectionConfig->getDatabaseName());
        $statement->bindValue(':table_name', $table);
        $statement->bindValue(':index_name', $indexName);

        $statement->execute();

        return ! empty($statement->fetch(PDO::FETCH_ASSOC));
    }

    /**
     * Indicates whether a constraint on table exists or not.
     *
     * @param string $table
     * @param string $constraintName
     *
     * @throws PDOException
     * @return bool
     */
    public function isConstraintExists(string $table, string $constraintName): bool
    {
        $statement = $this->prepare(
            <<<'SQL'
            SELECT 1
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = :db_name
              AND TABLE_NAME = :table_name
              AND CONSTRAINT_NAME = :constraint_name;
            SQL
        );
        $statement->bindValue(':db_name', $this->connectionConfig->getDatabaseName());
        $statement->bindValue(':table_name', $table);
        $statement->bindValue(':constraint_name', $constraintName);

        $statement->execute();

        return ! empty($statement->fetch(PDO::FETCH_ASSOC));
    }

    /**
     * This method returns a column type from a given table and column.
     *
     * @param string $tableName
     * @param string $columnName
     *
     * @return string
     */
    public function getColumnType(string $tableName, string $columnName): string
    {
        $tableName = HtmlAnalyzer::sanitizeAndRemoveTags($tableName);
        $columnName = HtmlAnalyzer::sanitizeAndRemoveTags($columnName);

        $query = 'SELECT COLUMN_TYPE
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = :dbName
            AND TABLE_NAME = :tableName
            AND COLUMN_NAME = :columnName';

        $stmt = $this->prepare($query);

        try {
            $stmt->bindValue(':dbName', $this->connectionConfig->getDatabaseName(), PDO::PARAM_STR);
            $stmt->bindValue(':tableName', $tableName, PDO::PARAM_STR);
            $stmt->bindValue(':columnName', $columnName, PDO::PARAM_STR);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if (! empty($result)) {
                return $result['COLUMN_TYPE'];
            }

            throw new PDOException("Unable to get column type");
        } catch (PDOException $e) {
            $this->writeDbLog(
                message: 'Error while checking if the column exists',
                customContext: [
                    'table' => $tableName,
                    'column' => $columnName,
                ],
                query: $stmt->queryString,
                previous: $e
            );

            return '';
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

            // here we don't want to use CentreonDbStatement, instead used PDOStatement
            $this->setAttribute(\PDO::ATTR_STATEMENT_CLASS, [\PDOStatement::class]);

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
        } finally {
            // here we restart CentreonDbStatement for the other requests
            $this->setAttribute(\PDO::ATTR_STATEMENT_CLASS, [
                CentreonDBStatement::class,
                [$this->logger],
            ]);
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
    private function writeDbLog(
        string $message,
        array $customContext = [],
        string $query = '',
        ?\Throwable $previous = null
    ): void {
        // prepare context of the database exception
        if ($previous instanceof CentreonDbException) {
            $dbExceptionContext = $previous->getOptions();
        } elseif ($previous instanceof ConnectionException) {
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

        $this->logger->critical(
            CentreonLog::TYPE_SQL,
            "[CentreonDb] {$message}",
            $context,
            $previous
        );
    }

    //******************************************** DEPRECATED METHODS ***********************************************//

    /**
     * Factory for singleton
     *
     * @param string $name The name of centreon datasource
     *
     * @throws Exception
     * @return CentreonDB
     *
     * @deprecated
     */
    public static function factory(string $name = self::LABEL_DB_CONFIGURATION)
    {
        if (! in_array($name, [self::LABEL_DB_CONFIGURATION, self::LABEL_DB_REALTIME])) {
            throw new Exception("The datasource isn't defined in configuration file.");
        }
        if (! isset(self::$instance[$name])) {
            self::$instance[$name] = new CentreonDB($name);
        }

        return self::$instance[$name];
    }

    /**
     * @param string $query
     * @param array $options
     *
     * @throws CentreonDbException
     * @return \PDOStatement|bool
     *
     * @deprecated Use {@see ConnectionInterface} methods instead
     * @see        ConnectionInterface
     */
    public function prepareQuery(string $query, array $options = []): \PDOStatement|bool
    {
        try {
            if (empty($query)) {
                throw new CentreonDbException(
                    'Error while preparing query, query must not be empty',
                    ['query' => $query]
                );
            }

            // here we don't want to use CentreonDbStatement, instead used PDOStatement
            $this->setAttribute(\PDO::ATTR_STATEMENT_CLASS, [\PDOStatement::class]);

            return parent::prepare($query, $options);
        } catch (\Throwable $e) {
            $this->writeDbLog(
                message: "Error while preparing query",
                query: $query,
                previous: $e,
            );
            throw new CentreonDbException(
                message: "Error while preparing the query: {$e->getMessage()}",
                options: ['query' => $query],
                previous: $e
            );
        } finally {
            // here we restart CentreonDbStatement for the other requests
            $this->setAttribute(\PDO::ATTR_STATEMENT_CLASS, [
                CentreonDBStatement::class,
                [$this->logger],
            ]);
        }
    }

    /**
     *  Allowed types : PDO::PARAM_STR, PDO::PARAM_BOOL, PDO::PARAM_INT, PDO::PARAM_NULL
     *
     * @param \PDOStatement $pdoStatement
     * @param int|string $paramName
     * @param mixed $value
     * @param int $type
     *
     * @throws CentreonDbException
     * @return bool
     *
     * @deprecated Use {@see ConnectionInterface} methods instead
     * @see        ConnectionInterface
     */
    public function makeBindValue(
        \PDOStatement $pdoStatement,
        int|string $paramName,
        mixed $value,
        int $type = \PDO::PARAM_STR
    ): bool {
        try {
            if (empty($paramName)) {
                throw new CentreonDbException(
                    "paramName must to be filled, empty given",
                    ['param_name' => $paramName]
                );
            }
            if (
                ! in_array(
                    $type,
                    [\PDO::PARAM_STR, \PDO::PARAM_BOOL, \PDO::PARAM_INT, \PDO::PARAM_NULL],
                    true
                )
            ) {
                throw new CentreonDbException(
                    "Error for the param type, it's not an integer or a value of PDO::PARAM_*",
                    ['param_name' => $paramName]
                );
            }

            return $pdoStatement->bindValue($paramName, $value, $type);
        } catch (\Throwable $e) {
            $this->writeDbLog(
                message: "Error while binding value for param {$paramName} : {$e->getMessage()}",
                customContext: [
                    'param_name' => $paramName,
                    'param_value' => $value,
                    'param_type' => $type
                ],
                query: $pdoStatement->queryString,
                previous: $e,
            );
            throw new CentreonDbException(
                message: "Error while binding value for param {$paramName} : {$e->getMessage()}",
                options: [
                    'query' => $pdoStatement->queryString,
                    'param_name' => $paramName,
                    'param_value' => $value,
                    'param_type' => $type
                ],
                previous: $e
            );
        }
    }

    /**
     * @param \PDOStatement $pdoStatement
     * @param int|string $paramName
     * @param mixed $var
     * @param int $type
     * @param int $maxLength
     *
     * @throws CentreonDbException
     * @return bool
     *
     * @deprecated Use {@see ConnectionInterface} methods instead
     * @see        ConnectionInterface
     */
    public function makeBindParam(
        \PDOStatement $pdoStatement,
        int|string $paramName,
        mixed &$var,
        int $type = \PDO::PARAM_STR,
        int $maxLength = 0
    ): bool {
        try {
            if (empty($paramName)) {
                throw new CentreonDbException(
                    "paramName must to be filled, empty given",
                    ['param_name' => $paramName]
                );
            }
            if (
                ! in_array(
                    $type,
                    [\PDO::PARAM_STR, \PDO::PARAM_BOOL, \PDO::PARAM_INT, \PDO::PARAM_NULL],
                    true
                )
            ) {
                throw new CentreonDbException(
                    "Error for the param type, it's not an integer or a value of PDO::PARAM_*",
                    ['param_name' => $paramName]
                );
            }

            return $pdoStatement->bindParam($paramName, $var, $type, $maxLength);
        } catch (\Throwable $e) {
            $this->writeDbLog(
                message: "Error while binding param {$paramName} : {$e->getMessage()}",
                customContext: [
                    'param_name' => $paramName,
                    'param_var' => $var,
                    'param_type' => $type,
                    'param_max_length' => $maxLength
                ],
                query: $pdoStatement->queryString,
                previous: $e,
            );
            throw new CentreonDbException(
                message: "Error while binding param {$paramName} : {$e->getMessage()}",
                options: [
                    'query' => $pdoStatement->queryString,
                    'param_name' => $paramName,
                    'param_var' => $var,
                    'param_type' => $type,
                    'param_max_length' => $maxLength
                ],
                previous: $e
            );
        }
    }

    /**
     * Prefer to use fetchNumeric() or fetchAssociative() instead of this method.
     *
     * @param \PDOStatement $pdoStatement
     *
     * @throws CentreonDbException
     *
     * @return mixed
     *
     * @deprecated Use {@see ConnectionInterface} methods instead
     * @see        ConnectionInterface
     */
    public function fetch(\PDOStatement $pdoStatement): mixed
    {
        try {
            return $pdoStatement->fetch();
        } catch (\Throwable $e) {
            try {
                $this->closeQuery($pdoStatement);
            } catch (ConnectionException $e) {
                $this->writeDbLog(
                    message: "Error while closing the query : {$e->getMessage()}",
                    query: $pdoStatement->queryString,
                    previous: $e,
                );
                throw new CentreonDbException(
                    message: "Error while closing the query : {$e->getMessage()}",
                    options: ['query' => $pdoStatement->queryString],
                    previous: $e
                );
            }
            $this->writeDbLog(
                message: "Error while fetching the row : {$e->getMessage()}",
                query: $pdoStatement->queryString,
                previous: $e,
            );
            throw new CentreonDbException(
                message: "Error while fetching the row : {$e->getMessage()}",
                options: ['query' => $pdoStatement->queryString],
                previous: $e
            );
        }
    }

    /**
     * Prefer to use fetchAllNumeric() or fetchAllAssociative() instead of this method.
     *
     * @param \PDOStatement $pdoStatement
     *
     * @throws CentreonDbException
     *
     * @return array
     *
     * @deprecated Use {@see ConnectionInterface} methods instead
     * @see        ConnectionInterface
     */
    public function fetchAll(\PDOStatement $pdoStatement): array
    {
        try {
            return $pdoStatement->fetchAll();
        } catch (\Throwable $e) {
            $this->writeDbLog(
                message: "Error while fetching all the rows : {$e->getMessage()}",
                query: $pdoStatement->queryString,
                previous: $e,
            );
            throw new CentreonDbException(
                message: "Error while fetching all the rows : {$e->getMessage()}",
                options: ['query' => $pdoStatement->queryString],
                previous: $e
            );
        } finally {
            try {
                $this->closeQuery($pdoStatement);
            } catch (ConnectionException $e) {
                $this->writeDbLog(
                    message: "Error while closing the query : {$e->getMessage()}",
                    query: $pdoStatement->queryString,
                    previous: $e,
                );
                throw new CentreonDbException(
                    message: "Error while closing the query : {$e->getMessage()}",
                    options: ['query' => $pdoStatement->queryString],
                    previous: $e
                );
            }
        }
    }

    /**
     * @param \PDOStatement $pdoStatement
     * @param array|null $bindParams
     *
     * @throws CentreonDbException
     * @return bool (no signature for this method because of a bug with tests with \Centreon\Test\Mock\CentreonDb::execute())
     *
     * @deprecated Use {@see ConnectionInterface} methods instead
     * @see        ConnectionInterface
     */
    public function execute(\PDOStatement $pdoStatement, ?array $bindParams = null)
    {
        try {
            if ($bindParams === []) {
                throw new CentreonDbException(
                    "To execute the query, bindParams must to be an array filled or null, empty array given",
                    ['bind_params' => $bindParams]
                );
            }

            return $pdoStatement->execute($bindParams);
        } catch (\Throwable $e) {
            $this->writeDbLog(
                message: "Error while executing the query : {$e->getMessage()}",
                customContext: ['bind_params' => $bindParams],
                query: $pdoStatement->queryString,
                previous: $e,
            );
            throw new CentreonDbException(
                message: "Error while executing the query : {$e->getMessage()}",
                options: ['query' => $pdoStatement->queryString, 'bind_params' => $bindParams],
                previous: $e
            );
        }
    }

    /**
     * @param string $string
     * @param int $type
     *
     * @throws CentreonDbException
     * @return string
     *
     * @deprecated Use {@see quoteString()} instead
     */
    public function escapeString(string $string, int $type = PDO::PARAM_STR): string
    {
        $quotedString = parent::quote($string, $type);
        if ($quotedString === false) {
            $this->writeDbLog(
                message: "Error while quoting the string",
                customContext: ['string' => $string],
            );
            throw new CentreonDbException("Error while quoting the string: {$string}");
        }

        return $quotedString;
    }

    /**
     * Without prepared query, only for SELECT queries.
     *
     * Not used for DDL queries.
     *
     * This method does not support PDO binding types.
     *
     * @param       $query
     * @param int $fetchMode
     * @param array $fetchModeArgs
     *
     * @throws CentreonDbException
     * @return PDOStatement|bool
     *
     * @deprecated Use {@see ConnectionInterface} methods instead
     * @see        ConnectionInterface
     */
    public function executeQuery(
        $query,
        int $fetchMode = PDO::FETCH_ASSOC,
        array $fetchModeArgs = []
    ): PDOStatement|false {
        try {
            if (empty($query)) {
                throw new CentreonDbException(
                    'Error while executing query, query must not be empty',
                    [
                        'query' => $query,
                    ]
                );
            }

            // here we don't want to use CentreonDbStatement, instead used PDOStatement
            $this->setAttribute(PDO::ATTR_STATEMENT_CLASS, [PDOStatement::class]);
            $stmt = $this->prepare($query);
            $stmt->execute();
            $stmt->setFetchMode($fetchMode, ...$fetchModeArgs);

            return $stmt;
        } catch (\Throwable $e) {
            $this->writeDbLog(
                message: "Error while executing the query: {$e->getMessage()}",
                customContext: [
                    'fetch_mode' => $fetchMode,
                    'fetch_mode_args' => $fetchModeArgs,
                ],
                query: $query,
                previous: $e,
            );
            throw new CentreonDbException(
                message: "Error while executing the query: {$e->getMessage()}",
                options: [
                    'query' => $query,
                    'fetch_mode' => $fetchMode,
                    'fetch_mode_args' => $fetchModeArgs,
                ],
                previous: $e
            );
        } finally {
            // here we restart CentreonDbStatement for the other requests
            $this->setAttribute(PDO::ATTR_STATEMENT_CLASS, [
                CentreonDBStatement::class,
                [$this->logger],
            ]);
        }
    }


    /**
     * When $withParamType is true, $bindParams must have an array as value like ['value', PDO::PARAM_*]
     * Allowed types : PDO::PARAM_STR, PDO::PARAM_BOOL, PDO::PARAM_INT, PDO::PARAM_NULL
     *
     * @param PDOStatement $pdoStatement
     * @param array $bindParams
     * @param bool $withParamType
     *
     * @throws CentreonDbException
     *
     * @return bool
     *
     * @deprecated Use {@see ConnectionInterface} methods instead
     * @see        ConnectionInterface
     */
    public function executePreparedQuery(
        PDOStatement $pdoStatement,
        array $bindParams,
        bool $withParamType = false
    ): bool {
        try {
            // here we don't want to use CentreonDbStatement, instead used PDOStatement
            $this->setAttribute(PDO::ATTR_STATEMENT_CLASS, [PDOStatement::class]);

            if ($bindParams === []) {
                throw new CentreonDbException(
                    "Binding parameters are empty",
                    ['bind_params' => $bindParams]
                );
            }

            if (! $withParamType) {
                return $pdoStatement->execute($bindParams);
            }

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
                        throw new CentreonDbException(
                            "Error for the param type, it's not an integer or a value of PDO::PARAM_*",
                            ['bind_param' => $bindParam]
                        );
                    }
                    $this->makeBindValue($pdoStatement, $paramName, $paramValue, $paramType);
                } else {
                    throw new CentreonDbException(
                        "Incorrect format for bindParam values, it must to be an array like ['value', PDO::PARAM_*]",
                        ['bind_params' => $bindParams]
                    );
                }
            }

            return $pdoStatement->execute();
        } catch (\Throwable $e) {
            $message = "Error while executing the prepared query: {$e->getMessage()}";
            $this->writeDbLog(
                message: $message,
                customContext: ['bind_params' => $bindParams, 'with_param_type' => $withParamType],
                query: $pdoStatement->queryString,
                previous: $e
            );
            throw new CentreonDbException(
                message: $message,
                options: [
                    'query' => $pdoStatement->queryString,
                    'bind_params' => $bindParams,
                    'with_param_type' => $withParamType
                ],
                previous: $e
            );
        } finally {
            // here we restart CentreonDbStatement for the other requests
            $this->setAttribute(PDO::ATTR_STATEMENT_CLASS, [
                CentreonDBStatement::class,
                [$this->logger],
            ]);
        }
    }

    /**
     * @param \PDOStatement $pdoStatement
     * @param int $column
     *
     * @throws CentreonDbException
     *
     * @return array|bool
     *
     * @deprecated Instead use {@see CentreonDB::fetchFirstColumn()}
     * @see        CentreonDB::fetchFirstColumn()
     */
    public function fetchColumn(\PDOStatement $pdoStatement, int $column = 0): mixed
    {
        try {
            return $pdoStatement->fetchColumn($column);
        } catch (\Throwable $e) {
            try {
                $this->closeQuery($pdoStatement);
            } catch (ConnectionException $e) {
                $this->writeDbLog(
                    message: "Error while closing the query",
                    previous: $e,
                );
                throw new CentreonDbException($message, previous: $e);
            }
            $message = "Error while fetching all the rows by column: {$e->getMessage()}";
            $this->writeDbLog($message, ['column' => $column], query: $pdoStatement->queryString, previous: $e);
            $options = ['query' => $pdoStatement->queryString];
            if ($e instanceof \PDOException) {
                $options['pdo_error_code'] = $e->getCode();
                $options['pdo_error_infos'] = $e->errorInfo;
            }
            throw new CentreonDbException($message, $options, $e);
        }
    }

    /**
     * Without prepared query
     *
     * @param string $query
     * @param int $column
     *
     * @throws CentreonDbException
     *
     * @return array
     *
     * @deprecated Instead use {@see CentreonDB::fetchAllByColumn()}
     * @see        CentreonDB::fetchAllByColumn()
     */
    public function executeQueryFetchColumn(string $query, int $column = 0): array
    {
        try {
            $this->validateSelectQuery($query);
            $pdoSth = $this->executeQuery($query, PDO::FETCH_COLUMN, [$column]);

            return $this->fetchAll($pdoSth);
        } catch (\Throwable $e) {
            throw new CentreonDbException(
                "Error while fetching data with executeQueryFetchColumn() : {$e->getMessage()}",
                [
                    'query' => $query,
                    'column' => $column,
                ],
                $e
            );
        }
    }

    /**
     * Without prepared query
     *
     * @param string $query
     *
     * @throws CentreonDbException
     *
     * @return array
     * @deprecated Instead use {@see CentreonDB::fetchAllAssociative()}
     * @see        CentreonDB::fetchAllAssociative()
     */
    public function executeQueryFetchAll(string $query): array
    {
        try {
            $this->validateSelectQuery($query);
            $pdoSth = $this->executeQuery($query);

            return $this->fetchAll($pdoSth);
        } catch (\Throwable $e) {
            throw new CentreonDbException(
                "Error while fetching data with executeQueryFetchAll() : {$e->getMessage()}",
                ['query' => $query],
                $e
            );
        }
    }

    /**
     * @param mixed $val
     *
     * @return void
     * @deprecated No longer used by internal code and not recommended
     */
    public function autoCommit($val): void
    {
        /* Deprecated */
    }

    /**
     * Escapes a string for query
     *
     * @access     public
     *
     * @param string $str
     * @param bool $htmlSpecialChars | htmlspecialchars() is used when true
     *
     * @return string
     *
     * @deprecated No longer used by internal code and not recommended, instead use {@see CentreonDB::escapeString()}
     * @see        CentreonDB::escapeString()
     */
    public static function escape($str, $htmlSpecialChars = false)
    {
        if ($htmlSpecialChars) {
            $str = htmlspecialchars($str);
        }

        return addslashes($str ?? '');
    }

    /**
     * Query
     *
     * @param string $queryString
     * @param null|mixed $parameters
     * @param mixed ...$parametersArgs
     *
     * @throws PDOException
     * @return CentreonDBStatement|false
     *
     * @deprecated Instead use fetch* methods
     *
     * #[\ReturnTypeWillChange] to fix the change of the method's signature and avoid a fatal error
     */
    #[ReturnTypeWillChange]
    public function query($queryString, $parameters = null, ...$parametersArgs): CentreonDBStatement|false
    {
        if (! is_null($parameters) && ! is_array($parameters)) {
            $parameters = [$parameters];
        }

        /*
         * Launch request
         */
        $sth = null;
        try {
            if (is_null($parameters)) {
                $sth = parent::query($queryString);
            } else {
                $sth = $this->prepare($queryString);
                $sth->execute($parameters);
            }
        } catch (PDOException $e) {
            // skip if we use CentreonDBStatement::execute method
            if (is_null($parameters)) {
                $queryString = str_replace(["`", '*'], ["", "\*"], $queryString);
                $this->writeDbLog(
                    'Error while using CentreonDb::query',
                    ['bind_params' => $parameters],
                    query: $queryString,
                    previous: $e
                );
            }
            throw $e;
        }

        $this->queryNumber++;
        $this->successQueryNumber++;

        return $sth;
    }

    /**
     * launch a getAll
     *
     * @access     public
     *
     * @param string $query_string query
     * @param array<mixed> $placeHolders
     *
     * @throws PDOException
     *
     * @return array|false  getAll result
     *
     * @deprecated Instead use {@see CentreonDB::executeQuery(), CentreonDB::prepareQuery(), CentreonDB::executePreparedQuery()}
     * @see        CentreonDB::executeQuery(), CentreonDB::prepareQuery(), CentreonDB::executePreparedQuery()
     */
    public function getAll($query_string = null, $placeHolders = [])
    {
        $this->requestExecuted++;

        try {
            $result = $this->query($query_string);
            $rows = $result->fetchAll();
            $this->requestSuccessful++;
        } catch (PDOException $e) {
            $this->writeDbLog(
                'Error while using CentreonDb::getAll',
                query: $query_string,
                previous: $e
            );
            throw new PDOException($e->getMessage(), hexdec($e->getCode()));
        }

        return $rows;
    }

    /**
     * return number of rows
     *
     * @deprecated No longer used by internal code and not recommended, instead use {@see CentreonDB::executeQuery()}
     * @see        CentreonDB::executeQuery()
     */
    public function numberRows(): int
    {
        $number = 0;
        $dbResult = $this->query("SELECT FOUND_ROWS() AS number");
        $data = $dbResult->fetch();
        if (isset($data["number"])) {
            $number = $data["number"];
        }

        return (int) $number;
    }

    /**
     * checks if there is malicious injection
     *
     * @param string $sString
     *
     * @deprecated  No longer used by internal code and not recommended
     *
     * @description NOT DELETING BECAUSE IT USED IN centreon-modules/centreon-bam-server
     */
    public static function checkInjection($sString): int
    {
        return 0;
    }

}
