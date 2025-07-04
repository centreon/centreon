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

use Centreon_Object_Trap;
use Centreon_Object_Trap_Matching;
use Exception;
use PDOException;
use Pimple\Container;

require_once 'centreonObject.class.php';
require_once 'centreonManufacturer.class.php';
require_once 'centreonHost.class.php';
require_once 'centreonService.class.php';
require_once 'Centreon/Object/Trap/Trap.php';
require_once 'Centreon/Object/Trap/Matching.php';
require_once 'Centreon/Object/Relation/Trap/Service.php';

/**
 * Class
 *
 * @class CentreonTrap
 * @package CentreonClapi
 */
class CentreonTrap extends CentreonObject
{
    public const ORDER_UNIQUENAME = 0;
    public const ORDER_OID = 1;
    public const UNKNOWN_STATUS = 'Unknown status';
    public const INCORRECT_PARAMETER = 'Incorrect parameter';

    /** @var string[] */
    public static $aDepends = ['VENDOR'];

    /** @var CentreonManufacturer */
    public $manufacturerObj;

    /**
     * CentreonTrap constructor
     *
     * @param Container $dependencyInjector
     *
     * @throws PDOException
     */
    public function __construct(Container $dependencyInjector)
    {
        parent::__construct($dependencyInjector);
        $this->object = new Centreon_Object_Trap($dependencyInjector);
        $this->manufacturerObj = new CentreonManufacturer($dependencyInjector);
        $this->params = [];
        $this->insertParams = ['traps_name', 'traps_oid'];
        $this->action = 'TRAP';
        $this->nbOfCompulsoryParams = count($this->insertParams);
    }

    /**
     * @param string|null $parameters
     * @throws CentreonClapiException
     * @return void
     */
    public function initInsertParameters($parameters = null): void
    {
        if (is_null($parameters)) {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }
        $params = explode($this->delim, $parameters);
        if (count($params) < $this->nbOfCompulsoryParams) {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }
        $addParams = [];
        $addParams[$this->object->getUniqueLabelField()] = $params[self::ORDER_UNIQUENAME];
        $addParams['traps_oid'] = $params[self::ORDER_OID];
        $this->params = array_merge($this->params, $addParams);
        $this->checkParameters();
    }

    /**
     * Get monitoring status
     *
     * @param string $val
     * @throws CentreonClapiException
     * @return int
     */
    public function getStatusInt($val)
    {
        $val = strtolower($val);
        if (! is_numeric($val)) {
            $statusTab = ['ok' => 0, 'warning' => 1, 'critical' => 2, 'unknown' => 3];
            if (isset($statusTab[$val])) {
                return $statusTab[$val];
            }

            throw new CentreonClapiException(self::UNKNOWN_STATUS . ':' . $val);
        } elseif ($val > 3) {
            throw new CentreonClapiException(self::UNKNOWN_STATUS . ':' . $val);
        }

        return $val;
    }

    /**
     * @param string|null $parameters
     * @throws CentreonClapiException
     * @return array
     */
    public function initUpdateParameters($parameters = null)
    {
        if (is_null($parameters)) {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }
        $params = explode($this->delim, $parameters);
        if (count($params) < self::NB_UPDATE_PARAMS) {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }

        $objectId = $this->getObjectId($params[self::ORDER_UNIQUENAME]);
        if ($objectId != 0) {
            if ($params[1] == 'manufacturer' || $params[1] == 'vendor') {
                $params[1] = 'manufacturer_id';
                $params[2] = $this->manufacturerObj->getId($params[2]);
            } elseif ($params[1] == 'status') {
                $params[1] = 'traps_status';
                $params[2] = $this->getStatusInt($params[2]);
            } elseif ($params[1] == 'output') {
                $params[1] = 'traps_args';
            } elseif ($params[1] == 'matching_mode') {
                $params[1] = 'traps_advanced_treatment';
            } elseif (! preg_match('/^traps_/', $params[1])) {
                $params[1] = 'traps_' . $params[1];
            }
            $params[2] = str_replace('<br/>', "\n", $params[2]);
            $updateParams = [$params[1] => $params[2]];
            $updateParams['objectId'] = $objectId;

            return $updateParams;
        }

        throw new CentreonClapiException(self::OBJECT_NOT_FOUND . ':' . $params[self::ORDER_UNIQUENAME]);
    }

