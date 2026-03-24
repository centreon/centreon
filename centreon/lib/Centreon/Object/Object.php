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
    public function __call($name, $args): array
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
    public function insert($params = []): false|string|null
    {
        $sqlFields = [];
        $sqlPlaceholders = [];
        $bindParams = [];

        foreach ($params as $key => $value) {
            if ($key == $this->primaryKey) {
                continue;
            }
            $sqlFields[] = $this->validateIdentifier($key);
            $placeholder = ':' . str_replace('.', '_', $key);
            $sqlPlaceholders[] = $placeholder;
            $bindParams[$placeholder] = trim($value);
        }

        if ($sqlFields === []) {
            return null;
        }

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->table,
            implode(', ', $sqlFields),
            implode(', ', $sqlPlaceholders)
        );

        $statement = $this->db->prepare($sql);
        foreach ($bindParams as $placeholder => $value) {
            $statement->bindValue($placeholder, $value);
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
        $sql = "DELETE FROM  {$this->table} WHERE {$this->primaryKey} = ?";
        $this->db->query($sql, [$objectId]);
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
        $setClauses = [];
        $sqlParams = [];
        $not_null_attributes = [];

        if (array_search('', $params)) {
            $sql_attr = "SHOW FIELDS FROM {$this->table}";
            $res = $this->getResult($sql_attr, [], 'fetchAll');
            foreach ($res as $tab) {
                if ($tab['Null'] == 'NO') {
                    $not_null_attributes[$tab['Field']] = true;
                }
            }
        }

        foreach ($params as $key => $value) {
            if ($key == $this->primaryKey) {
                continue;
            }
            $setClauses[] = $this->validateIdentifier($key) . ' = ?';
            if ($value === '' && ! isset($not_null_attributes[$key])) {
                $value = null;
            }
            if (! is_null($value)) {
                $value = str_replace('<br/>', "\n", $value);
            }
            $sqlParams[] = $value;
        }

        if ($setClauses !== []) {
            $sqlParams[] = $objectId;
            $sql = sprintf(
                'UPDATE %s SET %s WHERE %s = ?',
                $this->table,
                implode(', ', $setClauses),
                $this->validateIdentifier($this->primaryKey)
            );
            $this->db->query($sql, $sqlParams);
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
            if ($ids === []) {
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
    public function getParameters($objectId, $parameterNames): array
    {
        $paramList = is_array($parameterNames) ? $parameterNames : [$parameterNames];
        $params = implode(',', array_map([$this, 'validateIdentifier'], $paramList));
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
        $filterType = 'OR',
    ) {
        if ($filterType != 'OR' && $filterType != 'AND') {
            throw new Exception('Unknown filter type');
        }
        $paramList = is_array($parameterNames) ? $parameterNames : [$parameterNames];
        $params = implode(',', array_map([$this, 'validateIdentifier'], $paramList));
        $sql = "SELECT {$params} FROM {$this->table} ";
        $filterTab = [];
        if (count($filters)) {
            foreach ($filters as $key => $rawvalue) {
                $safeKey = $this->validateIdentifier($key);
                if ($filterTab === []) {
                    $sql .= " WHERE {$safeKey} ";
                } else {
                    $sql .= " {$filterType} {$safeKey} ";
                }
                if (is_array($rawvalue)) {
                    $sql .= ' IN (' . str_repeat('?,', count($rawvalue) - 1) . '?) ';
                    $filterTab = array_merge($filterTab, $rawvalue);
                } else {
                    $sql .= ' LIKE ? ';
                    $value = trim($rawvalue);
                    $value = str_replace('\\', '\\\\', $value);
                    $value = str_replace('_', "\_", $value);
                    $value = str_replace(' ', "\ ", $value);
                    $filterTab[] = $value;
                }
            }
        }
        if (isset($order, $sort) && (strtoupper($sort) == 'ASC' || strtoupper($sort) == 'DESC')) {
            $sql .= " ORDER BY {$order} {$sort} ";
        }
        if (isset($count) && $count != -1) {
            $sql = $this->db->limit($sql, $count, $offset);
        }

        return $this->getResult($sql, $filterTab, 'fetchAll');
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
    public function getIdByParameter($paramName, $paramValues = []): array
    {
        $safeParamName = $this->validateIdentifier($paramName);
        $sql = "SELECT {$this->primaryKey} FROM {$this->table} WHERE ";
        $condition = '';
        if (! is_array($paramValues)) {
            $paramValues = [$paramValues];
        }
        foreach ($paramValues as $val) {
            if ($condition != '') {
                $condition .= ' OR ';
            }
            $condition .= "`{$safeParamName}` = ? ";
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
     * Primary Key Getter
     *
     * @return string
     */
    public function getPrimaryKey(): string
    {
        return $this->primaryKey;
    }

    /**
     * Unique label field getter
     *
     * @return string
     */
    public function getUniqueLabelField(): string
    {
        return $this->uniqueLabelField;
    }

    /**
     * Get Table Name
     *
     * @return string
     */
    public function getTableName(): string
    {
        return $this->table;
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
    protected function getResult($sqlQuery, $sqlParams = [], $fetchMethod = 'fetchAll'): array
    {
        $res = $this->db->query($sqlQuery, $sqlParams);

        return $res->{$fetchMethod}();
    }

    /**
     * Validate a SQL identifier (column or table name) to prevent SQL injection.
     * Allows alphanumeric characters, underscores, dots, and the wildcard '*'.
     *
     * @param string $name
     *
     * @throws InvalidArgumentException
     * @return string
     */
    private function validateIdentifier(string $name): string
    {
        if (! preg_match('/^[a-zA-Z0-9_.]+$/', $name)) {
            throw new InvalidArgumentException("Invalid SQL identifier: {$name}");
        }

        return $name;
    }
}
