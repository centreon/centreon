<?php
/**
 * Copyright 2019 Centreon
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

/**
 * Mock class for resultset
 *
 * @author Centreon
 * @version 1.0.0
 * @package centreon-test-lib
 * @subpackage test
 */
class CentreonDBResultSet extends CentreonDBStatement
{
    protected $resultset = [];
    protected $params = null;
    protected $pos = 0;

    /**
     * @var callable
     */
    protected $callback;

    /**
     * Constructor
     *
     * @param array $resultset The resultset for a query
     * @param array $params The parameters of query, if not set :
     *   * the query has not parameters
     *   * the result is generic for the query
     * @param callable $callback execute a callback when a query is executed
     */
    public function __construct($resultset, $params = null, callable $callback = null)
    {
        $this->resultset = $resultset;
        $this->params = $params;
        $this->callback = $callback;

        if ($this->params !== null) {
            ksort($this->params);
        }
    }

    /**
     * Execute the callback comes from the test case object
     */
    public function executeCallback($values = null): void
    {
        if ($this->callback !== null) {
            call_user_func($this->callback, $values);
        }
    }

    /**
     * Return a result
     *
     * @return array
     */
    public function fetchRow()
    {
        if (!isset($this->resultset[$this->pos])) {
            return false;
        }
        return $this->resultset[$this->pos++];
    }

    /**
     * Return a result
     *
     * @return mixed
     */
    public function fetch(
        int $mode = \PDO::FETCH_DEFAULT,
        int $cursorOrientation = \PDO::FETCH_ORI_NEXT,
        int $cursorOffset = 0
    ): mixed {
        return $this->fetchRow();
    }

    /**
     * Return all results
     *
     * @return array
     */
    public function fetchAll(int $mode = \PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        $this->pos = count($this->resultset);
        return $this->resultset;
    }

    /**
     * Reset the position of resultset
     */
    public function resetResultSet(): void
    {
        $this->pos = 0;
    }

    /**
     * Get resultset stack
     */
    public function getResultSet(): array
    {
        return $this->resultset;
    }

    /**
     * Return the number of rows of the result set
     *
     * @return int
     */
    public function numRows()
    {
        return $this->rowCount();
    }

    /**
     * Count of updated lines
     *
     * @return int
     */
    public function rowCount(): int
    {
        return count($this->resultset);
    }

    /**
     * If the queries match
     *
     * @param array $params The parameters of current query
     * @return int The level of match
     *   * 0 - Not match
     *   * 1 - Match by default (the result set params is null by the query has $params)
     *   * 2 - Exact match
     */
    public function match($params = null)
    {
        if ($this->params === $params) {
            return 2;
        } elseif ($this->params === null) {
            return 1;
        } elseif ($params !== null) {
            ksort($params);
        }

        return 0;
    }

    /**
     * @return bool
     */
    public function closeCursor(): bool
    {
        return true;
    }
}
