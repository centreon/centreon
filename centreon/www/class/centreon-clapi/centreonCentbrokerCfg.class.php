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

namespace CentreonClapi;

use Centreon_Object_Broker;
use CentreonConfigCentreonBroker;
use Exception;
use PDOException;
use Pimple\Container;

require_once 'centreonObject.class.php';
require_once 'centreonInstance.class.php';
require_once 'Centreon/Object/Broker/Broker.php';

require_once _CENTREON_PATH_ . 'www/class/centreonDB.class.php';
require_once _CENTREON_PATH_ . 'www/class/centreonConfigCentreonBroker.php';

/**
 * Class
 *
 * @class CentreonCentbrokerCfg
 * @package CentreonClapi
 */
class CentreonCentbrokerCfg extends CentreonObject
{
    public const ORDER_UNIQUENAME = 0;
    public const ORDER_INSTANCE = 1;
    public const UNKNOWNCOMBO = 'Unknown combination';
    public const INVALIDFIELD = 'Invalid field';
    public const NOENTRYFOUND = 'No entry found';

    /** @var string[] */
    public static $aDepends = ['INSTANCE'];

    /** @var CentreonInstance */
    protected $instanceObj;

    /** @var CentreonConfigCentreonBroker */
    protected $brokerObj;

    /**
     * CentreonCentbrokerCfg constructor
     *
     * @param Container $dependencyInjector
     *
     * @throws PDOException
     */
    public function __construct(Container $dependencyInjector)
    {
        parent::__construct($dependencyInjector);
        $this->instanceObj = new CentreonInstance($dependencyInjector);
        $this->brokerObj = new CentreonConfigCentreonBroker($dependencyInjector['configuration_db']);
        $this->object = new Centreon_Object_Broker($dependencyInjector);
        $this->params = ['config_filename' => 'central-broker.json', 'config_activate' => '1'];
        $this->insertParams = ['name', 'ns_nagios_server'];
        $this->action = 'CENTBROKERCFG';
        $this->nbOfCompulsoryParams = count($this->insertParams);
        $this->activateField = 'config_activate';
    }

    /**
     * Magic method
     *
     * @param $name
     * @param $arg
     * @throws CentreonClapiException
     */
    public function __call($name, $arg)
    {
        // Get the method name
        $name = strtolower($name);

        // Get the action and the object
        if (preg_match('/^(list|get|set|add|del)(input|output)/', $name, $matches)) {
            $tagName = $matches[2];

            // Parse arguments
            if (! isset($arg[0])) {
                throw new CentreonClapiException(self::MISSINGPARAMETER);
            }
            $args = explode($this->delim, $arg[0]);
            $configIds = $this->object->getIdByParameter($this->object->getUniqueLabelField(), [$args[0]]);
            if (! count($configIds)) {
                throw new CentreonClapiException(self::OBJECT_NOT_FOUND . ':' . $args[0]);
            }

            $configId = $configIds[0];

            switch ($matches[1]) {
                case 'list':
                    $this->listFlow($configId, $tagName, $args);
                    break;
                case 'get':
                    $this->getFlow($configId, $tagName, $args);
                    break;
                case 'set':
                    $this->setFlow($configId, $tagName, $args);
                    break;
                case 'add':
                    $this->addFlow($configId, $tagName, $args);
                    break;
                case 'del':
                    $this->delFlow($configId, $tagName, $args);
                    break;
            }
        } else {
            throw new CentreonClapiException(self::UNKNOWN_METHOD);
        }
    }

    /**
     * @param $parameters
     * @throws CentreonClapiException
     * @return void
     */
    public function initInsertParameters($parameters): void
    {
        $params = explode($this->delim, $parameters);
        if (count($params) < $this->nbOfCompulsoryParams) {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }
        $addParams = [];
        $addParams[$this->object->getUniqueLabelField()] = $params[self::ORDER_UNIQUENAME];
        $addParams['ns_nagios_server'] = $this->instanceObj->getInstanceId($params[self::ORDER_INSTANCE]);
        $this->params = array_merge($this->params, $addParams);
        $this->checkParameters();
    }

