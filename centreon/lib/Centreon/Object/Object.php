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
 *
 * @author sylvestre
 */
abstract class Centreon_Object
{
    /**
     * Database Connector
     * @var CentreonDB
     */
    protected $db;

    /**
     * Table name of the object
     * @var string|null
     */
    protected $table = null;

    /**
     * Primary key name
     * @var string|null
     */
    protected $primaryKey = null;

    /**
     * Unique label field
     * @var string|null
     */
    protected $uniqueLabelField = null;

    /**
     * Centreon_Object constructor
     *
     * @param Container $dependencyInjector
     */
    public function __construct(Container $dependencyInjector)
    {
        $this->db = $dependencyInjector['configuration_db'];
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
     * Used for inserting object into database
     *
     * @param array $params
     *
     * @throws PDOException
     * @return false|string|null
     */
    public function insert($params = [])
    {
        $fields = [];
        $placeholders = [];
        $bindParams = [];
        foreach ($params as $key => $value) {
            $key = $this->sanitizeIdentifier($key);
            if ($key == $this->primaryKey) {
                continue;
            }
            $fields[] = $key;
            $placeholders[] = ':' . $key;
            $bindParams[':' . $key] = trim($value);
        }

        if ($fields === []) {
            return null;
        }

        $sql = "INSERT INTO {$this->table} (" . implode(',', $fields) . ') VALUES ('
            . implode(',', $placeholders) . ')';
        $statement = $this->db->prepare($sql);
        foreach ($bindParams as $paramName => $paramValue) {
            $statement->bindValue($paramName, $paramValue);
        }
        $statement->execute();

        return $this->db->lastInsertId();
    }

    /**
     * Used for deleteing object from database
     *
     * @param int $objectId
     *
     * @throws PDOException
     */
    public function delete($objectId): void
    {
        $statement = $this->db->prepare(
            "DELETE FROM {$this->table} WHERE {$this->primaryKey} = :objectId"
        );
        $statement->bindValue(':objectId', $objectId, PDO::PARAM_INT);
        $statement->execute();
    }

    /**
     * Used for updating object in database
     *
     * @param int $objectId
     * @param array $params
     *
     * @throws PDOException
     * @return void
     */
    public function update($objectId, $params = []): void
    {
        $notNullAttributes = [];

        if (array_search('', $params, true) !== false) {
            $res = $this->getResult("SHOW FIELDS FROM {$this->table}", [], 'fetchAll');
            foreach ($res as $tab) {
                if ($tab['Null'] == 'NO') {
                    $notNullAttributes[$tab['Field']] = true;
                }
            }
        }

        $setClauses = [];
        $bindParams = [];
        foreach ($params as $key => $value) {
            $key = $this->sanitizeIdentifier($key);
            if ($key == $this->primaryKey) {
                continue;
            }
            $setClauses[] = $key . ' = :' . $key;
            if ($value === '' && ! isset($notNullAttributes[$key])) {
                $value = null;
            }
            if (! is_null($value)) {
                $value = str_replace('<br/>', "\n", $value);
            }
            $bindParams[':' . $key] = $value;
        }

        if ($setClauses !== []) {
            $sql = "UPDATE {$this->table} SET " . implode(',', $setClauses)
                . " WHERE {$this->primaryKey} = :primaryKeyId";
            $statement = $this->db->prepare($sql);
            foreach ($bindParams as $paramName => $paramValue) {
                $statement->bindValue($paramName, $paramValue);
            }
            $statement->bindValue(':primaryKeyId', $objectId, PDO::PARAM_INT);
            $statement->execute();
        }
    }

    /**
     * Used for duplicating object
     *
     * @param int $sourceObjectId
     * @param int $duplicateEntries
     *
     * @throws PDOException
     * @todo relations
     */
    public function duplicate($sourceObjectId, $duplicateEntries = 1): void
    {
        $sourceParams = $this->getParameters($sourceObjectId, '*');
        if (isset($sourceParams[$this->primaryKey])) {
            unset($sourceParams[$this->primaryKey]);
        }
        if (isset($sourceParams[$this->uniqueLabelField])) {
            $originalName = $sourceParams[$this->uniqueLabelField];
        }
        $originalName = $sourceParams[$this->uniqueLabelField];
        for ($i = 1; $i <= $duplicateEntries; $i++) {
            if (isset($sourceParams[$this->uniqueLabelField], $originalName)) {
                $sourceParams[$this->uniqueLabelField] = $originalName . '_' . $i;
            }
            $ids = $this->getIdByParameter($this->uniqueLabelField, [$sourceParams[$this->uniqueLabelField]]);
            if (! count($ids)) {
                $this->insert($sourceParams);
            }
        }
    }

    /**
     * Get object parameters
     *
     * @param int $objectId
     * @param mixed $parameterNames
     *
     * @throws PDOException
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
        $statement = $this->db->prepare($sql);
        $statement->bindValue(':objectId', $objectId, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetch(PDO::FETCH_ASSOC);
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
            if (is_array($rawvalue)) {
                if ($rawvalue === []) {
                    $whereClauses[] = '1 = 0';
                    continue;
                }
                $inPlaceholders = [];
                foreach ($rawvalue as $inValue) {
                    $paramName = ':filter_' . $filterIndex++;
                    $inPlaceholders[] = $paramName;
                    $bindParams[$paramName] = $inValue;
                }
                $whereClauses[] = "{$key} IN (" . implode(',', $inPlaceholders) . ')';
            } else {
                $paramName = ':filter_' . $filterIndex++;
                $value = trim($rawvalue);
                $value = str_replace('\\', '\\\\', $value);
                $value = str_replace('_', "\_", $value);
                $value = str_replace(' ', "\ ", $value);
                $whereClauses[] = "{$key} LIKE {$paramName}";
                $bindParams[$paramName] = $value;
            }
        }
        if ($whereClauses !== []) {
            $sql .= ' WHERE ' . implode(" {$filterType} ", $whereClauses);
        }
        if (isset($order, $sort) && (strtoupper($sort) == 'ASC' || strtoupper($sort) == 'DESC')) {
            $order = $this->sanitizeIdentifier($order);
            $sql .= " ORDER BY {$order} {$sort} ";
        }
        if (isset($count) && $count != -1) {
            $sql = $this->db->limit($sql, $count, $offset);
        }

        $statement = $this->db->prepare($sql);
        foreach ($bindParams as $paramName => $paramValue) {
            $statement->bindValue($paramName, $paramValue);
        }
        $statement->execute();

        return $statement->fetchAll();
    }

    /**
     * Generic method that allows to retrieve object ids
     * from another object parameter
     *
     * @param string $paramName
     * @param array $paramValues
     *
     * @throws PDOException
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
        $statement = $this->db->prepare($sql);
        foreach ($bindParams as $paramKey => $paramValue) {
            $statement->bindValue($paramKey, $paramValue);
        }
        $statement->execute();

        $tab = [];
        foreach ($statement->fetchAll() as $row) {
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
     *
     * @throws PDOException
     * @return array
     */
    protected function getResult($sqlQuery, $sqlParams = [], $fetchMethod = 'fetchAll')
    {
        $res = $this->db->query($sqlQuery, $sqlParams);

        return $res->{$fetchMethod}();
    }
}
