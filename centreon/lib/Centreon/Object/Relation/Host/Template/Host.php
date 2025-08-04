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
 * @class Centreon_Object_Relation_Host_Template_Host
 */
class Centreon_Object_Relation_Host_Template_Host extends Centreon_Object_Relation
{
    /** @var Centreon_Object_Host */
    public $firstObject;

    /** @var Centreon_Object_Host */
    public $secondObject;

    /** @var string */
    protected $relationTable = 'host_template_relation';

    /** @var string */
    protected $firstKey = 'host_tpl_id';

    /** @var string */
    protected $secondKey = 'host_host_id';

    /**
     * Centreon_Object_Relation_Host_Template_Host constructor
     *
     * @param Pimple\Container $dependencyInjector
     */
    public function __construct(Pimple\Container $dependencyInjector)
    {
        parent::__construct($dependencyInjector);
        $this->firstObject = new Centreon_Object_Host($dependencyInjector);
        $this->secondObject = new Centreon_Object_Host($dependencyInjector);
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
        $sql = 'SELECT MAX(`order`) as maxorder FROM ' . $this->relationTable . ' WHERE ' . $this->secondKey . ' = ?';
        $res = $this->db->query($sql, [$skey]);
        $row = $res->fetch();
        $order = 1;
        if (isset($row['maxorder'])) {
            $order = $row['maxorder'] + 1;
        }
        unset($res);
        $sql = "INSERT INTO {$this->relationTable} ({$this->firstKey}, {$this->secondKey}, `order`) VALUES (?, ?, ?)";
        $this->db->query($sql, [$fkey, $skey, $order]);
    }

    /**
     * Delete host template / host relation and linked services
     *
     * @param int $fkey - host template
     * @param int $skey - host
     * @return void
     */
    public function delete($fkey, $skey = null): void
    {
        global $pearDB;
        $this->db->beginTransaction();
        try {
            parent::delete($fkey, $skey);
            $pearDB = $this->db;
            $centreon = true; // Needed so we can include file below
            require_once _CENTREON_PATH_ . '/www/include/configuration/configObject/host/DB-Func.php';
            deleteHostServiceMultiTemplate($skey, $fkey, [], null);
            $this->db->commit();
        } catch (PDOException $e) {
            $this->db->rollBack();
            exitProcess(PROCESS_ID, 1, $e->getMessage());
        }
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
        $sql = "SELECT {$targetKey} FROM {$this->relationTable} WHERE {$sourceKey} = ? ORDER BY `order`";
        $result = $this->getResult($sql, $sourceId);
        $tab = [];
        foreach ($result as $rez) {
            $tab[] = $rez[$targetKey];
        }

        return $tab;
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
}