    /**
     * @param string|null $parameters
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
        $params = ['traps_id', 'traps_name', 'traps_oid', 'manufacturer_id'];
        $paramString = str_replace('_', ' ', implode($this->delim, $params));
        $paramString = str_replace('traps ', '', $paramString);
        $paramString = str_replace('manufacturer id', 'manufacturer', $paramString);
        echo $paramString . "\n";
        $elements = $this->object->getList($params, -1, 0, null, null, $filters);
        foreach ($elements as $tab) {
            $str = '';
            foreach ($tab as $key => $value) {
                if ($key == 'manufacturer_id') {
                    $value = $this->manufacturerObj->getName($value);
                }
                $str .= $value . $this->delim;
            }
            $str = trim($str, $this->delim) . "\n";
            echo $str;
        }
    }

    /**
     * Get matching rules
     *
     * @param string|null $parameters
     * @throws CentreonClapiException
     * @return void
     */
    public function getmatching($parameters = null): void
    {
        if (is_null($parameters)) {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }
        $trapId = $this->getObjectId($parameters);
        if (! $trapId) {
            throw new CentreonClapiException(self::OBJECT_NOT_FOUND . ':' . $parameters);
        }
        $matchObj = new Centreon_Object_Trap_Matching($this->dependencyInjector);
        $params = ['tmo_id', 'tmo_string', 'tmo_regexp', 'tmo_status', 'tmo_order'];
        $elements = $matchObj->getList(
            $params,
            -1,
            0,
            'tmo_order',
            'ASC',
            ['trap_id' => $trapId]
        );
        $status = [0 => 'OK', 1 => 'WARNING', 2 => 'CRITICAL', 3 => 'UNKNOWN'];
        echo 'id' . $this->delim . 'string' . $this->delim . 'regexp' . $this->delim
            . 'status' . $this->delim . "order\n";
        foreach ($elements as $element) {
            echo $element['tmo_id'] . $this->delim
                . $element['tmo_string'] . $this->delim
                . $element['tmo_regexp'] . $this->delim
                . $status[$element['tmo_status']] . $this->delim
                . $element['tmo_order'] . "\n";
        }
    }

    /**
     * @param string|null $parameters
     * @throws CentreonClapiException
     */
    public function addmatching($parameters = null): void
    {
        if (is_null($parameters)) {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }
        $params = explode($this->delim, $parameters);
        if (count($params) < 4) {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }
        $trapId = $this->getObjectId($params[0]);
        if (! $trapId) {
            throw new CentreonClapiException(self::OBJECT_NOT_FOUND . ':' . $params[0]);
        }
        $string = $params[1];
        $regexp = $params[2];
        $status = $this->getStatusInt($params[3]);
        $matchObj = new Centreon_Object_Trap_Matching($this->dependencyInjector);
        $elements = $matchObj->getList(
            '*',
            -1,
            0,
            null,
            null,
            ['trap_id' => $trapId, 'tmo_regexp' => $regexp, 'tmo_string' => $string, 'tmo_status' => $status],
            'AND'
        );
        if (! count($elements)) {
            $elements = $matchObj->getList('*', -1, 0, null, null, ['trap_id' => $trapId]);
            $order = count($elements) + 1;
            $matchObj->insert(['trap_id' => $trapId, 'tmo_regexp' => $regexp, 'tmo_string' => $string, 'tmo_status' => $status, 'tmo_order' => $order]);
        }
    }

    /**
     * @param mixed $parameters
     * @throws CentreonClapiException
     */
    public function delmatching($parameters = null): void
    {
        if (is_null($parameters)) {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }
        if (! is_numeric($parameters)) {
            throw new CentreonClapiException('Incorrect id parameters');
        }
        $matchObj = new Centreon_Object_Trap_Matching($this->dependencyInjector);
        $matchObj->delete($parameters);
    }

    /**
     * @param string|null $parameters
     * @throws CentreonClapiException
     */
    public function updatematching($parameters = null): void
    {
        if (is_null($parameters)) {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }
        $params = explode($this->delim, $parameters);
        if (count($params) < 3) {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }
        $matchingId = $params[0];
        if (! is_numeric($matchingId)) {
            throw new CentreonClapiException('Incorrect id parameters');
        }
        $key = $params[1];
        $value = $params[2];
        if ($key == 'trap_id') {
            throw new CentreonClapiException(self::INCORRECT_PARAMETER);
        }
        if (! preg_match('/tmo_/', $key)) {
            $key = 'tmo_' . $key;
        }
        if ($key == 'tmo_status') {
            $value = $this->getStatusInt($value);
        }
        $matchObj = new Centreon_Object_Trap_Matching($this->dependencyInjector);
        $matchObj->update($matchingId, [$key => $value]);
    }

    /**
     * Export
     *
     * @param string|null $filterName
     *
     * @throws Exception
     * @return false|void
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
            $addStr = $this->action . $this->delim . 'ADD';
            foreach ($this->insertParams as $param) {
                $addStr .= $this->delim . $element[$param];
            }
            $addStr .= "\n";
            echo $addStr;
            foreach ($element as $parameter => $value) {
                if ($parameter != 'traps_id') {
                    if (! is_null($value) && $value != '') {
                        $value = str_replace("\n", '<br/>', $value);
                        if ($parameter == 'manufacturer_id') {
                            $parameter = 'vendor';
                            $value = $this->manufacturerObj->getName($value);
                        }
                        $value = CentreonUtils::convertLineBreak($value);
                        echo $this->action . $this->delim
                            . 'setparam' . $this->delim
                            . $element[$this->object->getUniqueLabelField()] . $this->delim
                            . $parameter . $this->delim
                            . $value . "\n";
                    }
                }
            }
            $matchingObj = new Centreon_Object_Trap_Matching($this->dependencyInjector);
            $matchingProps = $matchingObj->getList(
                '*',
                -1,
                0,
                null,
                'ASC',
                ['trap_id' => $element['traps_id']]
            );
            foreach ($matchingProps as $prop) {
                echo $this->action . $this->delim
                    . 'addmatching' . $this->delim
                    . $element['traps_name'] . $this->delim
                    . $prop['tmo_string'] . $this->delim
                    . $prop['tmo_regexp'] . $this->delim
                    . $prop['tmo_status'] . "\n";
            }
        }
    }
}
