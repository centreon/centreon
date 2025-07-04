<?php
/*
 * Copyright 2005-2015 CENTREON
 * Centreon is developped by : Julien Mathis and Romain Le Merlus under
 * GPL Licence 2.0.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation ; either version 2 of the License.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
 * PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, see <http://www.gnu.org/licenses>.
 *
 * Linking this program statically or dynamically with other modules is making a
 * combined work based on this program. Thus, the terms and conditions of the GNU
 * General Public License cover the whole combination.
 *
 * As a special exception, the copyright holders of this program give CENTREON
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of CENTREON choice, provided that
 * CENTREON also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
 *
 * For more information : contact@centreon.com
 *
 */

namespace CentreonClapi;

use Centreon_Object_DependencyServicegroupParent;
use Centreon_Object_Relation_Host_Group_Service;
use Centreon_Object_Relation_Host_Service;
use Centreon_Object_Relation_Service_Group_Host_Group_Service;
use Centreon_Object_Relation_Service_Group_Service;
use Centreon_Object_Service_Group;
use Exception;
use PDOException;
use Pimple\Container;

require_once "centreonObject.class.php";
require_once "centreonConfigurationChange.class.php";
require_once "centreonACL.class.php";
require_once "Centreon/Object/Service/Group.php";
require_once "Centreon/Object/Relation/Host/Service.php";
require_once "Centreon/Object/Relation/Host/Group/Service/Service.php";
require_once "Centreon/Object/Relation/Service/Group/Service.php";
require_once "Centreon/Object/Relation/Service/Group/Host/Group/Service.php";
require_once "Centreon/Object/Dependency/DependencyServicegroupParent.php";

/**
 * Class
 *
 * @class CentreonServiceGroup
 * @package CentreonClapi
 * @description Class for managing Service groups
 */
class CentreonServiceGroup extends CentreonObject
{
    public const ORDER_UNIQUENAME = 0;
    public const ORDER_ALIAS = 1;
    public const INVALID_GEO_COORDS = "Invalid geo coords";

    /** @var string[] */
    public static $aDepends = ['HOST', 'SERVICE'];

    /**
     * CentreonServiceGroup constructor
     *
     * @param Container $dependencyInjector
     *
     * @throws PDOException
     */
    public function __construct(Container $dependencyInjector)
    {
        parent::__construct($dependencyInjector);
        $this->object = new Centreon_Object_Service_Group($dependencyInjector);
        $this->params = ['sg_activate' => '1'];
        $this->insertParams = ['sg_name', 'sg_alias'];
        $this->exportExcludedParams = array_merge($this->insertParams, [$this->object->getPrimaryKey()]);
        $this->action = "SG";
        $this->nbOfCompulsoryParams = count($this->insertParams);
        $this->activateField = "sg_activate";
    }

    /**
     * @param mixed|null $parameters
     * @param array $filters
     *
     * @throws Exception
     */
    public function show($parameters = null, $filters = []): void
    {
        $filters = [];
        if (isset($parameters)) {
            $filters = [$this->object->getUniqueLabelField() => "%" . $parameters . "%"];
        }
        $params = ['sg_id', 'sg_name', 'sg_alias'];
        $paramString = str_replace("sg_", "", implode($this->delim, $params));
        echo $paramString . "\n";
        $elements = $this->object->getList(
            $params,
            -1,
            0,
            null,
            null,
            $filters
        );
        foreach ($elements as $tab) {
            $tab = array_map('html_entity_decode', $tab);
            $tab = array_map(function ($s) {
                return mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1');
            }, $tab);
            echo implode($this->delim, $tab) . "\n";
        }
    }

    /**
     * @param $parameters
     *
     * @return void
     * @throws CentreonClapiException
     * @throws PDOException
     */
    public function initInsertParameters($parameters): void
    {
        $params = explode($this->delim, $parameters);
        if (count($params) < $this->nbOfCompulsoryParams) {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }
        $addParams = [];
        $addParams[$this->object->getUniqueLabelField()] = $this->checkIllegalChar($params[self::ORDER_UNIQUENAME]);
        $addParams['sg_alias'] = $params[self::ORDER_ALIAS];
        $this->params = array_merge($this->params, $addParams);
        $this->checkParameters();
    }

