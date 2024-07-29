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
 * Class CentreonDB used to manage DB connection
 */
class CentreonDB extends \PDO
{
    /**
     * @var string
     */
    public const LABEL_DB_CONFIGURATION = 'centreon';

    /**
     * @var string
     */
    public const LABEL_DB_REALTIME = 'centstorage';

    /**
     * @var array<string,\CentreonDB>
     */
    private static $instance = [];

    /**
     * @var string
     */
    protected $db_type = "mysql";

    /**
     * @var string
     */
    protected $db_port = "3306";

    /**
     * @var int
     */
    protected $retry;

    /**
     * @var array<string,mixed>
     */
    protected $dsn;

    /**
     * @var array<int, array<int, mixed>|int|bool|string>
     */
    protected $options;

    /**
     * @var string
     */
    protected $centreon_path;

    /**
     * @var \CentreonLog
     */
    protected $log;


    /*
     * Statistics
     */

    /**
     * @var int
     */
    protected $requestExecuted;

    /**
     * @var int
     */
    protected $requestSuccessful;

    /**
     * @var int
     */
    protected $lineRead;

    /**
     * @var int
     */
    private $queryNumber;

    /**
     * @var int
     */
    private $successQueryNumber;

    /**
     * Constructor
     *
     * @param string $db | centreon, centstorage
     * @param int $retry
     *
     * @throws Exception
     */
    public function __construct($db = self::LABEL_DB_CONFIGURATION, $retry = 3)
    {
        try {
            $conf_centreon['hostCentreon'] = hostCentreon;
            $conf_centreon['hostCentstorage'] = hostCentstorage;
            $conf_centreon['user'] = user;
            $conf_centreon['password'] = password;
            $conf_centreon['db'] = db;
            $conf_centreon['dbcstg'] = dbcstg;
            $conf_centreon['port'] = port;

            $this->log = new CentreonLog();

            $this->centreon_path = _CENTREON_PATH_;
            $this->retry = $retry;

            $this->options = [
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_STATEMENT_CLASS => [
                    CentreonDBStatement::class,
                    [$this->log],
                ],
                PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
                PDO::MYSQL_ATTR_LOCAL_INFILE => true,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ];

            /*
             * Add possibility to change SGDB port
             */
            if (!empty($conf_centreon["port"])) {
                $this->db_port = $conf_centreon["port"];
            } else {
                $this->db_port = '3306';
            }

            $this->dsn = [
                'phptype' => $this->db_type,
                'username' => $conf_centreon["user"],
                'password' => $conf_centreon["password"],
                'port' => $this->db_port
            ];

            if (strtolower($db) === self::LABEL_DB_REALTIME) {
                $this->dsn['hostspec'] = $conf_centreon["hostCentstorage"];
                $this->dsn['database'] = $conf_centreon["dbcstg"];
            } else {
                $this->dsn['hostspec'] = $conf_centreon["hostCentreon"];
                $this->dsn['database'] = $conf_centreon["db"];
            }

            /*
             * Init request statistics
             */
            $this->requestExecuted = 0;
            $this->requestSuccessful = 0;
            $this->lineRead = 0;

            parent::__construct(
                $this->dsn['phptype'] . ":" . "dbname=" . $this->dsn['database'] .
                ";host=" . $this->dsn['hostspec'] . ";port=" . $this->dsn['port'],
                $this->dsn['username'],
                $this->dsn['password'],
                $this->options
            );
        } catch (Exception $e) {
            if (php_sapi_name() !== "cli") {
                $this->displayConnectionErrorPage(
                    $e->getCode() === 2002 ? "Unable to connect to database" : $e->getMessage()
                );
            } else {
                throw new Exception($e->getMessage());
            }
        }
    }

    /**
     * @param string $query
     * @param array $options
     * @return PDOStatement|bool
     * @throws CentreonDbException
     */
    public function prepareQuery(string $query, array $options = []): PDOStatement|bool
    {
        try {
            return parent::prepare($query, $options);
        } catch (PDOException $e) {
            $message = "Error while preparing the query: {$e->getMessage()}";
            $this->logSqlError($query, $message);
            throw new CentreonDbException(
                $message,
                [
                    'query' => $query,
                    'pdo_error_code' => $e->getCode(),
                    'pdo_error_infos' => $e->errorInfo,
                ],
                $e
            );
        }
    }

