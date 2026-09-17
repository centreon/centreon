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

namespace Centreon\Infrastructure\CentreonLegacyDB;

use PDO;
use PDOStatement;

/**
 * The purpose of the collector is to have the ability to bind a value data (parameters
 * and column too) to statement before the statement object to be initialized
 */
class StatementCollector
{
    /**
     * Collection of columns
     *
     * @var array
     */
    protected $columns = [];

    /**
     * Collection of values
     *
     * @var array
     */
    protected $values = [];

    /**
     * Collection of parameters
     *
     * @var array
     */
    protected $params = [];

    /**
     * Add a column
     *
     * @param string $parameter
     * @param mixed $value
     * @param int $data_type
     */
    public function addColumn($parameter, $value, int $data_type = PDO::PARAM_STR): void
    {
        $this->columns[$parameter] = [
            'value' => $value,
            'data_type' => $data_type,
        ];
    }

    /**
     * Add a value
     *
     * @param string $parameter
     * @param mixed $value
     * @param int $data_type
     */
    public function addValue($parameter, $value, int $data_type = PDO::PARAM_STR): void
    {
        $this->values[$parameter] = [
            'value' => $value,
            'data_type' => $data_type,
        ];
    }

    /**
     * Add a parameter
     *
     * @param string $parameter
     * @param mixed $value
     * @param int $data_type
     */
    public function addParam($parameter, $value, int $data_type = PDO::PARAM_STR): void
    {
        $this->params[$parameter] = [
            'value' => $value,
            'data_type' => $data_type,
        ];
    }

    /**
     * Bind collected data to statement
     *
     * @param PDOStatement $stmt
     */
    public function bind(PDOStatement $stmt): void
    {
        // bind columns to statment
        foreach ($this->values as $parameter => $data) {
            $stmt->bindColumn($parameter, $data['value'], $data['data_type']);
        }

        // bind values to statment
        foreach ($this->values as $parameter => $data) {
            $stmt->bindValue($parameter, $data['value'], $data['data_type']);
        }

        // bind parameters to statment
        foreach ($this->values as $parameter => $data) {
            $stmt->bindParam($parameter, $data['value'], $data['data_type']);
        }
    }
}
