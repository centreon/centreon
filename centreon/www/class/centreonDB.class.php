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

use Adaptation\Database\Collection\BatchInsertParameters;
use Adaptation\Database\Collection\QueryParameters;
use Adaptation\Database\ConnectionInterface;
use Adaptation\Database\Enum\QueryParameterTypeEnum;
use Adaptation\Database\Exception\ConnectionException;

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

    /** @var CentreonDbConfig */
    private CentreonDbConfig $dbConfig;

    /**
     * By default, the queries are buffered.
     *
     * @var bool
     */
    private bool $isBufferedQueryActive = true;

    /**
     * Constructor
     *
     * @param string                $dbLabel LABEL_DB_* constants
     * @param int                   $retry
     * @param CentreonDbConfig|null $dbConfig
     *
     * @throws Exception
     */
    public function __construct(
        $dbLabel = self::LABEL_DB_CONFIGURATION,
        $retry = self::RETRY,
        ?CentreonDbConfig $dbConfig = null
    ) {
        try {
            if (is_null($dbConfig)) {
                $this->dbConfig = new CentreonDbConfig(
                    $dbLabel === self::LABEL_DB_CONFIGURATION ? hostCentreon : hostCentstorage,
                    user,
                    password,
                    $dbLabel === self::LABEL_DB_CONFIGURATION ? db : dbcstg,
                    port ?? 3306
                );
            } else {
                $this->dbConfig = $dbConfig;
            }

            $this->logger = CentreonLog::create();

            $this->centreon_path = _CENTREON_PATH_;
            $this->retry = $retry;

            $this->options = [
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_STATEMENT_CLASS => [
                    CentreonDBStatement::class,
                    [$this->logger],
                ],
                PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
                PDO::MYSQL_ATTR_LOCAL_INFILE => true,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ];

            /*
             * Init request statistics
             */
            $this->requestExecuted = 0;
            $this->requestSuccessful = 0;
            $this->lineRead = 0;

            parent::__construct(
                $this->dbConfig->getMysqlDsn(),
                $this->dbConfig->dbUser,
                $this->dbConfig->dbPassword,
                $this->options
            );
        } catch (Exception $e) {
            $this->writeDbLog(
                message: "Unable to connect to database : {$e->getMessage()}",
                customContext: ['dsn_mysql' => $this->dbConfig->getMysqlDsn()],
                previous: $e,
            );
            if (PHP_SAPI !== "cli") {
                $this->displayConnectionErrorPage(
                    $e->getCode() === 2002 ? "Unable to connect to database" : $e->getMessage()
                );
            } else {
                throw new Exception($e->getMessage());
            }
        }
    }

    /**
     * Factory
     *
     * @param CentreonDbConfig $dbConfig
     *
     * @throws Exception
     * @return CentreonDB
     */
    public static function connectToCentreonDb(CentreonDbConfig $dbConfig): CentreonDB
    {
        return new self(dbLabel: self::LABEL_DB_CONFIGURATION, dbConfig: $dbConfig);
    }

    /**
     * Factory
     *
     * @param CentreonDbConfig $dbConfig
     *
     * @throws Exception
     * @return CentreonDB
     */
    public static function connectToCentreonStorageDb(CentreonDbConfig $dbConfig): CentreonDB
    {
        return new self(dbLabel: self::LABEL_DB_REALTIME, dbConfig: $dbConfig);
    }

    /**
     * Return the database name if it exists.
     *
     * @throws ConnectionException
     * @return string|null
     */
    public function getDatabaseName(): ?string
    {
        if (! isset($this->dbConfig->dbName) || empty($this->dbConfig->dbName)) {
            throw ConnectionException::getDatabaseNameFailed();
        }

        return $this->dbConfig->dbName;
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
        } catch (Throwable $e) {
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
     *  - DML statements: INSERT, UPDATE, DELETE
     *  - DDL statements: CREATE, DROP, ALTER, etc.
     *  - DCL statements: GRANT, REVOKE, etc.
     *  - Session control statements: ALTER SESSION, SET, DECLARE, etc.
     *  - Other statements that don't yield a row set.
     *
     * @param string               $query
     * @param QueryParameters|null $queryParameters
     *
     * @throws ConnectionException
     * @return int
     */
    public function executeStatement(string $query, ?QueryParameters $queryParameters = null): int
    {
        try {
            if (empty($query)) {
                throw ConnectionException::notEmptyQuery();
            }

            if (str_starts_with($query, 'SELECT')) {
                throw ConnectionException::executeStatementBadFormat(
                    "Cannot use it with a SELECT query",
                    $query
                );
            }

            // here we don't want to use CentreonDbStatement, instead used PDOStatement
            $this->setAttribute(\PDO::ATTR_STATEMENT_CLASS, [\PDOStatement::class]);

            try {
                $pdoStatement = $this->prepareQuery($query);
            } catch (CentreonDbException $e) {
                throw ConnectionException::executeStatementFailed($e, $query, $queryParameters);
            }

            if (! is_null($queryParameters)) {
                foreach ($queryParameters->getIterator() as $queryParameter) {
                    $pdoStatement->bindValue(
                        $queryParameter->getName(),
                        $queryParameter->getValue(),
                        ($queryParameter->getType() !== null) ?
                            $queryParameter->getType()->getValue() : QueryParameterTypeEnum::STRING
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
        } finally {
            // here we restart CentreonDbStatement for the other requests
            $this->setAttribute(\PDO::ATTR_STATEMENT_CLASS, [
                CentreonDBStatement::class,
                [$this->logger],
            ]);
        }
    }

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
    public function insert(string $query, QueryParameters $queryParameters): int
    {
        try {
            if (! str_starts_with($query, 'INSERT INTO ')
                || ! str_starts_with($query, 'insert into ')
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
     * This method supports PDO binding types.
     *
     * @param string                $tableName
     * @param array                 $columns
     * @param BatchInsertParameters $batchInsertParameters
     *
     * @throws ConnectionException
     * @return int
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

            foreach ($batchInsertParameters->getIterator() as $queryParameters) {
                if ($queryParameters->empty()) {
                    throw ConnectionException::batchInsertQueryBadUsage('Query parameters must not be empty');
                }
                if (count($columns) !== $queryParameters->length()) {
                    throw ConnectionException::batchInsertQueryBadUsage(
                        'Columns and query parameters must have the same length'
                    );
                }
                $valuesInsert[] = '(' . implode(', ', array_fill(0, $queryParameters->length(), '?')) . ')';
                $queryParametersToInsert = $queryParametersToInsert->mergeWith($queryParameters);
            }
            $query .= implode(', ', $valuesInsert);

            return $this->executeStatement($query, $queryParametersToInsert);
        } catch (Throwable $e) {
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
            throw ConnectionException::batchInsertQueryFailed($e, $tableName, $columns, $batchInsertParameters);
        }
    }

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
    public function update(string $query, QueryParameters $queryParameters): int
    {
        try {
            if (! str_starts_with($query, 'UPDATE ')
                || ! str_starts_with($query, 'update ')
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
     * @param string          $query
     * @param QueryParameters $queryParameters
     *
     * @throws ConnectionException
     * @return int
     */
    public function delete(string $query, QueryParameters $queryParameters): int
    {
        try {
            if (! str_starts_with($query, 'DELETE ')
                || ! str_starts_with($query, 'delete ')
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
     * @param string          $query
     * @param QueryParameters $queryParameters
     *
     * @throws ConnectionException
     * @return array<string, mixed>|false False is returned if no rows are found.
     */
    public function fetchNumeric(string $query, QueryParameters $queryParameters): false | array
    {
        try {
            $this->validateSelectQuery($query);
            $pdoStatement = $this->prepareQuery($query);
            $pdoStatement = $this->executeSelectQuery($pdoStatement, $queryParameters, \PDO::FETCH_NUM);

            return $this->fetch($pdoStatement);
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
     * @param string          $query
     * @param QueryParameters $queryParameters
     *
     * @throws ConnectionException
     * @return array<string, mixed>|false False is returned if no rows are found.
     */
    public function fetchAssociative(string $query, QueryParameters $queryParameters): false | array
    {
        try {
            $this->validateSelectQuery($query);
            $pdoStatement = $this->prepareQuery($query);
            $pdoStatement = $this->executeSelectQuery($pdoStatement, $queryParameters, \PDO::FETCH_ASSOC);

            return $this->fetch($pdoStatement);
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
     * @param string          $query
     * @param QueryParameters $queryParameters
     *
     * @throws ConnectionException
     * @return mixed|false False is returned if no rows are found.
     */
    public function fetchOne(string $query, QueryParameters $queryParameters): mixed
    {
        try {
            $this->validateSelectQuery($query);
            $pdoStatement = $this->prepareQuery($query);
            $pdoStatement = $this->executeSelectQuery($pdoStatement, $queryParameters, \PDO::FETCH_COLUMN);

            return $this->fetch($pdoStatement)[0] ?? false;
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
     * @param string          $query
     * @param QueryParameters $queryParameters
     * @param int             $column
     *
     * @throws ConnectionException
     * @return list<mixed>
     */
    public function fetchByColumn(string $query, QueryParameters $queryParameters, int $column = 0): array
    {
        try {
            $this->validateSelectQuery($query);
            $pdoStatement = $this->prepareQuery($query);
            $pdoStatement = $this->executeSelectQuery($pdoStatement, $queryParameters, \PDO::FETCH_COLUMN, [$column]);

            return $this->fetchAll($pdoStatement);
        } catch (\Throwable $e) {
            $this->writeDbLog(
                message: "Unable to fetch by column query",
                customContext: ['query_parameters' => $queryParameters, 'column' => $column],
                query: $query,
                previous: $e,
            );
            throw ConnectionException::fetchByColumnQueryFailed($e, $query, $queryParameters, $column);
        }
    }

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
    public function fetchAllNumeric(string $query, QueryParameters $queryParameters): array
    {
        try {
            $this->validateSelectQuery($query);
            $pdoStatement = $this->prepareQuery($query);
            $pdoStatement = $this->executeSelectQuery($pdoStatement, $queryParameters, \PDO::FETCH_NUM);

            return $this->fetchAll($pdoStatement);
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
     * @param string          $query
     * @param QueryParameters $queryParameters
     *
     * @throws ConnectionException
     * @return array<array<string,mixed>>
     */
    public function fetchAllAssociative(string $query, QueryParameters $queryParameters): array
    {
        try {
            $this->validateSelectQuery($query);
            $pdoStatement = $this->prepareQuery($query);
            $pdoStatement = $this->executeSelectQuery($pdoStatement, $queryParameters, \PDO::FETCH_ASSOC);

            return $this->fetchAll($pdoStatement);
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
     * Prepares and executes an SQL query and returns the result as an array of associative arrays with the name of the
     * column as key.
     *
     * Could be only used with SELECT.
     *
     * @param string          $query
     * @param QueryParameters $queryParameters
     * @param int             $column
     *
     * @throws ConnectionException
     * @return list<mixed>
     */
    public function fetchAllByColumn(string $query, QueryParameters $queryParameters, int $column = 0): array
    {
        try {
            $this->validateSelectQuery($query);
            $pdoStatement = $this->prepareQuery($query);
            $pdoStatement = $this->executeSelectQuery($pdoStatement, $queryParameters, \PDO::FETCH_COLUMN, [$column]);

            return $this->fetchAll($pdoStatement);
        } catch (\Throwable $e) {
            $this->writeDbLog(
                message: "Unable to fetch all by column query",
                customContext: ['query_parameters' => $queryParameters, 'column' => $column],
                query: $query,
                previous: $e,
            );
            throw ConnectionException::fetchAllByColumnQueryFailed($e, $query, $queryParameters, $column);
        }
    }

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
    public function fetchAllKeyValue(string $query, QueryParameters $queryParameters): array
    {
        try {
            $this->validateSelectQuery($query);
            $pdoStatement = $this->prepareQuery($query);
            $pdoStatement = $this->executeSelectQuery($pdoStatement, $queryParameters, \PDO::FETCH_KEY_PAIR);

            return $this->fetchAll($pdoStatement);
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
     * @param string          $query
     * @param QueryParameters $queryParameters
     *
     * @throws ConnectionException
     * @return array<mixed,array<string,mixed>>
     */
    public function fetchAllAssociativeIndexed(string $query, QueryParameters $queryParameters): array
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
     * @param string          $query
     * @param QueryParameters $queryParameters
     *
     * @throws ConnectionException
     * @return \Traversable<int,list<mixed>>
     */
    public function iterateNumeric(string $query, QueryParameters $queryParameters): \Traversable
    {
        try {
            $this->validateSelectQuery($query);
            $pdoStatement = $this->prepareQuery($query);
            $pdoStatement = $this->executeSelectQuery($pdoStatement, $queryParameters, \PDO::FETCH_NUM);
            while (($row = $this->fetch($pdoStatement)) !== false) {
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
     * @param string          $query
     * @param QueryParameters $queryParameters
     *
     * @throws ConnectionException
     * @return \Traversable<int,array<string,mixed>>
     */
    public function iterateAssociative(string $query, QueryParameters $queryParameters): \Traversable
    {
        try {
            $this->validateSelectQuery($query);
            $pdoStatement = $this->prepareQuery($query);
            $pdoStatement = $this->executeSelectQuery($pdoStatement, $queryParameters, \PDO::FETCH_ASSOC);
            while (($row = $this->fetch($pdoStatement)) !== false) {
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
     * @param string          $query
     * @param QueryParameters $queryParameters
     * @param int             $column
     *
     * @throws ConnectionException
     * @return \Traversable<int,list<mixed>>
     */
    public function iterateByColumn(string $query, QueryParameters $queryParameters, int $column = 0): \Traversable
    {
        try {
            $this->validateSelectQuery($query);
            $pdoStatement = $this->prepareQuery($query);
            $pdoStatement = $this->executeSelectQuery($pdoStatement, $queryParameters, \PDO::FETCH_COLUMN, [$column]);
            while (($row = $this->fetch($pdoStatement)) !== false) {
                yield $row;
            }
        } catch (\Throwable $e) {
            $this->writeDbLog(
                message: "Unable to iterate by column query",
                customContext: ['query_parameters' => $queryParameters, 'column' => $column],
                query: $query,
                previous: $e,
            );
            throw ConnectionException::iterateByColumnQueryFailed($e, $query, $queryParameters, $column);
        }
    }

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
     * @return \Traversable<mixed,mixed>
     */
    public function iterateKeyValue(string $query, QueryParameters $queryParameters): \Traversable
    {
        try {
            $this->validateSelectQuery($query);
            $pdoStatement = $this->prepareQuery($query);
            $pdoStatement = $this->executeSelectQuery($pdoStatement, $queryParameters, \PDO::FETCH_KEY_PAIR);
            while (($row = $this->fetch($pdoStatement)) !== false) {
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
     * @param string          $query
     * @param QueryParameters $queryParameters
     *
     * @throws ConnectionException
     * @return \Traversable<mixed,array<string,mixed>>
     */
    public function iterateAssociativeIndexed(string $query, QueryParameters $queryParameters): \Traversable
    {
        try {
            $this->validateSelectQuery($query);
            foreach ($this->iterateAssociative($query, $queryParameters) as $row) {
                yield [array_shift($row) => $row];
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
     * {@see commit} or {@see rollBack}
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
    public function commit(): bool
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
    public function rollBack(): bool
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
    public function allowUnbufferedQuery(): void
    {
        $currentDriverName = $this->getAttribute(PDO::ATTR_DRIVER_NAME);
        if (! in_array($currentDriverName, self::DRIVER_ALLOWED_UNBUFFERED_QUERY)) {
            $this->writeDbLog(
                message: "Unbuffered queries are not allowed with this driver",
                customContext: ['driver_name' => $currentDriverName]
            );
            throw ConnectionException::allowUnbufferedQueryFailed(parent::class, $currentDriverName);
        }
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
     * @param string $query
     * @param array  $options
     *
     * @throws CentreonDbException
     * @return \PDOStatement|bool
     */
    public function prepareQuery(string $query, array $options = []): \PDOStatement | bool
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
     * Prefer to use fetchNumeric() or fetchAssociative() instead of this method.
     *
     * @param \PDOStatement $pdoStatement
     *
     * @throws CentreonDbException
     *
     * @return mixed
     *
     * @see fetchNumeric(), fetchAssociative()
     */
    public function fetch(\PDOStatement $pdoStatement): mixed
    {
        try {
            return $pdoStatement->fetch();
        } catch (\Throwable $e) {
            $this->closeQuery($pdoStatement);
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
     * @see fetchAllNumeric(), fetchAllAssociative()
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
            $this->closeQuery($pdoStatement);
        }
    }

    /**
     * @param \PDOStatement $pdoStatement
     * @param array|null    $bindParams
     *
     * @throws CentreonDbException
     * @return bool (no signature for this method because of a bug with tests with \Centreon\Test\Mock\CentreonDb::execute())
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
     *  Allowed types : PDO::PARAM_STR, PDO::PARAM_BOOL, PDO::PARAM_INT, PDO::PARAM_NULL
     *
     * @param \PDOStatement $pdoStatement
     * @param int|string    $paramName
     * @param mixed         $value
     * @param int           $type
     *
     * @throws CentreonDbException
     * @return bool
     */
    public function makeBindValue(
        \PDOStatement $pdoStatement,
        int | string $paramName,
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
        } catch (Throwable $e) {
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
     * @param int|string    $paramName
     * @param mixed         $var
     * @param int           $type
     * @param int           $maxLength
     *
     * @throws CentreonDbException
     * @return bool
     */
    public function makeBindParam(
        \PDOStatement $pdoStatement,
        int | string $paramName,
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
     * @param PDOStatement $pdoStatement
     *
     * @throws CentreonDbException
     * @return bool
     */
    public function closeQuery(PDOStatement $pdoStatement): bool
    {
        try {
            return $pdoStatement->closeCursor();
        } catch (\Throwable $e) {
            $this->writeDbLog(
                message: "Error while closing the PDOStatement cursor: {$e->getMessage()}",
                query: $pdoStatement->queryString,
                previous: $e,
            );
            throw new CentreonDbException(
                message: "Error while closing the PDOStatement cursor: {$e->getMessage()}",
                options: ['query' => $pdoStatement->queryString],
                previous: $e
            );
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
                       a, div.Error{font-family:"Bitstream Vera Sans", arial, Tahoma, "Sans serif";font-weight: bold;}
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
     * Factory for singleton
     *
     * @param string $name The name of centreon datasource
     *
     * @throws Exception
     * @return CentreonDB
     */
    public static function factory($name = self::LABEL_DB_CONFIGURATION)
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
            if ($dbResult = $this->query("SHOW TABLE STATUS FROM `" . $this->dbConfig->dbName . "`")) {
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
     * @param string|null $table  - the table on which we'll search the column
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
            $stmt->bindValue(':dbName', $this->dbConfig->dbName, PDO::PARAM_STR);
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
        $statement->bindValue(':db_name', $this->dbConfig->dbName);
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
        $statement->bindValue(':db_name', $this->dbConfig->dbName);
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
            $stmt->bindValue(':dbName', $this->dbConfig->dbName, PDO::PARAM_STR);
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
     * @param string               $query
     * @param QueryParameters|null $queryParameters
     * @param int                  $fetchMode
     * @param array                $fetchModeArgs
     *
     * @throws ConnectionException
     * @return PDOStatement|false
     */
    private function executeSelectQuery(
        string $query,
        ?QueryParameters $queryParameters = null,
        int $fetchMode = PDO::FETCH_ASSOC,
        array $fetchModeArgs = []
    ): PDOStatement | false {
        try {
            // here we don't want to use CentreonDbStatement, instead used PDOStatement
            $this->setAttribute(\PDO::ATTR_STATEMENT_CLASS, [\PDOStatement::class]);

            try {
                $pdoStatement = $this->prepareQuery($query);
            } catch (CentreonDbException $e) {
                throw ConnectionException::executeStatementFailed(
                    $e,
                    $query,
                    ['query' => $query, 'infos' => $e->getOptions()]
                );
            }

            foreach ($queryParameters->getIterator() as $queryParameter) {
                $pdoStatement->bindValue(
                    $queryParameter->getName(),
                    $queryParameter->getValue(),
                    ($queryParameter->getType() !== null) ?
                        $queryParameter->getType()->getValue() : QueryParameterTypeEnum::STRING
                );
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
                context: [
                    'query' => $query,
                    'query_parameters' => $queryParameters,
                ]
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
        if (! str_starts_with($query, 'SELECT')) {
            throw ConnectionException::selectQueryBadFormat($query);
        }
    }

    /**
     * Write SQL errors messages
     *
     * @param string         $message
     * @param array          $customContext
     * @param string         $query
     * @param Throwable|null $previous
     */
    private function writeDbLog(
        string $message,
        array $customContext = [],
        string $query = '',
        ?Throwable $previous = null
    ): void {
        // prepare context of the database exception
        if ($previous instanceof CentreonDbException) {
            $dbExceptionContext = $previous->getOptions();
        } elseif ($previous instanceof ConnectionException) {
            $dbExceptionContext = $previous->getContext();
        } elseif ($previous instanceof PDOException) {
            $dbExceptionContext = ['pdo_error' => $previous->errorInfo];
        } else {
            $dbExceptionContext = [];
        }
        if (isset($dbExceptionContext['query'])) {
            unset($dbExceptionContext['query']);
        }

        // prepare default context
        $defaultContext = ['db_name' => $this->dbConfig->dbName];
        if (! empty($query)) {
            $defaultContext['query'] = $query;
        }

        $context = array_merge($defaultContext, $customContext, ['db_exception_context' => $dbExceptionContext]);

        $this->logger->log(
            CentreonLog::TYPE_SQL,
            CentreonLog::LEVEL_CRITICAL,
            "[CentreonDb] $message",
            $context,
            $previous
        );
    }

    //******************************************** DEPRECATED METHODS ***********************************************//

    /**
     * @param string $string
     * @param int    $type
     *
     * @throws CentreonDbException
     * @return string
     *
     * @deprecated Use {@see quote()} instead
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
     * @param int   $fetchMode
     * @param array $fetchModeArgs
     *
     * @throws CentreonDbException
     * @return PDOStatement|bool
     *
     * @deprecated Instead use {@see CentreonDB::fetch***(), CentreonDB::iterate***()}
     */
    public function executeQuery(
        $query,
        int $fetchMode = PDO::FETCH_ASSOC,
        array $fetchModeArgs = []
    ): PDOStatement | false {
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
        } catch (Throwable $e) {
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
     * @param array        $bindParams
     * @param bool         $withParamType
     *
     * @throws CentreonDbException
     *
     * @return bool
     *
     * @deprecated Instead use {@see CentreonDB::insert(), CentreonDB::update(), CentreonDB::delete(), CentreonDB::fetch***(), CentreonDB::iterate***()}
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
     * Only for DDL queries (ALTER TABLE, CREATE TABLE, DROP TABLE, CREATE DATABASE, and TRUNCATE TABLE...)
     *
     * @param string $query
     *
     * @throws CentreonDbException
     * @return bool
     *
     * @deprecated Instead use {@see CentreonDB::executeStatement()}
     * @see        CentreonDB::executeStatement()
     */
    public function updateDatabase(string $query): bool
    {
        try {
            if (empty($query)) {
                throw new CentreonDbException(
                    'Query must not be empty',
                    ['query' => $query]
                );
            }
            $standardQueryStarts = ['SELECT ', 'UPDATE ', 'DELETE ', 'INSERT INTO '];
            foreach ($standardQueryStarts as $standardQueryStart) {
                if (
                    str_starts_with($query, strtolower($standardQueryStart))
                    || str_starts_with($query, strtoupper($standardQueryStart))
                ) {
                    throw new CentreonDbException(
                        'Query must not to start by SELECT, UPDATE, DELETE or INSERT INTO, this method is only for DDL queries',
                        ['query' => $query]
                    );
                }
            }

            return $this->exec($query) !== false;
        } catch (\Throwable $e) {
            $this->writeDbLog(
                message: "Error while updating the database: {$e->getMessage()}",
                query: $query,
                previous: $e
            );
            throw new CentreonDbException(
                message: "Error while updating the database: {$e->getMessage()}",
                options: ['query' => $query,],
                previous: $e
            );
        }
    }

    /**
     * @param \PDOStatement $pdoStatement
     * @param int           $column
     *
     * @throws CentreonDbException
     *
     * @return array|bool
     *
     * @deprecated Instead use {@see CentreonDB::fetchByColumn()}
     * @see        CentreonDB::fetchByColumn()
     */
    public function fetchColumn(\PDOStatement $pdoStatement, int $column = 0): mixed
    {
        try {
            return $pdoStatement->fetchColumn($column);
        } catch (\Throwable $e) {
            $this->closeQuery($pdoStatement);
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
     * @param int    $column
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
        } catch (Throwable $e) {
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
        } catch (Throwable $e) {
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
     * @param bool   $htmlSpecialChars | htmlspecialchars() is used when true
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
     * @param string     $queryString
     * @param null|mixed $parameters
     * @param mixed      ...$parametersArgs
     *
     * @throws PDOException
     * @return CentreonDBStatement|false
     *
     * @deprecated Instead use fetch* methods
     *
     * #[\ReturnTypeWillChange] to fix the change of the method's signature and avoid a fatal error
     */
    #[ReturnTypeWillChange]
    public function query($queryString, $parameters = null, ...$parametersArgs): CentreonDBStatement | false
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
     * @param string       $query_string query
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