    /**
     * Get a parameter
     *
     * @param string|null $parameters
     * @throws CentreonClapiException
     */
    public function getparam($parameters = null): void
    {
        $params = explode($this->delim, $parameters);
        if (count($params) < 2) {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }
        $authorizeParam = ['alias', 'comment', 'name', 'activate', 'geo_coords'];
        $unknownParam = [];

        if (($objectId = $this->getObjectId($params[self::ORDER_UNIQUENAME])) != 0) {
            $listParam = explode('|', $params[1]);
            $exportedFields = [];
            $resultString = "";
            foreach ($listParam as $paramSearch) {
                $paramString = !$paramString ? $paramSearch : $paramString . $this->delim . $paramSearch;
                $field = $paramSearch;
                if (!in_array($field, $authorizeParam)) {
                    $unknownParam[] = $field;
                } else {
                    switch ($paramSearch) {
                        case "geo_coords":
                            break;
                        default:
                            if (!preg_match("/^sg_/", $paramSearch)) {
                                $field = "sg_" . $paramSearch;
                            }
                            break;
                    }


                    $ret = $this->object->getParameters($objectId, $field);
                    $ret = $ret[$field];

                    if (!isset($exportedFields[$paramSearch])) {
                        $resultString .= $this->csvEscape($ret) . $this->delim;
                        $exportedFields[$paramSearch] = 1;
                    }
                }
            }
        } else {
            throw new CentreonClapiException(self::OBJECT_NOT_FOUND . ":" . $params[self::ORDER_UNIQUENAME]);
        }

        if ($unknownParam !== []) {
            throw new CentreonClapiException(self::OBJECT_NOT_FOUND . ":" . implode('|', $unknownParam));
        }
        echo implode(';', array_unique(explode(';', $paramString))) . "\n";
        echo substr($resultString, 0, -1) . "\n";
    }


    /**
     * @param $parameters
     * @return array
     * @throws CentreonClapiException
     */
    public function initUpdateParameters($parameters)
    {
        $params = explode($this->delim, $parameters);
        if (count($params) < self::NB_UPDATE_PARAMS) {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }

        $objectId = $this->getObjectId($params[self::ORDER_UNIQUENAME]);
        if ($objectId != 0) {
            if (!preg_match("/^sg_/", $params[1]) && $params[1] != "geo_coords") {
                $params[1] = "sg_" . $params[1];
            } elseif ($params[1] === "geo_coords") {
                if (!CentreonUtils::validateGeoCoords($params[2])) {
                    throw new CentreonClapiException(self::INVALID_GEO_COORDS);
                }
            }

            $updateParams = [$params[1] => $params[2]];
            $updateParams['objectId'] = $objectId;
            return $updateParams;
        } else {
            throw new CentreonClapiException(self::OBJECT_NOT_FOUND . ":" . $params[self::ORDER_UNIQUENAME]);
        }
    }