    /**
     * @param $parameters
     * @throws CentreonClapiException
     * @return array
     */
    public function initUpdateParameters($parameters)
    {
        $params = explode($this->delim, $parameters);
        if (count($params) < self::NB_UPDATE_PARAMS) {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }

        $objectId = $this->getObjectId($params[self::ORDER_UNIQUENAME]);
        if ($objectId != 0) {
            if ($params[1] == 'instance' || $params[1] == 'ns_nagios_server') {
                $params[1] = 'ns_nagios_server';
                $params[2] = $this->instanceObj->getInstanceId($params[2]);
            } elseif (! preg_match('/^config_/', $params[1])) {
                $parametersWithoutPrefix = [
                    'event_queue_max_size',
                    'event_queues_total_size',
                    'cache_directory',
                    'stats_activate',
                    'daemon',
                    'pool_size',
                    'command_file',
                    'log_directory',
                    'log_filename',
                ];
                if (! in_array($params[1], $parametersWithoutPrefix)) {
                    $params[1] = 'config_' . $params[1];
                }
            }
            $updateParams = [$params[1] => $params[2]];
            $updateParams['objectId'] = $objectId;

            return $updateParams;
        }

        throw new CentreonClapiException(self::OBJECT_NOT_FOUND . ':' . $params[self::ORDER_UNIQUENAME]);
    }

    /**
     * @param null $parameters
     * @param array $filters
     *
     * @throws Exception
     */
    public function show($parameters = null, $filters = []): void
    {
        $filters = [];
        if (isset($parameters)) {
            $filters = [$this->object->getUniqueLabelField() => '%' . $parameters . '%'];
        }
        $params = ['config_id', 'config_name', 'ns_nagios_server'];
        $paramString = str_replace('_', ' ', implode($this->delim, $params));
        $paramString = str_replace('ns nagios server', 'instance', $paramString);
        echo $paramString . "\n";
        $elements = $this->object->getList($params, -1, 0, null, null, $filters);
        foreach ($elements as $tab) {
            $str = '';
            foreach ($tab as $key => $value) {
                if ($key == 'ns_nagios_server') {
                    $value = $this->instanceObj->getInstanceName($value);
                }
                $str .= $value . $this->delim;
            }
            $str = trim($str, $this->delim) . "\n";
            echo $str;
        }
    }

    /**
     * Get list from tag
     *
     * @param string $tagName
     *
     * @throws CentreonClapiException
     * @throws PDOException
     */
    public function getTypeList($tagName = ''): void
    {
        if ($tagName == '') {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }

        $sql = 'SELECT ct.cb_type_id, ct.type_shortname, ct.type_name
        		FROM cb_tag_type_relation cttr, cb_type ct, cb_tag ca
        		WHERE ct.cb_type_id = cttr.cb_type_id
        		AND cttr.cb_tag_id = ca.cb_tag_id
        		AND ca.tagname = ?
        		ORDER BY ct.type_name';
        $res = $this->db->query($sql, [$tagName]);
        $rows = $res->fetchAll();
        if (! count($rows)) {
            throw new CentreonClapiException(self::NOENTRYFOUND . ' for ' . $tagName);
        }
        echo 'type id' . $this->delim . 'short name' . $this->delim . "name\n";
        foreach ($rows as $row) {
            echo $row['cb_type_id'] . $this->delim . $row['type_shortname'] . $this->delim . $row['type_name'] . "\n";
        }
    }

