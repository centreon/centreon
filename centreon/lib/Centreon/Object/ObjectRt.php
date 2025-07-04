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

use Pimple\Container;

/**
 * Abstract Centreon Object class
 */
abstract class Centreon_ObjectRt
{
    /** Database Connector */
    protected $dbMon;

    /** Table name of the object */
    protected $table = null;

    /** Primary key name */
    protected $primaryKey = null;

    /** Unique label field */
    protected $uniqueLabelField = null;

    /**
     * Centreon_ObjectRt constructor
     *
     * @param Container $dependencyInjector
     */
    public function __construct(Container $dependencyInjector)
    {
        $this->dbMon = $dependencyInjector['realtime_db'];
    }

    /**
     * Get result from sql query
     *
     * @param string $sqlQuery
     * @param array $sqlParams
     * @param string $fetchMethod
     * @return array
     */
    protected function getResult($sqlQuery, $sqlParams = [], $fetchMethod = 'fetchAll')
    {
        $res = $this->dbMon->query($sqlQuery, $sqlParams);

        return $res->{$fetchMethod}();
    }

    /**
     * Get object parameters
     *
     * @param int $objectId
     * @param mixed $parameterNames
     * @return array
     */
    public function getParameters($objectId, $parameterNames)
    {
        $params = is_array($parameterNames) ? implode(',', $parameterNames) : $parameterNames;
        $sql = "SELECT {$params} FROM {$this->table} WHERE {$this->primaryKey} = ?";

        return $this->getResult($sql, [$objectId], 'fetch');
    }

    /**
     * List all objects with all their parameters
     * Data heavy, use with as many parameters as possible
     * in order to limit it
     *
     * @param mixed $parameterNames
     * @param int $count
     * @param int $offset
     * @param string $order
     * @param string $sort
     * @param array $filters
     * @param string $filterType
     * @throws Exception
     * @return array
     */
    public function getList(
        $parameterNames = '*',
        $count = -1,
        $offset = 0,
        $order = null,
        $sort = 'ASC',
        $filters = [],
        $filterType = 'OR'
    ) {
        if ($filterType != 'OR' && $filterType != 'AND') {
            throw new Exception('Unknown filter type');
        }
        $params = is_array($parameterNames) ? implode(',', $parameterNames) : $parameterNames;
        $sql = "SELECT {$params} FROM {$this->table} ";
        $filterTab = [];
        if (count($filters)) {
            foreach ($filters as $key => $rawvalue) {
                if ($filterTab === []) {
                    $sql .= " WHERE {$key} LIKE ? ";
                } else {
                    $sql .= " {$filterType} {$key} LIKE ? ";
                }
                $value = trim($rawvalue);
                $value = str_replace('\\', '\\\\', $value);
                $value = str_replace('_', "\_", $value);
                $value = str_replace(' ', "\ ", $value);
                $filterTab[] = $value;
            }
        }
        if (isset($order, $sort)   && (strtoupper($sort) == 'ASC' || strtoupper($sort) == 'DESC')) {
            $sql .= " ORDER BY {$order} {$sort} ";
        }
        if (isset($count) && $count != -1) {
            $sql = $this->dbMon->limit($sql, $count, $offset);
        }

        return $this->getResult($sql, $filterTab, 'fetchAll');
    }

    /**
     * Generic method that allows to retrieve object ids
     * from another object parameter
     *
     * @param string $paramName
     * @param array $paramValues
     * @return array
     */
    public function getIdByParameter($paramName, $paramValues = [])
    {
        $sql = "SELECT {$this->primaryKey} FROM {$this->table} WHERE ";
        $condition = '';
        if (! is_array($paramValues)) {
            $paramValues = [$paramValues];
        }
        foreach ($paramValues as $val) {
            if ($condition != '') {
                $condition .= ' OR ';
            }
            $condition .= $paramName . ' = ? ';
        }
        if ($condition) {
            $sql .= $condition;
            $rows = $this->getResult($sql, $paramValues, 'fetchAll');
            $tab = [];
            foreach ($rows as $val) {
                $tab[] = $val[$this->primaryKey];
            }

            return $tab;
        }

        return [];
    }

    /**
     * Generic method that allows to retrieve object ids
     * from another object parameter
     *
     * @param string $name
     * @param array $args
     * @throws Exception
     * @return array
     */
    public function __call($name, $args)
    {
        if (preg_match('/^getIdBy([a-zA-Z0-9_]+)/', $name, $matches)) {
            return $this->getIdByParameter($matches[1], $args);
        }

        throw new Exception('Unknown method');
    }

    /**
     * Primary Key Getter
     *
     * @return string
     */
    public function getPrimaryKey()
    {
        return $this->primaryKey;
    }

    /**
     * Unique label field getter
     *
     * @return string
     */
    public function getUniqueLabelField()
    {
        return $this->uniqueLabelField;
    }

    /**
     * Get Table Name
     *
     * @return string
     */
    public function getTableName()
    {
        return $this->table;
    }
}