    /**
     * Magic method for get/set/add/del relations
     *
     * @param string $name
     * @param array $arg
     * @throws CentreonClapiException
     */
    public function __call($name, $arg)
    {
        /* Get the method name */
        $name = strtolower($name);
        /* Get the action and the object */
        if (preg_match("/^(get|add|del|set)(service|hostgroupservice)\$/", $name, $matches)) {
            /* Parse arguments */
            if (!isset($arg[0])) {
                throw new CentreonClapiException(self::MISSINGPARAMETER);
            }
            $args = explode($this->delim, $arg[0]);
            $sgIds = $this->object->getIdByParameter($this->object->getUniqueLabelField(), [$args[0]]);
            if (!count($sgIds)) {
                throw new CentreonClapiException(self::OBJECT_NOT_FOUND . ":" . $args[0]);
            }
            $sgId = $sgIds[0];

            if ($matches[2] == "service") {
                $relobj = new Centreon_Object_Relation_Service_Group_Service($this->dependencyInjector);
                $obj = new Centreon_Object_Relation_Host_Service($this->dependencyInjector);
                $existingRelationIds = $relobj->getHostIdServiceIdFromServicegroupId($sgId);
                $hstring = "host_id";
            } else {
                $relobj = new Centreon_Object_Relation_Service_Group_Host_Group_Service($this->dependencyInjector);
                $obj = new Centreon_Object_Relation_Host_Group_Service($this->dependencyInjector);
                $existingRelationIds = $relobj->getHostGroupIdServiceIdFromServicegroupId($sgId);
                $hstring = "hostgroup_id";
            }
            if ($matches[1] == "get") {
                if ($matches[2] == "service") {
                    echo "host id" . $this->delim
                        . "host name" . $this->delim
                        . "service id" . $this->delim
                        . "service description\n";
                } elseif ($matches[2] == "hostgroupservice") {
                    echo "hostgroup id" . $this->delim
                        . "hostgroup name" . $this->delim
                        . "service id" . $this->delim
                        . "service description\n";
                }
                foreach ($existingRelationIds as $val) {
                    if ($matches[2] == "service") {
                        $elements = $obj->getMergedParameters(
                            ['host_name', 'host_id'],
                            ['service_description', 'service_id'],
                            -1,
                            0,
                            "host_name,service_description",
                            "ASC",
                            ["service_id" => $val['service_id'], "host_id" => $val['host_id']],
                            "AND"
                        );
                        if (isset($elements[0])) {
                            echo $elements[0]['host_id'] . $this->delim
                                . $elements[0]['host_name'] . $this->delim
                                . $elements[0]['service_id'] . $this->delim
                                . $elements[0]['service_description'] . "\n";
                        }
                    } else {
                        $elements = $obj->getMergedParameters(
                            ['hg_name', 'hg_id'],
                            ['service_description', 'service_id'],
                            -1,
                            0,
                            "hg_name,service_description",
                            "ASC",
                            ["service_id" => $val['service_id'], "hg_id" => $val['hostgroup_id']],
                            "AND"
                        );
                        if (isset($elements[0])) {
                            echo $elements[0]['hg_id'] . $this->delim
                                . $elements[0]['hg_name'] . $this->delim
                                . $elements[0]['service_id'] . $this->delim
                                . $elements[0]['service_description'] . "\n";
                        }
                    }
                }
            } else {
                if (!isset($args[1])) {
                    throw new CentreonClapiException(self::MISSINGPARAMETER);
                }

                $centreonConfig = new CentreonConfigurationChange($this->dependencyInjector['configuration_db']);
                $hostIds = $centreonConfig->findHostsForConfigChangeFlagFromServiceGroupId($sgId);
                $previousPollerIds = $centreonConfig->findPollersForConfigChangeFlagFromHostIds($hostIds);

                $relation = $args[1];
                $relations = explode("|", $relation);
                $relationTable = [];
                $i = 0;
                foreach ($relations as $rel) {
                    $tmp = explode(",", $rel);
                    if (count($tmp) < 2) {
                        throw new CentreonClapiException(self::MISSINGPARAMETER);
                    }
                    if ($matches[2] == "service") {
                        $elements = $obj->getMergedParameters(
                            ['host_id'],
                            ['service_id'],
                            -1,
                            0,
                            null,
                            null,
                            ["host_name" => $tmp[0], "service_description" => $tmp[1]],
                            "AND"
                        );
                        if (!count($elements)) {
                            throw new CentreonClapiException(self::OBJECT_NOT_FOUND . ":" . $tmp[0] . "/" . $tmp[1]);
                        }
                        $relationTable[$i]['host_id'] = $elements[0]['host_id'];
                        $relationTable[$i]['service_id'] = $elements[0]['service_id'];
                    } elseif ($matches[2] == "hostgroupservice") {
                        $elements = $obj->getMergedParameters(
                            ['hg_id'],
                            ['service_id'],
                            -1,
                            0,
                            null,
                            null,
                            ["hg_name" => $tmp[0], "service_description" => $tmp[1]],
                            "AND"
                        );
                        if (!count($elements)) {
                            throw new CentreonClapiException(self::OBJECT_NOT_FOUND . ":" . $tmp[0] . "/" . $tmp[1]);
                        }
                        $relationTable[$i]['hostgroup_id'] = $elements[0]['hg_id'];
                        $relationTable[$i]['service_id'] = $elements[0]['service_id'];
                    }
                    $i++;
                }
                if ($matches[1] == "set") {
                    foreach ($existingRelationIds as $key => $existrel) {
                        $relobj->delete($sgId, $existrel[$hstring], $existrel['service_id']);
                        unset($existingRelationIds[$key]);
                    }
                }
                foreach ($relationTable as $relation) {
                    if ($matches[1] == "del") {
                        $relobj->delete($sgId, $relation[$hstring], $relation['service_id']);
                    } elseif ($matches[1] == "add" || $matches[1] == "set") {
                        $insert = true;
                        foreach ($existingRelationIds as $existrel) {
                            if (($existrel[$hstring] == $relation[$hstring]) &&
                                $existrel['service_id'] == $relation['service_id']) {
                                $insert = false;
                                break;
                            }
                        }
                        if ($insert == true) {
                            $key = ['hostId' => $relation[$hstring], 'serviceId' => $relation['service_id']];
                            $relobj->insert($sgId, $key);
                        }
                    }
                }

                $centreonConfig->signalConfigurationChange(
                    CentreonConfigurationChange::RESOURCE_TYPE_SERVICEGROUP,
                    $sgId,
                    $previousPollerIds
                );

                $acl = new CentreonACL($this->dependencyInjector);
                $acl->reload(true);
            }
        } else {
            throw new CentreonClapiException(self::UNKNOWN_METHOD);
        }
    }

