<?php

/**
 * Copyright 2019-2021 Centreon
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
 */

namespace Centreon\Test\Mock;

// \CentreonDB is not autoloaded in module unit tests, so we need to mock it
if (!class_exists("\CentreonDB")) {
    (new \PHPUnit\Framework\MockObject\Generator)->getMock("\CentreonDB", array());
}

/**
 * Mock class for dbconn
 *
 * @author Centreon
 * @version 1.0.0
 * @package centreon-test-lib
 * @subpackage test
 */
class CentreonDB extends \CentreonDB
{

    /**
     * @var array
     */
    protected $queries;

    /**
     * @var callable
     */
    protected $commitCallback;

    /**
     * @var array
     */
    protected $transactionQueries;

    /**
     * @var string|false
     */
    protected $lastInsertId = false;

    /**
     * Constructor
     *
     * @param string $db
     * @param int $retry
     * @param bool $silent
     */
    public function __construct($db = 'centreon', $retry = 3, $silent = false)
    {
        $this->queries = [];
    }

    /**
     * Stub for function query
     *
     * {@inheritdoc}
     * @return CentreonDBStatement|false The resultset
     */
    public function query($queryString, $parameters = null, ...$parametersArgs): CentreonDBStatement|false
    {
        return $this->execute($queryString, null);
    }

    /**
     * Stub escape function
     *
     * @param string $string The string to escape
     * @param bool $htmlSpecialChars
     * @return string The string escaped
     */
    public static function escape($string, $htmlSpecialChars = false)
    {
        return $string;
    }

    /**
     * Stub quote function
     *
     * @param string $string The string to escape
     * @param string $paramtype
     * @return string|false The string escaped
     */
    public function quote(string $string, int $type = \PDO::PARAM_STR): string|false
    {
        return "'" . $string . "'";
    }

    /**
     * Reset result sets
     */
    public function resetResultSet(): void
    {
        $this->queries = [];
        $this->commitCallback = null;
    }

    /**
     * Add a resultset to the mock
     *
     * @param string $query The query to catch
     * @param array $result The resultset
     * @param array $params The parameters of query, if not set :
     *   * the query has not parameters
     *   * the result is generic for the query
     * @param callable $callback execute a callback when a query is executed
     */
    public function addResultSet($query, $result, $params = null, callable $callback = null)
    {
        if (!isset($this->queries[$query])) {
            $this->queries[$query] = [];
        }
        $this->queries[$query][] = new CentreonDBResultSet($result, $params, $callback);

        return $this;
    }

    /**
     * Add a callback set to test exception in commit of transaction
     *
     * @param callable $callback execute a callback when a query is executed
     */
    public function setCommitCallback(callable $callback = null)
    {
        $this->commitCallback = $callback;

        return $this;
    }

    /**
     * @param $query
     * @param array $options
     * @return CentreonDBStatement
     * @throws \Exception
     */
    public function prepare(string $query, array $options = []): CentreonDBStatement|false
    {
        if (!isset($this->queries[$query])) {
            throw new \Exception('Query is not set.' . "\nQuery : " . $query);
        }

        return new CentreonDBStatement($query, $this->queries[$query], $this);
    }

    /**
     * Execute a query with values
     *
     * @param string $query The query to execute
     * @param array $values The list of values for the query
     * @return CentreonDBResultSet The resultset
     */
    public function execute($query, $values = null)
    {
        if (!array_key_exists($query, $this->queries)) {
            throw new \Exception('Query is not set.' . "\nQuery : " . $query);
        }

        // find good query
        $matching = null;

        foreach ($this->queries[$query] as $resultSet) {
            $result = $resultSet->match($values);

            if ($result === 2) {
                return $resultSet;
            } elseif ($result === 1 && $matching === null) {
                $matching = $resultSet;
            }
        }

        if ($matching === null) {
            throw new \Exception('Query is not set.' . "\nQuery : " . $query);
        }

        // trigger callback
        $matching->executeCallback($values);

        // log queries if query will be execute in transaction
        $this->transactionLogQuery($query, $values, $matching);

        return $matching;
    }

    /**
     *
     * @param type $enable
     * @return type
     */
    public function autoCommit($enable): void
    {
        return;
    }

    /**
     * @return bool
     */
    public function beginTransaction(): bool
    {
        $this->transactionQueries = [];

        return true;
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function commit(): bool
    {
        if ($this->commitCallback !== null) {
            // copy and reset the property transactionQueries
            $queries = $this->transactionQueries;
            $this->transactionQueries = null;

            call_user_func($this->commitCallback, [$queries]);
        }

        return true;
    }

    /**
     * @return bool
     */
    public function rollback(): bool
    {
        return true;
    }

    /**
     * @param string|false $id
     */
    public function setLastInsertId($id = false): void
    {
        $this->lastInsertId = $id;
    }

    /**
     * @return int|null
     */
    public function lastInsertId(?string $name = null): string|false
    {
        return $this->lastInsertId;
    }

    /**
     * Log queries if query will be execute in transaction
     *
     * @param string $query
     * @param array $values
     * @param array $matching
     */
    public function transactionLogQuery(string $query, array $values = null, $matching): void
    {
        if ($this->transactionQueries !== null) {
            $this->transactionQueries[] = [
                'query' => $query,
                'params' => $values,
                'result' => $matching,
            ];
        }
    }
}