    /**
     * User help method
     * Get Field list from Type
     *
     * @param $typeName
     *
     * @throws CentreonClapiException
     * @throws PDOException
     */
    public function getFieldList($typeName): void
    {
        if ($typeName == '') {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }
        $sql = 'SELECT f.cb_field_id, f.fieldname, f.displayname, f.fieldtype
        		FROM cb_type_field_relation tfr, cb_field f, cb_type ct
        		WHERE ct.cb_type_id = tfr.cb_type_id
        		AND tfr.cb_field_id = f.cb_field_id
        		AND ct.type_shortname = ?
        		ORDER BY f.fieldname';
        $res = $this->db->query($sql, [$typeName]);
        $rows = $res->fetchAll();
        if (! count($rows)) {
            throw new CentreonClapiException(self::NOENTRYFOUND . ' for ' . $typeName);
        }
        echo 'field id' . $this->delim . 'short name' . $this->delim . "name\n";
        foreach ($rows as $row) {
            echo $row['cb_field_id'] . $this->delim . $row['fieldname'];
            if ($row['fieldtype'] == 'select' || $row['fieldtype'] == 'multiselect') {
                echo '*';
            }
            echo $this->delim . $row['displayname'] . $this->delim . $row['fieldtype'] . "\n";
        }
    }

    /**
     * User help method
     * Get Value list from Selectbox name
     *
     * @param $selectName
     *
     * @throws CentreonClapiException
     * @throws PDOException
     */
    public function getValueList($selectName): void
    {
        if ($selectName == '') {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }
        $sql = 'SELECT value_value
        		FROM cb_list_values lv, cb_list l, cb_field f
        		WHERE lv.cb_list_id = l.cb_list_id
        		AND l.cb_field_id = f.cb_field_id
        		AND f.fieldname = ?
        		ORDER BY lv.value_value';
        $res = $this->db->query($sql, [$selectName]);
        $rows = $res->fetchAll();
        if (! count($rows)) {
            throw new CentreonClapiException(self::NOENTRYFOUND . ' for ' . $selectName);
        }
        echo "possible values\n";
        foreach ($rows as $row) {
            echo $row['value_value'] . "\n";
        }
    }

    /**
     * @param null $filterName
     *
     * @throws PDOException
     * @return bool|void
     */
    public function export($filterName = null)
    {
        if (! $this->canBeExported($filterName)) {
            return false;
        }

        $labelField = $this->object->getUniqueLabelField();
        $filters = [];
        if (! is_null($filterName)) {
            $filters[$labelField] = $filterName;
        }
        $elements = $this->object->getList(
            '*',
            -1,
            0,
            $labelField,
            'ASC',
            $filters,
            'AND'
        );
        foreach ($elements as $element) {
            $addStr = $this->action . $this->delim . 'ADD'
                . $this->delim . $element['config_name']
                . $this->delim . $this->instanceObj->getInstanceName($element['ns_nagios_server']);
            echo $addStr . "\n";
            echo $this->action . $this->delim
                . 'SETPARAM' . $this->delim
                . $element['config_name'] . $this->delim
                . 'filename' . $this->delim
                . $element['config_filename'] . "\n";
            echo $this->action . $this->delim
                . 'SETPARAM' . $this->delim
                . $element['config_name'] . $this->delim
                . 'cache_directory' . $this->delim
                . $element['cache_directory'] . "\n";
            echo $this->action . $this->delim
                . 'SETPARAM' . $this->delim
                . $element['config_name'] . $this->delim
                . 'stats_activate' . $this->delim
                . $element['stats_activate'] . "\n";
            echo $this->action . $this->delim
                . 'SETPARAM' . $this->delim
                . $element['config_name'] . $this->delim
                . 'daemon' . $this->delim
                . $element['daemon'] . "\n";
            $poolSize = empty($element['pool_size']) ? '' : $element['pool_size'];
            echo $this->action . $this->delim
                . 'SETPARAM' . $this->delim
                . $element['config_name'] . $this->delim
                . 'pool_size' . $this->delim
                . $poolSize . "\n";
            echo $this->action . $this->delim
                . 'SETPARAM' . $this->delim
                . $element['config_name'] . $this->delim
                . 'event_queues_total_size' . $this->delim
                . ($element['event_queues_total_size'] ?? '') . "\n";
            $sql = 'SELECT id, tag, type_name, name, parameters
                    FROM cfg_broker_input_output
                    WHERE config_id = ?
                    ORDER BY tag, id';
            $res = $this->db->query($sql, [$element['config_id']]);
            $resultSet = $res->fetchAll();
            unset($res);
            foreach ($resultSet as $flowRow) {
                $tagUpper = strtoupper($flowRow['tag']);
                $params = json_decode($flowRow['parameters'], true) ?? [];

                echo $this->action . $this->delim . 'ADD' . $tagUpper
                    . $this->delim . $element['config_name']
                    . $this->delim . $flowRow['name']
                    . $this->delim . $flowRow['type_name'] . "\n";

                foreach ($params as $paramKey => $paramValue) {
                    if ($paramKey === 'filters_category') {
                        $converted = implode(',', (array) $paramValue);
                        echo $this->action . $this->delim . 'SET' . $tagUpper
                            . $this->delim . $element['config_name']
                            . $this->delim . $flowRow['id']
                            . $this->delim . 'category'
                            . $this->delim . $converted . "\n";
                        continue;
                    }
                    if (is_array($paramValue)) {
                        continue;
                    }
                    $paramValue = CentreonUtils::convertLineBreak($paramValue);
                    if ($paramValue !== '') {
                        echo $this->action . $this->delim . 'SET' . $tagUpper
                            . $this->delim . $element['config_name']
                            . $this->delim . $flowRow['id']
                            . $this->delim . $paramKey
                            . $this->delim . $paramValue . "\n";
                    }
                }
            }
        }
    }