    /**
     * @param $parameters
     *
     * @return void
     * @throws CentreonClapiException
     */
    public function add($parameters): void
    {
        parent::add($parameters);

        $centreonConfig = new CentreonConfigurationChange($this->dependencyInjector['configuration_db']);
        $servicegroupId = $this->getObjectId($this->params[$this->object->getUniqueLabelField()]);
        $centreonConfig->signalConfigurationChange(
            CentreonConfigurationChange::RESOURCE_TYPE_SERVICEGROUP,
            $servicegroupId
        );
    }

    /**
     * Delete Action
     * Must delete services as well
     *
     * @param string $objectName
     * @return void
     * @throws CentreonClapiException
     */
    public function del($objectName): void
    {
        $sgId = $this->getObjectId($objectName);

        $centreonConfig = new CentreonConfigurationChange($this->dependencyInjector['configuration_db']);
        $hostIds = $centreonConfig->findHostsForConfigChangeFlagFromServiceGroupId($sgId);
        $previousPollerIds = $centreonConfig->findPollersForConfigChangeFlagFromHostIds($hostIds);

        $parentDependency = new Centreon_Object_DependencyServicegroupParent($this->dependencyInjector);
        $parentDependency->removeRelationLastServicegroupDependency($sgId);
        parent::del($objectName);

        $centreonConfig->signalConfigurationChange(
            CentreonConfigurationChange::RESOURCE_TYPE_SERVICEGROUP,
            $sgId,
            $previousPollerIds
        );
    }

    /**
     * @param $parameters
     *
     * @return void
     * @throws CentreonClapiException
     */
    public function setParam($parameters = []): void
    {
        $params = method_exists($this, "initUpdateParameters") ? $this->initUpdateParameters($parameters) : $parameters;
        if (!empty($params)) {
            $servicegroupId = $params['objectId'];

            $centreonConfig = new CentreonConfigurationChange($this->dependencyInjector['configuration_db']);
            $hostIds = $centreonConfig->findHostsForConfigChangeFlagFromServiceGroupId($servicegroupId);
            $previousPollerIds = $centreonConfig->findPollersForConfigChangeFlagFromHostIds($hostIds);

            parent::setparam($parameters);

            $centreonConfig->signalConfigurationChange(
                CentreonConfigurationChange::RESOURCE_TYPE_SERVICEGROUP,
                $servicegroupId,
                $previousPollerIds
            );
        }
    }