    /**
     * @param PDOStatement $pdoStatement
     * @param array $bindParams
     * @param bool $withParamType
     * @return bool
     * @throws CentreonDbException
     */
    public function executePreparedQuery(PDOStatement $pdoStatement, array $bindParams, bool $withParamType = false): bool
    {
        try {
            if (empty($bindParams)) {
                throw new CentreonDbException(
                    "Binding parameters are empty",
                    ['bind_params' => $bindParams]
                );
            }

            if ($withParamType) {
                foreach ($bindParams as $paramName => $bindParam) {
                    if (is_array($bindParam) && !empty($bindParam)) {
                        $paramValue = $bindParam[0];
                        $paramType = $bindParam[1];
                        $this->makeBindValue($pdoStatement, $paramName, $paramValue, $paramType);
                    }
                }
                return $pdoStatement->execute();
            }

            return $pdoStatement->execute($bindParams);

        } catch (PDOException $e) {
            $message = "Error while executing the prepared query: {$e->getMessage()}";
            $this->logSqlError($pdoStatement->queryString, $message);
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
     * @param $query
     * @param int $fetchMode
     * @param array $fetchModeArgs
     * @return PDOStatement|bool
     * @throws CentreonDbException
     */
    public function executeQuery($query, int $fetchMode = PDO::FETCH_ASSOC, array $fetchModeArgs = []): PDOStatement|bool
    {
        try {
            return parent::query($query, $fetchMode, ...$fetchModeArgs);
        } catch (PDOException $e) {
            $message = "Error while executing the simple query: {$e->getMessage()}";
            $this->logSqlError($query, $message);
            throw new CentreonDbException(
                $message,
                [
                    'query' => $query,
                    'pdo_error_code' => $e->getCode(),
                    'pdo_error_infos' => $e->errorInfo,
                ],
                $e
            );
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
            $this->logSqlError($pdoStatement->queryString, $message);
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
            $this->logSqlError($pdoStatement->queryString, $message);
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
            $this->logSqlError($pdoStatement->queryString, $message);
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
     * @param int|string $paramName
     * @param mixed $value
     * @param int $type
     * @return bool
     * @throws CentreonDbException
     */
    public function makeBindValue(PDOStatement $pdoStatement, int|string $paramName, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        try {
            return $pdoStatement->bindValue($paramName, $value, $type);
        } catch (PDOException $e) {
            $message = "Error while binding value for param {$paramName} : {$e->getMessage()}";
            $this->logSqlError($pdoStatement->queryString, $message);
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
        int|string $paramName, mixed &$var,
        int $type = PDO::PARAM_STR,
        int $maxLength = 0
    ): bool
    {
        try {
            return $pdoStatement->bindParam($paramName, $var, $type, $maxLength);
        } catch (PDOException $e) {
            $message = "Error while binding param {$paramName} : {$e->getMessage()}";
            $this->logSqlError($pdoStatement->queryString, $message);
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
            $this->logSqlError($pdoStatement->queryString, $message);
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
    public function escapeString(string $string, int $type): string
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
        if (!$msg) {
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
        if (!in_array($name, [self::LABEL_DB_CONFIGURATION, self::LABEL_DB_REALTIME])) {
            throw new Exception("The datasource isn't defined in configuration file.");
        }
        if (!isset(self::$instance[$name])) {
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
            if ($dbResult = $this->query("SHOW TABLE STATUS FROM `" . $this->dsn['database'] . "`")) {
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
     * @param string $table - the table on which we'll search the column
     * @param string $column - the column name to be checked
     *
     * @return int
     */
    public function isColumnExist(string $table = null, string $column = null): int
    {
        if (!$table || !$column) {
            return -1;
        }

        $table = \HtmlAnalyzer::sanitizeAndRemoveTags($table);
        $column = \HtmlAnalyzer::sanitizeAndRemoveTags($column);

        $query = "SELECT COLUMN_NAME
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = :dbName
            AND TABLE_NAME = :tableName
            AND COLUMN_NAME = :columnName";

        $stmt = $this->prepare($query);

        try {
            $stmt->bindValue(':dbName', $this->dsn['database'], \PDO::PARAM_STR);
            $stmt->bindValue(':tableName', $table, \PDO::PARAM_STR);
            $stmt->bindValue(':columnName', $column, \PDO::PARAM_STR);
            $stmt->execute();
            $stmt->fetch();

            if ($stmt->rowCount()) {
                return 1; // column already exist
            }
            return 0; // column to add
        } catch (\PDOException $e) {
            $this->logSqlError($query, $e->getMessage());
            return -1;
        }
    }

    /**
     * Indicates whether an index exists or not.
     *
     * @param string $table
     * @param string $indexName
     *
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
        $statement->bindValue(':db_name', $this->dsn['database']);
        $statement->bindValue(':table_name', $table);
        $statement->bindValue(':index_name', $indexName);

        $statement->execute();
        return !empty($statement->fetch(\PDO::FETCH_ASSOC));
    }

    /**
     * Indicates whether a constraint on table exists or not.
     *
     * @param string $table
     * @param string $constraintName
     *
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
        $statement->bindValue(':db_name', $this->dsn['database']);
        $statement->bindValue(':table_name', $table);
        $statement->bindValue(':constraint_name', $constraintName);

        $statement->execute();
        return !empty($statement->fetch(\PDO::FETCH_ASSOC));
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
        $tableName = \HtmlAnalyzer::sanitizeAndRemoveTags($tableName);
        $columnName = \HtmlAnalyzer::sanitizeAndRemoveTags($columnName);

        $query = 'SELECT COLUMN_TYPE
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = :dbName
            AND TABLE_NAME = :tableName
            AND COLUMN_NAME = :columnName';

        $stmt = $this->prepare($query);

        try {
            $stmt->bindValue(':dbName', $this->dsn['database'], \PDO::PARAM_STR);
            $stmt->bindValue(':tableName', $tableName, \PDO::PARAM_STR);
            $stmt->bindValue(':columnName', $columnName, \PDO::PARAM_STR);
            $stmt->execute();
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!empty($result)) {
                return $result['COLUMN_TYPE'];
            }
            throw new \PDOException("Unable to get column type");
        } catch (\PDOException $e) {
            $this->logSqlError($query, $e->getMessage());
        }
    }

    /**
     * Write SQL errors messages and queries
     *
     * @param string $query the query string to write to log
     * @param string $message the message to write to log
     */
    private function logSqlError(string $query, string $message): void
    {
        $this->log->insertLog(2, "{$message} QUERY : {$query}");
    }

    //******************************************** DEPRECATED METHODS ***********************************************//

    /**
     * @param mixed $val
     * @return void
     * @deprecated No longer used by internal code and not recommended
     */
    public function autoCommit($val)
    {
        /* Deprecated */
    }

    /**
     * @param PDOStatement|false $stmt
     * @param string[] $arrayValues
     * @return bool
     * @deprecated No longer used by internal code and not recommended, instead use {@see CentreonDB::executePreparedQuery()}
     * @see CentreonDB::executePreparedQuery()
     */
    public function execute($stmt, $arrayValues)
    {
        return $stmt->execute($arrayValues);
    }

    /**
     *
     *
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
     */
    public function query($queryString, $parameters = null, ...$parametersArgs): CentreonDBStatement|bool {
        if (!is_null($parameters) && !is_array($parameters)) {
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
                $sth = $sth->execute($parameters);
            }
        } catch (\PDOException $e) {
            // skip if we use CentreonDBStatement::execute method
            if (is_null($parameters)) {
                $string = str_replace("`", "", $queryString);
                $string = str_replace('*', "\*", $string);
                $this->logSqlError($string, $e->getMessage());
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
     * @return mixed[]|false  getAll result
     *
     * @throws \PDOException
     *
     * @deprecated Instead use {@see CentreonDB::executeQuery(), CentreonDB::prepareQuery(), CentreonDB::executePreparedQuery()}
     * @see CentreonDB::executeQuery(), CentreonDB::prepareQuery(), CentreonDB::executePreparedQuery()
     */
    public function getAll($query_string = null, $placeHolders = [])
    {
        $rows = [];
        $this->requestExecuted++;

        try {
            $result = $this->query($query_string);
            $rows = $result->fetchAll();
            $this->requestSuccessful++;
        } catch (\PDOException $e) {
            $this->logSqlError($query_string, $e->getMessage());
            throw new \PDOException($e->getMessage(), hexdec($e->getCode()));
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
        return (int)$number;
    }

    /**
     * checks if there is malicious injection
     * @param string $sString
     *
     * @deprecated No longer used by internal code and not recommended
     */
    public static function checkInjection($sString): int
    {
        return 0;
    }

}
