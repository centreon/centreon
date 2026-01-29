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

require_once 'Centreon/Object/Relation/Relation.php';

/**
 * Class
 *
 * @class Centreon_Object_Relation_Service_Template_Host
 */
class Centreon_Object_Relation_Service_Template_Host extends Centreon_Object_Relation
{
    /** @var Centreon_Object_Service_Template */
    public $firstObject;

    /** @var Centreon_Object_Host_Template */
    public $secondObject;

    /** @var string */
    protected $relationTable = 'host_service_relation';

    /** @var string */
    protected $firstKey = 'service_service_id';

    /** @var string */
    protected $secondKey = 'host_host_id';

    /**
     * Centreon_Object_Relation_Service_Template_Host constructor
     *
     * @param Pimple\Container $dependencyInjector
     */
    public function __construct(Pimple\Container $dependencyInjector)
    {
        parent::__construct($dependencyInjector);
        $this->firstObject = new Centreon_Object_Service_Template($dependencyInjector);
        $this->secondObject = new Centreon_Object_Host_Template($dependencyInjector);
    }

    /**
     * Insert host template / host relation
     * Order has importance
     *
     * @param int $fkey
     * @param int $skey
     * @return void
     */
    public function insert($fkey, $skey = null): void
    {
        $sql = "INSERT INTO {$this->relationTable} ({$this->secondKey}, {$this->firstKey}) VALUES (?, ?)";
        $this->db->query($sql, [$fkey, $skey]);
    }

    /**
     * Get Merged Parameters from seperate tables
     *
     * @param array $firstTableParams
     * @param array $secondTableParams
     * @param int $count
     * @param string $order
     * @param string $sort
     * @param array $filters
     * @param string $filterType
     * @param mixed $offset
     *
     * @throws Exception
     * @return array
     */
    public function getMergedParameters($firstTableParams = [], $secondTableParams = [], $count = -1, $offset = 0, $order = null, $sort = 'ASC', $filters = [], $filterType = 'OR')
    {
        if (! isset($this->firstObject) || ! isset($this->secondObject)) {
            throw new Exception('Unsupported method on this object');
        }
        $fString = '';
        $sString = '';
        foreach ($firstTableParams as $fparams) {
            if ($fString != '') {
                $fString .= ',';
            }
            $fString .= 'h.' . $fparams;
        }
        foreach ($secondTableParams as $sparams) {
            if ($fString != '' || $sString != '') {
                $sString .= ',';
            }
            $sString .= 'h2.' . $sparams;
        }
        $sql = 'SELECT ' . $fString . $sString . '
        		FROM ' . $this->firstObject->getTableName() . ' h,' . $this->relationTable . '
        		JOIN ' . $this->secondObject->getTableName() . ' h2 ON ' . $this->relationTable . '.' . $this->firstKey . ' = h2.' . $this->secondObject->getPrimaryKey() . '
        		WHERE h.' . $this->firstObject->getPrimaryKey() . ' = ' . $this->relationTable . '.' . $this->secondKey;
        $filterTab = [];
        if (count($filters)) {
            foreach ($filters as $key => $rawvalue) {
                $sql .= " {$filterType} {$key} LIKE ? ";
                $value = trim($rawvalue);
                $value = str_replace('_', "\_", $value);
                $value = str_replace(' ', "\ ", $value);
                $filterTab[] = $value;
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
     * Delete host template / host relation
     * Order has importance
     *
     * @param int|null $fkey
     * @param int|null $skey
     * @return void
     */
    public function delete($fkey, $skey = null): void
    {
        if (isset($fkey, $skey)) {
            $sql = "DELETE FROM {$this->relationTable} WHERE {$this->firstKey} = ? AND {$this->secondKey} = ?";
            $args = [$skey, $fkey];
        } elseif (isset($skey)) {
            $sql = "DELETE FROM {$this->relationTable} WHERE {$this->firstKey} = ?";
            $args = [$skey];
        } else {
            $sql = "DELETE FROM {$this->relationTable} WHERE {$this->secondKey} = ?";
            $args = [$fkey];
        }
        $this->db->query($sql, $args);
    }
}