    /**
     * @param $objectName
     *
     * @return void
     * @throws CentreonClapiException
     */
    public function enable($objectName): void
    {
        parent::enable($objectName);

        $centreonConfig = new CentreonConfigurationChange($this->dependencyInjector['configuration_db']);
        $servicegroupId = $this->getObjectId($objectName);
        $centreonConfig->signalConfigurationChange(
            CentreonConfigurationChange::RESOURCE_TYPE_SERVICEGROUP,
            $servicegroupId
        );
    }

    /**
     * @param $objectName
     *
     * @return void
     * @throws CentreonClapiException
     */
    public function disable($objectName): void
    {
        parent::disable($objectName);

        $centreonConfig = new CentreonConfigurationChange($this->dependencyInjector['configuration_db']);
        $servicegroupId = $this->getObjectId($objectName);
        $centreonConfig->signalConfigurationChange(
            CentreonConfigurationChange::RESOURCE_TYPE_SERVICEGROUP,
            $servicegroupId,
            [],
            false
        );
    }

    /**
     * Export
     *
     * @param null $filterName
     *
     * @return void
     * @throws Exception
     */
    public function export($filterName = null)
    {
        if (!parent::export($filterName)) {
            return false;
        }

        $labelField = $this->object->getUniqueLabelField();
        $filters = [];
        if (!is_null($filterName)) {
            $filters[$labelField] = $filterName;
        }

        $sgs = $this->object->getList(
            [$this->object->getPrimaryKey(), $labelField],
            -1,
            0,
            $labelField,
            'ASC',
            $filters
        );
        $relobjSvc = new Centreon_Object_Relation_Service_Group_Service($this->dependencyInjector);
        $objSvc = new Centreon_Object_Relation_Host_Service($this->dependencyInjector);
        $relobjHgSvc = new Centreon_Object_Relation_Service_Group_Host_Group_Service($this->dependencyInjector);
        $objHgSvc = new Centreon_Object_Relation_Host_Group_Service($this->dependencyInjector);

        foreach ($sgs as $sg) {
            $sgId = $sg[$this->object->getPrimaryKey()];
            $sgName = $sg[$this->object->getUniqueLabelField()];
            $existingRelationIds = $relobjSvc->getHostIdServiceIdFromServicegroupId($sgId);
            foreach ($existingRelationIds as $val) {
                $elements = $objSvc->getMergedParameters(
                    ['host_name'],
                    ['service_description'],
                    -1,
                    0,
                    "host_name,service_description",
                    "ASC",
                    ["service_id" => $val['service_id'], "host_id" => $val['host_id']],
                    "AND"
                );
                foreach ($elements as $element) {
                    echo $this->action . $this->delim
                        . "addservice" . $this->delim
                        . $sgName . $this->delim
                        . $element['host_name'] . "," . $element['service_description'] . "\n";
                }
            }
            $existingRelationIds = $relobjHgSvc->getHostGroupIdServiceIdFromServicegroupId($sgId);
            foreach ($existingRelationIds as $val) {
                $elements = $objHgSvc->getMergedParameters(
                    ['hg_name'],
                    ['service_description'],
                    -1,
                    0,
                    null,
                    null,
                    ["hg_id" => $val['hostgroup_id'], "service_id" => $val['service_id']],
                    "AND"
                );
                foreach ($elements as $element) {
                    echo $this->action . $this->delim
                        . "addhostgroupservice" . $this->delim
                        . $sgName . $this->delim
                        . $element['hg_name'] . "," . $element['service_description'] . "\n";
                }
            }
        }
    }
}
