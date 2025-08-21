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

use Centreon\Test\Mock\CentreonDB;

/**
 * Mock class for resultset
 *
 * @author Centreon
 * @version 1.0.0
 * @package centreon-test-lib
 * @subpackage test
 */
class CentreonDBStatement extends \CentreonDBStatement
{
    /**
     * @var string
     */
    protected $query;

    /**
     * @var array
     */
    protected $resultsets;

    /**
     * @var \Centreon\Test\Mock\CentreonDB
     */
    protected $db;

    /**
     * @var array
     */
    protected $params = null;
    protected $currentResultSet = null;

    /**
     * Constructor
     *
     * @param string $query
     * @param array $resultset The resultset for a query
     * @param CentreonDB $db
     */
    public function __construct($query, $resultsets, CentreonDB $db)
    {
        $this->query = $query;
        $this->resultsets = $resultsets;
        $this->db = $db;
    }

    /**
     * Bind column
     * {@inheritDoc}
     */
    public function bindColumn(
        string|int $column,
        mixed &$var,
        int $type = \PDO::PARAM_STR,
        int $maxLength = 0,
        mixed $driverOptions = null
    ): bool {
        return true;
    }

    /**
     * Bind parameter
     */
    public function bindParam(
        string|int $param,
        mixed &$var,
        int $type = \PDO::PARAM_STR,
        int $maxLength = 0,
        mixed $driverOptions = null
    ): bool {
        $this->bindValue($param, $var);

        return true;
    }

    /**
     * Bind value
     */
    public function bindValue(
        string|int $param,
        mixed $value,
        int $type = \PDO::PARAM_STR
    ): bool {
        if (is_null($this->params)) {
            $this->params = array();
        }
        if (is_int($param)) {
            $this->params[$param - 1] = $value;
        } else {
            $this->params[$param] = $value;
        }

        return true;
    }

    /**
     * Execute statement
     */
    public function execute($params = null): bool
    {
        $matching = null;

        foreach ($this->resultsets as $resultset) {
            $result = $resultset->match($this->params);
            if ($result === 2 && is_null($this->currentResultSet)) {
                $this->currentResultSet = $resultset;
            } elseif ($result === 1 && is_null($matching)) {
                $matching = $resultset;
            }
        }

        if (is_null($this->currentResultSet)) {
            $this->currentResultSet = $matching;
        }

        if (is_null($this->currentResultSet)) {
            throw new \Exception('The query has not match');
        }

        // trigger callback
        $this->currentResultSet->executeCallback($this->params);

        // log queries if query will be execute in transaction
        $this->db->transactionLogQuery($this->query, $this->params, $this->currentResultSet);

        return true;
    }

    /**
     * Count of updated lines
     *
     * @return int
     */
    public function rowCount(): int
    {
        return $this->currentResultSet->rowCount();
    }

    /**
     * Return a result
     *
     * @return array
     */
    public function fetchRow()
    {
        if (!is_null($this->currentResultSet)) {
            return $this->currentResultSet->fetchRow();
        }

        return false;
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
     * Reset the position of resultset
     */
    public function resetResultSet(): void
    {
        if (!is_null($this->currentResultSet)) {
            $this->currentResultset->fetchRow();
        }
    }

    /**
     * Get count of rows
     *
     * @return int
     */
    public function numRows()
    {
        if (!is_null($this->currentResultSet)) {
            return $this->currentResultSet->numRows();
        }

        return 0;
    }

    /**
     * Close cursor
     */
    public function closeCursor(): bool
    {
        return true;
    }

    /**
     * Return a result
     *
     * @return array
     */
    public function fetchAll(int $mode = \PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        $results = [];

        while ($row = $this->fetch()) {
            $results[] = $row;
        }

        return $results;
    }


    /**
     * Set fetch mode
     *
     * @param mixed $mode
     * @param mixed $params
     * @return bool
     */
    public function setFetchMode(int $mode, ...$args): bool
    {
        $this->fetchObjectName = $args;

        return true;
    }
}
