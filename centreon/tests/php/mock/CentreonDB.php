<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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

namespace Centreon\Test\Mock;

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
    /** @var array */
    protected array $queries = [];

    /** @var callable */
    protected $commitCallback;

    /** @var array|null */
    protected ?array $transactionQueries = null;

    /** @var string|false */
    protected string|false $lastInsertId = false;

    public function __construct()
    {

    }

    /**
     * Stub for function query
     *
     * {@inheritDoc}
     * @throws \Exception
     * @return CentreonDBStatement|false The resultset
     */
    public function query($queryString, $parameters = null, ...$parametersArgs): CentreonDBStatement|false
    {
        return $this->execute($queryString, null);
    }

    /**
     * Stub escape function
     *
     * @param string $str The string to escape
     * @param bool $htmlSpecialChars
     *
     * @return string The string escaped
     */
    public static function escape($str, $htmlSpecialChars = false): string
    {
        return $str;
    }

    /**
     * Stub quote function
     *
     * @param string $string The string to escape
     * @param int $type
     *
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
     * @param array|null $params The parameters of query, if not set :
     *                           * the query has not parameters
     *                           * the result is generic for the query
     * @param callable|null $callback execute a callback when a query is executed
     *
     * @return CentreonDB
     */
    public function addResultSet(string $query, array $result, ?array $params = null, ?callable $callback = null): CentreonDB
    {
        if (! isset($this->queries[$query])) {
            $this->queries[$query] = [];
        }
        $this->queries[$query][] = new CentreonDBResultSet($result, $params, $callback);

        return $this;
    }

    /**
     * Add a callback set to test exception in commit of transaction
     *
     * @param callable|null $callback execute a callback when a query is executed
     *
     * @return CentreonDB
     */
    public function setCommitCallback(?callable $callback = null): CentreonDB
    {
        $this->commitCallback = $callback;

        return $this;
    }

    /**
     * @param string $query
     * @param array $options
     *
     * @throws \Exception
     * @return CentreonDBStatement|false
     */
    public function prepare(string $query, array $options = []): CentreonDBStatement|false
    {
        if (! isset($this->queries[$query])) {
            throw new \Exception('Query is not set.' . "\nQuery : " . $query);
        }

        return new CentreonDBStatement($query, $this->queries[$query], $this);
    }

    /**
     * Execute a query with values
     *
     * @param string $query The query to execute
     * @param null $values The list of values for the query
     *
     * @throws \Exception
     * @return CentreonDBResultSet The resultset
     */
    public function execute($query, $values = null): CentreonDBResultSet
    {
        if (! array_key_exists($query, $this->queries)) {
            throw new \Exception('Query is not set.' . "\nQuery : " . $query);
        }

        // find good query
        $matching = null;

        foreach ($this->queries[$query] as $resultSet) {
            $result = $resultSet->match($values);

            if ($result === 2) {
                return $resultSet;
            }
            if ($result === 1 && $matching === null) {
                $matching = $resultSet;
            }
        }

        if ($matching === null) {
            throw new \Exception('Query is not set.' . "\nQuery : " . $query);
        }

        // trigger callback
        $matching->executeCallback($values);

        // log queries if query will be execute in transaction
        $this->transactionLogQuery($query, $matching, $values);

        return $matching;
    }

    /**
     * @param mixed $val
     *
     * @return void
     */
    public function autoCommit($val): void
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
     * @throws \Exception
     * @return bool
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
     * @param false|string $id
     */
    public function setLastInsertId(false|string $id = false): void
    {
        $this->lastInsertId = $id;
    }

    /**
     * @param string|null $name
     *
     * @return string|false
     */
    public function lastInsertId(?string $name = null): string|false
    {
        return $this->lastInsertId;
    }

    /**
     * Log queries if query will be execute in transaction
     *
     * @param string $query
     * @param array|null $values
     * @param CentreonDBResultSet $matching
     */
    public function transactionLogQuery(string $query, CentreonDBResultSet $matching, ?array $values = null): void
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
