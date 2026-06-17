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
     * Get object parameters
     *
     * @param int $objectId
     * @param mixed $parameterNames
     * @return array
     */
    public function getParameters($objectId, $parameterNames)
    {
        if (is_array($parameterNames)) {
            $params = implode(',', array_map([$this, 'sanitizeIdentifier'], $parameterNames));
        } else {
            $params = $parameterNames !== '*' ? $this->sanitizeIdentifier($parameterNames) : $parameterNames;
        }
        $sql = "SELECT {$params} FROM {$this->table} WHERE {$this->primaryKey} = :objectId";
        $stmt = $this->dbMon->prepare($sql);
        $stmt->bindValue(':objectId', $objectId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch();
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
        $filterType = 'OR',
    ) {
        if ($filterType != 'OR' && $filterType != 'AND') {
            throw new Exception('Unknown filter type');
        }
        if (is_array($parameterNames)) {
            $params = implode(',', array_map([$this, 'sanitizeIdentifier'], $parameterNames));
        } else {
            $params = $parameterNames !== '*' ? $this->sanitizeIdentifier($parameterNames) : $parameterNames;
        }
        $sql = "SELECT {$params} FROM {$this->table} ";
        $bindParams = [];
        $filterIndex = 0;
        $whereClauses = [];
        foreach ($filters as $key => $rawvalue) {
            $key = $this->sanitizeIdentifier($key);
            $paramName = ':filter_' . $filterIndex++;
            $value = trim($rawvalue);
            $value = str_replace('\\', '\\\\', $value);
            $value = str_replace('_', "\_", $value);
            $value = str_replace(' ', "\ ", $value);
            $whereClauses[] = "{$key} LIKE {$paramName}";
            $bindParams[$paramName] = $value;
        }
        if ($whereClauses !== []) {
            $sql .= ' WHERE ' . implode(" {$filterType} ", $whereClauses);
        }
        if (isset($order, $sort) && (strtoupper($sort) == 'ASC' || strtoupper($sort) == 'DESC')) {
            $order = $this->sanitizeIdentifier($order);
            $sql .= " ORDER BY {$order} {$sort} ";
        }
        if (isset($count) && $count != -1) {
            $sql = $this->dbMon->limit($sql, $count, $offset);
        }

        $stmt = $this->dbMon->prepare($sql);
        foreach ($bindParams as $paramName => $paramValue) {
            $stmt->bindValue($paramName, $paramValue);
        }
        $stmt->execute();

        return $stmt->fetchAll();
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
        $paramName = $this->sanitizeIdentifier($paramName);
        if (! is_array($paramValues)) {
            $paramValues = [$paramValues];
        }
        if ($paramValues === []) {
            return [];
        }

        $conditions = [];
        $bindParams = [];
        foreach ($paramValues as $index => $val) {
            $paramKey = ':val_' . $index;
            $conditions[] = $paramName . ' = ' . $paramKey;
            $bindParams[$paramKey] = $val;
        }

        $sql = "SELECT {$this->primaryKey} FROM {$this->table} WHERE " . implode(' OR ', $conditions);
        $stmt = $this->dbMon->prepare($sql);
        foreach ($bindParams as $paramKey => $paramValue) {
            $stmt->bindValue($paramKey, $paramValue);
        }
        $stmt->execute();

        $tab = [];
        foreach ($stmt->fetchAll() as $row) {
            $tab[] = $row[$this->primaryKey];
        }

        return $tab;
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

    /**
     * @throws InvalidArgumentException
     */
    protected function sanitizeIdentifier(string $name): string
    {
        $name = trim(trim($name), '`');
        if ($name === '' || preg_match('/[;\'"\\\\#]|--|\/\*/', $name) === 1) {
            throw new InvalidArgumentException("Invalid identifier: {$name}");
        }

        return $name;
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
}
