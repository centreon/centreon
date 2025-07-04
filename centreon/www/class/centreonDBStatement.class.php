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

// file centreon.config.php may not exist in test environment
$configFile = realpath(__DIR__ . '/../../config/centreon.config.php');
if ($configFile !== false) {
    include_once $configFile;
}

require_once realpath(__DIR__ . '/centreonDB.class.php');

/**
 * Class
 *
 * @class CentreonDBStatement
 */
class CentreonDBStatement extends PDOStatement
{
    /**
     * When data is retrieved from `numRows()` method, it is stored in `allFetched`
     * in order to be usable later without requesting one more time the database.
     *
     * @var array|null
     */
    public $allFetched = null;

    /** @var CentreonLog */
    private $log;

    /**
     * CentreonDBStatement constructor
     *
     * @param CentreonLog|null $log
     */
    protected function __construct(?CentreonLog $log = null)
    {
        $this->log = $log;
    }

    /**
     * This method overloads PDO `fetch` in order to use the possible already
     * loaded data available in `allFetched`.
     *
     * @param int $mode
     * @param int $cursorOrientation
     * @param int $cursorOffset
     *
     * @return mixed
     */
    public function fetch(
        int $mode = PDO::FETCH_DEFAULT,
        int $cursorOrientation = PDO::FETCH_ORI_NEXT,
        int $cursorOffset = 0
    ): mixed {
        if (is_null($this->allFetched)) {
            return parent::fetch($mode, $cursorOrientation, $cursorOffset);
        }
        if (count($this->allFetched) <= 0) {
            return false;
        }

        return array_shift($this->allFetched);
    }

    /**
     * This method wraps the Fetch method for legacy code compatibility
     * @return mixed
     */
    public function fetchRow()
    {
        return $this->fetch();
    }

    /**
     * Free resources.
     *
     * @return void
     */
    public function free(): void
    {
        $this->closeCursor();
    }

    /**
     * Counts the number of rows returned by the query.\
     * This method fetches data if needed and store it in `allFetched`
     * in order to be usable later without requesting database again.
     *
     * @return int
     */
    public function numRows()
    {
        if (is_null($this->allFetched)) {
            $this->allFetched = $this->fetchAll();
        }

        return count($this->allFetched);
    }

    /**
     * This method wraps the PDO `execute` method and manages failures logging
     *
     * @param $parameters
     *
     * @throws PDOException
     * @return bool
     */
    public function execute($parameters = null): bool
    {
        $this->allFetched = null;

        try {
            $result = parent::execute($parameters);
        } catch (PDOException $e) {
            $string = str_replace('`', '', $this->queryString);
            $string = str_replace('*', "\*", $string);
            $this->log->insertLog(2, $e->getMessage() . ' QUERY : ' . $string . ', ' . json_encode($parameters));

            throw $e;
        }

        return $result;
    }
}
