<?php

/*
 * Copyright 2005-2021 Centreon
 * Centreon is developed by : Julien Mathis and Romain Le Merlus under
 * GPL Licence 2.0.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation ; either version 2 of the License.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
 * PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, see <http://www.gnu.org/licenses>.
 *
 * Linking this program statically or dynamically with other modules is making a
 * combined work based on this program. Thus, the terms and conditions of the GNU
 * General Public License cover the whole combination.
 *
 * As a special exception, the copyright holders of this program give Centreon
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of Centreon choice, provided that
 * Centreon also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
 *
 * For more information : contact@centreon.com
 *
 */

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
 * @class CentreonDB
 * @description used to manage DB connection
 */
class CentreonDB extends PDO
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
     * Constructor
     *
     * @param string $dbLabel LABEL_DB_* constants
     * @param int $retry
     * @param CentreonDbConfig|null $dbConfig
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
                "Unable to connect to database : {$e->getMessage()}",
                ['dsn_mysql' => $this->dbConfig->getMysqlDsn()],
                exception: $e,
                level: CentreonLog::LEVEL_CRITICAL
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
     * @param CentreonDbConfig $dbConfig
     * @return CentreonDB
     * @throws Exception
     */
    public static function connectToCentreonDb(CentreonDbConfig $dbConfig): CentreonDB
    {
        return new self(dbLabel: self::LABEL_DB_CONFIGURATION, dbConfig: $dbConfig);
    }

    /**
     * Factory
     * @param CentreonDbConfig $dbConfig
     * @return CentreonDB
     * @throws Exception
     */
    public static function connectToCentreonStorageDb(CentreonDbConfig $dbConfig): CentreonDB
    {
        return new self(dbLabel: self::LABEL_DB_REALTIME, dbConfig: $dbConfig);
    }

    /**
     * @param string $query
     * @param array $options
     * @return PDOStatement|bool
     * @throws CentreonDbException
     */
    public function prepareQuery(string $query, array $options = []): PDOStatement|bool
    {
        if (empty($query)) {
            throw new CentreonDbException(
                'Error while preparing query, query must not be empty',
                [
                    'query' => $query,
                ]
            );
        }

        try {
            // here we don't want to use CentreonDbStatement, instead used PDOStatement
            $this->setAttribute(PDO::ATTR_STATEMENT_CLASS, [PDOStatement::class]);
            return parent::prepare($query, $options);
        } catch (PDOException $e) {
            $message = "Error while preparing the query: {$e->getMessage()}";
            $this->writeDbLog($message, ['options' => $options], $query, $e);
            throw new CentreonDbException(
                $message,
                [
                    'query' => $query,
                    'pdo_error_code' => $e->getCode(),
                    'pdo_error_infos' => $e->errorInfo,
                ],
                $e
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
     * @param PDOStatement $pdoStatement
     * @param array $bindParams
     * @param bool $withParamType
     * @return bool
     * @throws CentreonDbException
     */
    public function executePreparedQuery(
        PDOStatement $pdoStatement,
        array $bindParams,
        bool $withParamType = false
    ): bool {
        try {
            // here we don't want to use CentreonDbStatement, instead used PDOStatement
            $this->setAttribute(PDO::ATTR_STATEMENT_CLASS, [PDOStatement::class]);

            if (empty($bindParams)) {
                throw new CentreonDbException(
                    "Binding parameters are empty",
                    ['bind_params' => $bindParams]
                );
            }

            if (! $withParamType) {
                return $pdoStatement->execute($bindParams);
            }

            foreach ($bindParams as $paramName => $bindParam) {
                if (is_array($bindParam) && ! empty($bindParam) && count($bindParam) === 2) {
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
        } catch (PDOException $e) {
            $message = "Error while executing the prepared query: {$e->getMessage()}";
            $this->writeDbLog($message, ['bind_params' => $bindParams], $pdoStatement->queryString, $e);
            throw new CentreonDbException(
                $message,
                [
                    'query' => $pdoStatement->queryString,
                    'pdo_error_code' => $e->getCode(),
                    'pdo_error_infos' => $e->errorInfo,
                ],
                $e
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
     * @param $query
     * @param int $fetchMode
     * @param array $fetchModeArgs
     * @return PDOStatement|bool
     * @throws CentreonDbException
     */
    public function executeQuery(
        $query,
        int $fetchMode = PDO::FETCH_ASSOC,
        array $fetchModeArgs = []
    ): PDOStatement|bool {
        if (empty($query)) {
            throw new CentreonDbException(
                'Error while executing query, query must not be empty',
                [
                    'query' => $query,
                ]
            );
        }

        try {
            // here we don't want to use CentreonDbStatement, instead used PDOStatement
            $this->setAttribute(PDO::ATTR_STATEMENT_CLASS, [PDOStatement::class]);
            return parent::query($query, $fetchMode, ...$fetchModeArgs);
        } catch (PDOException $e) {
            $message = "Error while executing the simple query: {$e->getMessage()}";
            $this->writeDbLog($message, query: $query, exception: $e);
            throw new CentreonDbException(
                $message,
                [
                    'query' => $query,
                    'pdo_error_code' => $e->getCode(),
                    'pdo_error_infos' => $e->errorInfo,
                ],
                $e
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
     * @param string $query
     * @return array
     * @throws CentreonDbException
     */
    public function executeQueryFetchAll(string $query): array
    {
        $pdoSth = $this->executeQuery($query);
        return $this->fetchAll($pdoSth);
    }

    /**
     * @param string $query
     * @param int $column
     * @return array
     * @throws CentreonDbException
     */
    public function executeQueryFetchColumn(string $query, int $column = 0): array
    {
        $pdoSth = $this->executeQuery($query, PDO::FETCH_COLUMN, [$column]);
        return $this->fetchAll($pdoSth);
    }

    /**
     * @param PDOStatement $pdoStatement
     * @return mixed
     * @throws CentreonDbException
     */
    public function fetch(PDOStatement $pdoStatement): mixed
    {
        try {
            return $pdoStatement->fetch();
        } catch (PDOException $e) {
            $this->closeQuery($pdoStatement);
            $message = "Error while fetching the row: {$e->getMessage()}";
            $this->writeDbLog($message, query: $pdoStatement->queryString, exception: $e);
            throw new CentreonDbException(
                $message,
                [
                    'query' => $pdoStatement->queryString,
                    'pdo_error_code' => $e->getCode(),
                    'pdo_error_infos' => $e->errorInfo,
                ],
                $e
            );
        }
    }

    /**
     * @param PDOStatement $pdoStatement
     * @return array
     * @throws CentreonDbException
     */
    public function fetchAll(PDOStatement $pdoStatement): array
    {
        try {
            return $pdoStatement->fetchAll();
        } catch (PDOException $e) {
            $message = "Error while fetching all the rows: {$e->getMessage()}";
            $this->writeDbLog($message, query: $pdoStatement->queryString, exception: $e);
            throw new CentreonDbException(
                $message,
                [
                    'query' => $pdoStatement->queryString,
                    'pdo_error_code' => $e->getCode(),
                    'pdo_error_infos' => $e->errorInfo,
                ],
                $e
            );
        } finally {
            $this->closeQuery($pdoStatement);
        }
    }

    /**
     * @param PDOStatement $pdoStatement
     * @param int $column
     * @return array|bool
     * @throws CentreonDbException
     */
    public function fetchColumn(PDOStatement $pdoStatement, int $column = 0): mixed
    {
        try {
            return $pdoStatement->fetchColumn($column);
        } catch (PDOException $e) {
            $message = "Error while fetching all the rows by column: {$e->getMessage()}";
            $this->writeDbLog($message, ['column' => $column], query: $pdoStatement->queryString, exception: $e);
            throw new CentreonDbException(
                $message,
                [
                    'query' => $pdoStatement->queryString,
                    'pdo_error_code' => $e->getCode(),
                    'pdo_error_infos' => $e->errorInfo,
                ],
                $e
            );
        } finally {
            $this->closeQuery($pdoStatement);
        }
    }

    /**
     * @param PDOStatement $pdoStatement
     * @param array|null $bindParams
     * @return bool (no signature for this method because of a bug with tests with \Centreon\Test\Mock\CentreonDb::execute())
     * @throws CentreonDbException
     */
    public function execute(PDOStatement $pdoStatement, ?array $bindParams = null)
    {
        try {
            if ($bindParams === []) {
                throw new CentreonDbException(
                    "To execute the query, bindParams must to be an array filled or null, empty array given",
                    ['bind_params' => $bindParams]
                );
            }
            return $pdoStatement->execute($bindParams);
        } catch (PDOException $e) {
            $message = "Error while executing the query: {$e->getMessage()}";
            $this->writeDbLog(
                $message,
                ['bind_params' => $bindParams],
                query: $pdoStatement->queryString,
                exception: $e
            );
            throw new CentreonDbException(
                $message,
                [
                    'query' => $pdoStatement->queryString,
                    'pdo_error_code' => $e->getCode(),
                    'pdo_error_infos' => $e->errorInfo,
                ],
                $e
            );
        }
    }

    /**
     *  Allowed types : PDO::PARAM_STR, PDO::PARAM_BOOL, PDO::PARAM_INT, PDO::PARAM_NULL
     * @param PDOStatement $pdoStatement
     * @param int|string $paramName
     * @param mixed $value
     * @param int $type
     * @return bool
     * @throws CentreonDbException
     */
    public function makeBindValue(
        PDOStatement $pdoStatement,
        int|string $paramName,
        mixed $value,
        int $type = PDO::PARAM_STR
    ): bool {
        if (empty($paramName)) {
            throw new CentreonDbException(
                "paramName must to be filled, empty given",
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
            throw new CentreonDbException(
                "Error for the param type, it's not an integer or a value of PDO::PARAM_*",
                ['param_name' => $paramName]
            );
        }
        try {
            return $pdoStatement->bindValue($paramName, $value, $type);
        } catch (PDOException $e) {
            $message = "Error while binding value for param {$paramName} : {$e->getMessage()}";
            $this->writeDbLog(
                $message,
                [
                    'param_name' => $paramName,
                    'param_type' => $type,
                    'param_value' => $value,
                ],
                query: $pdoStatement->queryString,
                exception: $e
            );
            throw new CentreonDbException(
                $message,
                [
                    'query' => $pdoStatement->queryString,
                    'pdo_error_code' => $e->getCode(),
                    'pdo_error_infos' => $e->errorInfo,
                    'param_name' => $paramName,
                    'param_value' => $value,
                    'param_type' => $type
                ],
                $e
            );
        }
    }

    /**
     * @param PDOStatement $pdoStatement
     * @param int|string $paramName
     * @param mixed $var
     * @param int $type
     * @param int $maxLength
     * @return bool
     * @throws CentreonDbException
     */
    public function makeBindParam(
        PDOStatement $pdoStatement,
        int|string $paramName,
        mixed &$var,
        int $type = PDO::PARAM_STR,
        int $maxLength = 0
    ): bool {
        if (empty($paramName)) {
            throw new CentreonDbException(
                "paramName must to be filled, empty given",
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
            throw new CentreonDbException(
                "Error for the param type, it's not an integer or a value of PDO::PARAM_*",
                ['param_name' => $paramName]
            );
        }
        try {
            return $pdoStatement->bindParam($paramName, $var, $type, $maxLength);
        } catch (PDOException $e) {
            $message = "Error while binding param {$paramName} : {$e->getMessage()}";
            $this->writeDbLog(
                $message,
                [
                    'param_name' => $paramName,
                    'param_var' => $var,
                    'param_type' => $type,
                    'param_max_length' => $maxLength
                ],
                query: $pdoStatement->queryString,
                exception: $e
            );
            throw new CentreonDbException(
                $message,
                [
                    'query' => $pdoStatement->queryString,
                    'pdo_error_code' => $e->getCode(),
                    'pdo_error_infos' => $e->errorInfo,
                    'param_name' => $paramName,
                    'param_var' => $var,
                    'param_type' => $type,
                    'param_max_length' => $maxLength
                ],
                $e
            );
        }
    }

    /**
     * @param PDOStatement $pdoStatement
     * @return bool
     * @throws CentreonDbException
     */
    public function closeQuery(PDOStatement $pdoStatement): bool
    {
        try {
            return $pdoStatement->closeCursor();
        } catch (PDOException $e) {
            $message = "Error while closing the PDOStatement cursor: {$e->getMessage()}";
            $this->writeDbLog($message, query: $pdoStatement->queryString, exception: $e);
            throw new CentreonDbException(
                $message,
                [
                    'query' => $pdoStatement->queryString,
                    'pdo_error_code' => $e->getCode(),
                    'pdo_error_infos' => $e->errorInfo,
                ],
                $e
            );
        }
    }

    /**
     * @param string $string
     * @param int $type
     * @return string
     * @throws CentreonDbException
     */
    public function escapeString(string $string, int $type = PDO::PARAM_STR): string
    {
        $quotedString = parent::quote($string, $type);
        if ($quotedString === false) {
            throw new CentreonDbException("Error while quoting the string: {$string}");
        }
        return $quotedString;
    }

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
     * @return CentreonDB
     * @throws Exception
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
                'Error while checking if the column exists',
                [
                    'table' => $table,
                    'column' => $column,
                ],
                query: $stmt->queryString,
                exception: $e
            );
            return -1;
        }
    }

    /**
     * Indicates whether an index exists or not.
     *
     * @param string $table
     * @param string $indexName
     * @return bool
     * @throws PDOException
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
     * @return bool
     * @throws PDOException
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
                'Error while checking if the column exists',
                [
                    'table' => $tableName,
                    'column' => $columnName,
                ],
                query: $stmt->queryString,
                exception: $e
            );
        }
    }

    /**
     * Write SQL errors messages
     *
     * @param string $message
     * @param array $customContext
     * @param string $query
     * @param Throwable|null $exception
     * @param string $level
     */
    private function writeDbLog(
        string $message,
        array $customContext = [],
        string $query = '',
        ?Throwable $exception = null,
        string $level = CentreonLog::LEVEL_ERROR
    ): void {
        $defaultContext = ['db_name' => $this->dbConfig->dbName];
        if (! empty($query)) {
            $defaultContext['query'] = $query;
        }
        if ($exception instanceof PDOException) {
            $defaultContext['pdo_error_infos'] = $exception->errorInfo;
            $defaultContext['pdo_error_code'] = $exception->getCode();
        }
        $context = array_merge($defaultContext, $customContext);
        $this->logger->log(CentreonLog::TYPE_SQL, $level, "[CentreonDb] $message", $context, $exception);
    }

    //******************************************** DEPRECATED METHODS ***********************************************//

    /**
     * @param mixed $val
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
     * @access public
     *
     * @param string $str
     * @param bool $htmlSpecialChars | htmlspecialchars() is used when true
     *
     * @return string
     *
     * @deprecated No longer used by internal code and not recommended, instead use {@see CentreonDB::escapeString()}
     * @see CentreonDB::escapeString()
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
     * @return CentreonDBStatement|false
     *
     * @throws PDOException
     * @deprecated Instead use {@see CentreonDB::executeQuery(), CentreonDB::prepareQuery(), CentreonDB::executePreparedQuery()}
     * @see CentreonDB::executeQuery(), CentreonDB::prepareQuery(), CentreonDB::executePreparedQuery()
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
                    exception: $e
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
     * @access public
     * @param string $query_string query
     * @param array<mixed> $placeHolders
     *
     * @return array|false  getAll result
     *
     * @throws PDOException
     *
     * @deprecated Instead use {@see CentreonDB::executeQuery(), CentreonDB::prepareQuery(), CentreonDB::executePreparedQuery()}
     * @see CentreonDB::executeQuery(), CentreonDB::prepareQuery(), CentreonDB::executePreparedQuery()
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
                exception: $e
            );
            throw new PDOException($e->getMessage(), hexdec($e->getCode()));
        }

        return $rows;
    }

    /**
     * return number of rows
     *
     * @deprecated No longer used by internal code and not recommended, instead use {@see CentreonDB::executeQuery()}
     * @see CentreonDB::executeQuery()
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
     * @param string $sString
     *
     * @deprecated No longer used by internal code and not recommended
     *
     * @description NOT DELETING BECAUSE IT USED IN centreon-modules/centreon-bam-server
     */
    public static function checkInjection($sString): int
    {
        return 0;
    }

}