    /**
     * get list of multi select fields
     *
     * @throws PDOException
     * @return array
     */
    protected function getMultiselect()
    {
        $sql = "SELECT f.cb_fieldgroup_id, fieldname, groupname
            FROM cb_field f, cb_fieldgroup fg
            WHERE f.cb_fieldgroup_id = fg.cb_fieldgroup_id
            AND f.fieldtype = 'multiselect'";
        $res = $this->db->query($sql);
        $arr = [];
        while ($row = $res->fetch()) {
            $arr[$row['fieldname']]['groupid'] = $row['cb_fieldgroup_id'];
            $arr[$row['fieldname']]['groupname'] = $row['groupname'];
        }

        return $arr;
    }

    /**
     * Get block id
     *
     * @param string $tagName
     * @param string $typeName
     *
     * @throws CentreonClapiException
     * @throws PDOException
     * @return string
     */
    protected function getBlockId($tagName, $typeName)
    {
        $sql = 'SELECT cttr.cb_tag_id, cttr.cb_type_id
        		FROM cb_tag, cb_type, cb_tag_type_relation cttr
        		WHERE cb_tag.cb_tag_id = cttr.cb_tag_id
        		AND cttr.cb_type_id = cb_type.cb_type_id
        		AND cb_tag.tagname = ?
        		AND cb_type.type_shortname = ?';
        $res = $this->db->query($sql, [$tagName, $typeName]);
        $row = $res->fetch();
        if (! isset($row['cb_type_id']) || ! isset($row['cb_tag_id'])) {
            throw new CentreonClapiException(self::UNKNOWNCOMBO . ': ' . $tagName . '/' . $typeName);
        }

        return $row['cb_tag_id'] . '_' . $row['cb_type_id'];
    }

