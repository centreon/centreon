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
 * Class
 *
 * @class Centreon_Object_Relation
 */
abstract class Centreon_Object_Relation
{
    /** @var string */
    public $firstObject;

    /** @var string */
    public $secondObject;

    /** @var mixed */
    protected $db;

    /** @var null */
    protected $relationTable = null;

    /** @var null */
    protected $firstKey = null;

    /** @var null */
    protected $secondKey = null;

    /**
     * Centreon_Object_Relation constructor
     *
     * @param Container $dependencyInjector
     */
    public function __construct(Container $dependencyInjector)
    {
        $this->db = $dependencyInjector['configuration_db'];
    }

    /**
     * Used for inserting relation into database
     *
     * @param int $fkey
     * @param int $skey
     * @return void
     */
    public function insert($fkey, $skey = null): void
    {
        $sql = "INSERT INTO {$this->relationTable} ({$this->firstKey}, {$this->secondKey}) VALUES (?, ?)";
        $this->db->query($sql, [$fkey, $skey]);
    }

    /**
     * Used for deleting relation from database
     *
     * @param int $fkey
     * @param int $skey
     * @return void
     */
    public function delete($fkey, $skey = null): void
    {
        if (isset($fkey, $skey)) {
            $sql = "DELETE FROM {$this->relationTable} WHERE {$this->firstKey} = ? AND {$this->secondKey} = ?";
            $args = [$fkey, $skey];
        } elseif (isset($skey)) {
            $sql = "DELETE FROM {$this->relationTable} WHERE {$this->secondKey} = ?";
            $args = [$skey];
        } else {
            $sql = "DELETE FROM {$this->relationTable} WHERE {$this->firstKey} = ?";
            $args = [$fkey];
        }
        $this->db->query($sql, $args);
    }

    /**
     * @param $sql
     * @param $params
     *
     * @return mixed
     */
    protected function getResult($sql, $params = [])
    {
        $res = $this->db->query($sql, $params);

        return $res->fetchAll();
    }

    /**
     * Get relation Ids
     *
     * @throws Exception
     * @return array
     */
    public function getRelations()
    {
        $sql = 'SELECT ' . $this->firstKey . ',' . $this->secondKey . ' '
            . 'FROM ' . $this->relationTable;

        return $this->getResult($sql);
    }

    /**
     * Get Merged Parameters from seperate tables
     *
     * @param array $firstTableParams
     * @param array $secondTableParams
     * @param int $count
     * @param int $offset
     * @param null $order
     * @param string $sort
     * @param array $filters
     * @param string $filterType
     * @throws Exception
     * @return false|mixed
     */
    public function getMergedParameters(
        $firstTableParams = [],
        $secondTableParams = [],
        $count = -1,
        $offset = 0,
        $order = null,
        $sort = 'ASC',
        $filters = [],
        $filterType = 'OR'
    ) {
        if (! isset($this->firstObject) || ! isset($this->secondObject)) {
            throw new Exception('Unsupported method on this object');
        }
        $fString = '';
        $sString = '';
        foreach ($firstTableParams as $fparams) {
            if ($fString != '') {
                $fString .= ',';
            }
            $fString .= $this->firstObject->getTableName() . '.' . $fparams;
        }
        foreach ($secondTableParams as $sparams) {
            if ($fString != '' || $sString != '') {
                $sString .= ',';
            }
            $sString .= $this->secondObject->getTableName() . '.' . $sparams;
        }
        $sql = 'SELECT ' . $fString . $sString . ' FROM ' . $this->firstObject->getTableName() . ','
            . $this->secondObject->getTableName() . ',' . $this->relationTable
            . ' WHERE ' . $this->firstObject->getTableName() . '.'
            . $this->firstObject->getPrimaryKey() . ' = ' . $this->relationTable . '.' . $this->firstKey
            . ' AND ' . $this->relationTable . '.' . $this->secondKey . ' = ' . $this->secondObject->getTableName()
            . '.' . $this->secondObject->getPrimaryKey();
        $filterTab = [];
        if (count($filters)) {
            foreach ($filters as $key => $rawvalue) {
                if (is_array($rawvalue)) {
                    $sql .= " {$filterType} {$key} IN (" . str_repeat('?,', count($rawvalue) - 1) . '?) ';
                    $filterTab = array_merge($filterTab, $rawvalue);
                } else {
                    $sql .= " {$filterType} {$key} LIKE ? ";
                    $value = trim($rawvalue);
                    $value = str_replace('\\', '\\\\', $value);
                    $value = str_replace('_', "\_", $value);
                    $value = str_replace(' ', "\ ", $value);
                    $filterTab[] = $value;
                }
            }
        }
        if (isset($order, $sort)   && (strtoupper($sort) == 'ASC' || strtoupper($sort) == 'DESC')) {
            $sql .= " ORDER BY {$order} {$sort} ";
        }
        if (isset($count) && $count != -1) {
            $sql = $this->db->limit($sql, $count, $offset);
        }

        return $this->getResult($sql, $filterTab);
    }

    /**
     * Get target id from source id
     *
     * @param int $sourceKey
     * @param int $targetKey
     * @param array $sourceId
     * @return array
     */
    public function getTargetIdFromSourceId($targetKey, $sourceKey, $sourceId)
    {
        if (! is_array($sourceId)) {
            $sourceId = [$sourceId];
        }
        $sql = "SELECT {$targetKey} FROM {$this->relationTable} WHERE {$sourceKey} = ?";
        $result = $this->getResult($sql, $sourceId);
        $tab = [];
        foreach ($result as $rez) {
            $tab[] = $rez[$targetKey];
        }

        return $tab;
    }

    /**
     * Generic method that allows to retrieve target ids
     * from another another source id
     *
     * @param string $name
     * @param array $args
     * @throws Exception
     * @return array
     */
    public function __call($name, $args = [])
    {
        if (! count($args)) {
            throw new Exception('Missing arguments');
        }
        if (! isset($this->secondKey)) {
            throw new Exception('Not a relation table');
        }
        if (preg_match('/^get([a-zA-Z0-9_]+)From([a-zA-Z0-9_]+)/', $name, $matches)) {
            if (
                ($matches[1] != $this->firstKey && $matches[1] != $this->secondKey)
                || ($matches[2] != $this->firstKey && $matches[2] != $this->secondKey)
            ) {
                throw new Exception('Unknown field');
            }

            return $this->getTargetIdFromSourceId($matches[1], $matches[2], $args);
        }
        if (preg_match('/^delete_([a-zA-Z0-9_]+)/', $name, $matches)) {
            if ($matches[1] == $this->firstKey) {
                $this->delete($args[0]);
            } elseif ($matches[1] == $this->secondKey) {
                $this->delete(null, $args[0]);
            } else {
                throw new Exception('Unknown field');
            }
        } else {
            throw new Exception('Unknown method');
        }
    }

    /**
     * Get First Key
     *
     * @return string
     */
    public function getFirstKey()
    {
        return $this->firstKey;
    }

    /**
     * Get Second Key
     *
     * @return string
     */
    public function getSecondKey()
    {
        return $this->secondKey;
    }
}