    /**
     * Checks if field is valid
     *
     * @param int $configId
     * @param string $tagName
     * @param array $args | index 1 => config group id, 2 => config_key, 3 => config_value
     *
     * @throws PDOException
     * @return bool
     */
    protected function fieldIsValid($configId, $tagName, $args)
    {
        $sql = 'SELECT type_id FROM cfg_broker_input_output WHERE config_id = ? AND id = ? AND tag = ?';
        $res = $this->db->query($sql, [$configId, $args[1], $tagName]);
        $row = $res->fetch();
        unset($res);
        if (! isset($row['type_id'])) {
            return false;
        }

        $typeId = $row['type_id'];
        $sql = 'SELECT fieldtype, cf.cb_field_id, ct.cb_module_id
        		FROM cb_type_field_relation ctfr, cb_field cf, cb_type ct
        		WHERE ctfr.cb_field_id = cf.cb_field_id
        		AND ctfr.cb_type_id = ct.cb_type_id
        		AND cf.fieldname = ?
        		AND ctfr.cb_type_id = ?';
        $res = $this->db->query($sql, [$args[2], $typeId]);
        $row = $res->fetch();
        unset($res);
        if (! isset($row['fieldtype'])) {
            $sql = 'SELECT fieldtype, cf.cb_field_id, ct.cb_module_id
        			FROM cb_type_field_relation ctfr, cb_field cf, cb_type ct
        			WHERE ctfr.cb_field_id = cf.cb_field_id
        			AND ctfr.cb_type_id = ct.cb_type_id
        			AND ctfr.cb_type_id = ?';
            $res = $this->db->query($sql, [$typeId]);
            $rows = $res->fetchAll();
            unset($res);
            $found = false;
            foreach ($rows as $row) {
                $sql = 'SELECT fieldtype, cf.cb_field_id
    					FROM cb_module_relation cmr, cb_type ct, cb_type_field_relation ctfr, cb_field cf
                        WHERE cmr.cb_module_id = ?
                        AND cf.fieldname = ?
                        AND cmr.inherit_config = 1
                        AND cmr.module_depend_id = ct.cb_module_id
                        AND ct.cb_type_id = ctfr.cb_type_id
                        AND ctfr.cb_field_id = cf.cb_field_id
                        ORDER BY fieldname';
                $res = $this->db->query($sql, [$row['cb_module_id'], $args[2]]);
                $row = $res->fetch();
                if (isset($row['fieldtype'])) {
                    $found = true;
                    break;
                }
                unset($res);
            }
            if ($found == false) {
                return false;
            }
        }
        if ($row['fieldtype'] != 'select' && $row['fieldtype'] != 'multiselect') {
            return true;
        }
        if ($row['fieldtype'] == 'select') {
            $sql = 'SELECT value_value
        	    FROM cb_list cl, cb_list_values clv, cb_field cf
        	    WHERE cl.cb_list_id = clv.cb_list_id
        		AND cl.cb_field_id = cf.cb_field_id
            	AND cf.cb_field_id = ?
            	AND cf.fieldname = ?
            	AND clv.value_value = ?';
            $res = $this->db->query($sql, [$row['cb_field_id'], $args[2], $args[3]]);
            $row = $res->fetch();
            if (! isset($row['value_value'])) {
                return false;
            }
        } else {
            $vals = explode(',', $args[3]);
            $sql = 'SELECT value_value
        	    FROM cb_list cl, cb_list_values clv, cb_field cf
        	    WHERE cl.cb_list_id = clv.cb_list_id
        		AND cl.cb_field_id = cf.cb_field_id
            	AND cf.cb_field_id = ?
            	AND cf.fieldname = ?';
            $res = $this->db->query($sql, [$row['cb_field_id'], $args[2]]);
            $allowedValues = [];
            while ($row = $res->fetch()) {
                $allowedValues[] = $row['value_value'];
            }
            foreach ($vals as $v) {
                if (! in_array($v, $allowedValues)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * List flows
     *
     * @param $configId
     * @param $tagName
     * @param $args
     *
     * @throws PDOException
     */
    private function listFlow($configId, $tagName, $args): void
    {
        $query = 'SELECT id, name FROM cfg_broker_input_output '
            . 'WHERE config_id = ? AND tag = ? ORDER BY id';
        $res = $this->db->query($query, [$configId, $tagName]);

        echo "id;name\n";
        while ($row = $res->fetch()) {
            echo $row['id'] . $this->delim . $row['name'] . "\n";
        }
    }

    /**
     * Get flow parameters
     *
     * @param $configId
     * @param $tagName
     * @param $args
     *
     * @throws CentreonClapiException
     * @throws PDOException
     */
    private function getFlow($configId, $tagName, $args): void
    {
        if (! isset($args[1]) || $args[1] == '') {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }

        $query = 'SELECT type_name, name, parameters FROM cfg_broker_input_output '
            . 'WHERE config_id = ? AND id = ? AND tag = ?';
        $res = $this->db->query($query, [$configId, $args[1], $tagName]);
        $row = $res->fetch();
        if (! $row) {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }
        $params = json_decode($row['parameters'], true) ?? [];

        echo "parameter key;parameter value\n";
        echo 'type' . $this->delim . $row['type_name'] . "\n";
        echo 'name' . $this->delim . $row['name'] . "\n";
        if (isset($params['filters_category'])) {
            echo 'category' . $this->delim . implode(',', (array) $params['filters_category']) . "\n";
            unset($params['filters_category']);
        }
        foreach ($params as $key => $value) {
            if (is_array($value)) {
                continue;
            }
            echo $key . $this->delim . $value . "\n";
        }
    }

    /**
     * Set flow parameter
     *
     * @param $configId
     * @param $tagName
     * @param $args
     *
     * @throws CentreonClapiException
     * @throws PDOException
     */
    private function setFlow($configId, $tagName, $args): void
    {
        if (! isset($args[3])) {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }

        if ($this->fieldIsValid($configId, $tagName, $args) == false) {
            throw new CentreonClapiException(self::INVALIDFIELD);
        }

        $res = $this->db->query(
            'SELECT parameters FROM cfg_broker_input_output WHERE config_id = ? AND id = ? AND tag = ?',
            [$configId, $args[1], $tagName]
        );
        $row = $res->fetch();
        if (! $row) {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }
        $params = json_decode($row['parameters'], true) ?? [];

        if ($args[2] === 'category') {
            $params['filters_category'] = explode(',', $args[3]);
        } else {
            $multiselect = $this->getMultiselect();
            $params[$args[2]] = isset($multiselect[$args[2]]) ? explode(',', $args[3]) : $args[3];
        }

        $this->db->query(
            'UPDATE cfg_broker_input_output SET parameters = ? WHERE config_id = ? AND id = ? AND tag = ?',
            [json_encode($params), $configId, $args[1], $tagName]
        );
    }

    /**
     * Add flow
     *
     * @param $configId
     * @param $tagName
     * @param $args
     *
     * @throws CentreonClapiException
     * @throws PDOException
     */
    private function addFlow($configId, $tagName, $args): void
    {
        if (! isset($args[2])) {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }

        $cbTypeId = $this->brokerObj->getTypeId($args[2]);
        if (is_null($cbTypeId)) {
            throw new CentreonClapiException(self::UNKNOWNPARAMETER);
        }

        $fields = $this->brokerObj->getBlockInfos($cbTypeId);

        $defaultValues = [];
        foreach ($fields as $field) {
            if ($field['required'] === 0) {
                continue;
            }
            if (is_null($field['value'])) {
                $field['value'] = $this->brokerObj->getDefaults($field['id']);
            }
            if (is_null($field['value'])) {
                $field['value'] = '';
            }

            if ($field['group_name'] !== null) {
                $defaultValues[$field['group_name']][] = [$field['fieldname'] => $field['value']];
            } else {
                $defaultValues[$field['fieldname']] = $field['value'];
            }
        }

        // Check name uniqueness
        $res = $this->db->query(
            'SELECT id FROM cfg_broker_input_output WHERE config_id = ? AND tag = ? AND name = ?',
            [$configId, $tagName, $args[1]]
        );
        if ($res->fetch()) {
            throw new CentreonClapiException(self::OBJECTALREADYEXISTS);
        }
        unset($res);

        $blockId = $this->getBlockId($tagName, $args[2]);
        [, $typeId] = explode('_', $blockId);
        unset($defaultValues['name']);

        $this->db->query(
            'INSERT INTO cfg_broker_input_output (config_id, tag, type_id, type_name, name, parameters) '
            . 'VALUES (?, ?, ?, ?, ?, ?)',
            [$configId, $tagName, (int) $typeId, $args[2], $args[1], json_encode($defaultValues)]
        );
    }

    /**
     * Remove flow
     *
     * @param $configId
     * @param $tagName
     * @param $args
     *
     * @throws CentreonClapiException
     * @throws PDOException
     */
    private function delFlow($configId, $tagName, $args): void
    {
        if (! isset($args[1]) || $args[1] == '') {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }

        $this->db->query(
            'DELETE FROM cfg_broker_input_output WHERE config_id = ? AND id = ? AND tag = ?',
            [$configId, $args[1], $tagName]
        );
    }
}
