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

use Centreon_Object_DependencyHostgroupParent;
use Centreon_Object_Host;
use Centreon_Object_Host_Group;
use Centreon_Object_Relation_Host_Group_Host;
use Centreon_Object_Relation_Host_Group_Service_Group;
use Centreon_Object_Service_Group;
use Exception;
use PDO;
use PDOException;
use Pimple\Container;

require_once "centreonObject.class.php";
require_once "centreonConfigurationChange.class.php";
require_once "centreonACL.class.php";
require_once "centreonHost.class.php";
require_once "Centreon/Object/Host/Group.php";
require_once "Centreon/Object/Host/Host.php";
require_once "Centreon/Object/Service/Group.php";
require_once "Centreon/Object/Service/Service.php";
require_once "Centreon/Object/Relation/Host/Service.php";
require_once "Centreon/Object/Relation/Host/Group/Host.php";
require_once "Centreon/Object/Relation/Host/Group/Service/Service.php";
require_once "Centreon/Object/Relation/Host/Group/Service/Group.php";
require_once "Centreon/Object/Dependency/DependencyHostgroupParent.php";

/**
 * Class
 *
 * @class CentreonHostGroup
 * @package CentreonClapi
 * @description Class for managing host groups
 */
class CentreonHostGroup extends CentreonObject
{
    public const ORDER_UNIQUENAME = 0;
    public const ORDER_ALIAS = 1;
    public const INVALID_GEO_COORDS = "Invalid geo coords";

    /** @var string[] */
    public static $aDepends = ['HOST'];

    /**
     * CentreonHostGroup constructor
     *
     * @param Container $dependencyInjector
     *
     * @throws PDOException
     */
    public function __construct(Container $dependencyInjector)
    {
        parent::__construct($dependencyInjector);
        $this->object = new Centreon_Object_Host_Group($dependencyInjector);
        $this->params = ['hg_activate' => '1'];
        $this->insertParams = ['hg_name', 'hg_alias'];
        $this->exportExcludedParams = array_merge($this->insertParams, [$this->object->getPrimaryKey()]);
        $this->action = "HG";
        $this->nbOfCompulsoryParams = count($this->insertParams);
        $this->activateField = "hg_activate";
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
            $filters = [$this->object->getUniqueLabelField() => "%" . $parameters . "%"];
        }
        $params = ['hg_id', 'hg_name', 'hg_alias'];
        $paramString = str_replace("hg_", "", implode($this->delim, $params));
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
            echo implode($this->delim, $tab) . "\n";
        }
    }

    /**
     * @param null $parameters
     *
     * @return void
     * @throws CentreonClapiException
     * @throws PDOException
     */
    public function initInsertParameters($parameters = null): void
    {
        $params = explode($this->delim, $parameters);
        if (count($params) < $this->nbOfCompulsoryParams) {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }
        $addParams = [];
        $addParams[$this->object->getUniqueLabelField()] = $this->checkIllegalChar($params[self::ORDER_UNIQUENAME]);
        $addParams['hg_alias'] = $params[self::ORDER_ALIAS];
        $this->params = array_merge($this->params, $addParams);
        $this->checkParameters();
    }

    /**
     * @param $parameters
     *
     * @return void
     * @throws Exception
     */
    public function add($parameters): void
    {
        parent::add($parameters);

        $centreonConfig = new CentreonConfigurationChange($this->dependencyInjector['configuration_db']);
        $hostgroupId = $this->getObjectId($this->params[$this->object->getUniqueLabelField()]);
        $centreonConfig->signalConfigurationChange(CentreonConfigurationChange::RESOURCE_TYPE_HOSTGROUP, $hostgroupId);
    }

    /**
     * Del Action
     * Must delete services as well
     *
     * @param string $objectName
     *
     * @return void
     * @throws CentreonClapiException
     * @throws PDOException
     */
    public function del($objectName): void
    {
        $hostgroupId = $this->getObjectId($objectName);

        $centreonConfig = new CentreonConfigurationChange($this->dependencyInjector['configuration_db']);
        $hostIds = $centreonConfig->findHostsForConfigChangeFlagFromHostGroupIds([$hostgroupId]);
        $previousPollerIds = $centreonConfig->findPollersForConfigChangeFlagFromHostIds($hostIds);

        $parentDependency = new Centreon_Object_DependencyHostgroupParent($this->dependencyInjector);
        $parentDependency->removeRelationLastHostgroupDependency($hostgroupId);
        parent::del($objectName);
        $this->db->query(
            "DELETE FROM service WHERE service_register = '1' "
            . "AND service_id NOT IN (SELECT service_service_id FROM host_service_relation)"
        );

        $centreonConfig->signalConfigurationChange(
            CentreonConfigurationChange::RESOURCE_TYPE_HOSTGROUP,
            $hostgroupId,
            $previousPollerIds
        );
    }


    /**
     * @param array $parameters
     * @throws CentreonClapiException
     */
    public function setParam($parameters = []): void
    {
        $params = method_exists($this, "initUpdateParameters") ? $this->initUpdateParameters($parameters) : $parameters;
        if (!empty($params)) {
            $hostgroupId = $params['objectId'];

            $centreonConfig = new CentreonConfigurationChange($this->dependencyInjector['configuration_db']);
            $hostIds = $centreonConfig->findHostsForConfigChangeFlagFromHostGroupIds([$hostgroupId]);
            $previousPollerIds = $centreonConfig->findPollersForConfigChangeFlagFromHostIds($hostIds);

            parent::setparam($parameters);

            $centreonConfig->signalConfigurationChange(
                CentreonConfigurationChange::RESOURCE_TYPE_HOSTGROUP,
                $hostgroupId,
                $previousPollerIds
            );
        }
    }

    /**
     * Enable object
     *
     * @param string $objectName
     *
     * @return void
     * @throws Exception
     */
    public function enable($objectName): void
    {
        parent::enable($objectName);

        $centreonConfig = new CentreonConfigurationChange($this->dependencyInjector['configuration_db']);
        $hostgroupId = $this->getObjectId($objectName);
        $centreonConfig->signalConfigurationChange(CentreonConfigurationChange::RESOURCE_TYPE_HOSTGROUP, $hostgroupId);
    }

    /**
     * Disable object
     *
     * @param string $objectName
     *
     * @return void
     * @throws Exception
     */
    public function disable($objectName): void
    {
        parent::disable($objectName);

        $centreonConfig = new CentreonConfigurationChange($this->dependencyInjector['configuration_db']);
        $hostgroupId = $this->getObjectId($objectName);
        $centreonConfig->signalConfigurationChange(
            CentreonConfigurationChange::RESOURCE_TYPE_HOSTGROUP,
            $hostgroupId,
            [],
            false
        );
    }

    /**
     * Get a parameter
     *
     * @param null $parameters
     * @throws CentreonClapiException
     */
    public function getparam($parameters = null): void
    {
        $params = explode($this->delim, $parameters);
        if (count($params) < 2) {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }
        $authorizeParam = ['alias', 'comment', 'name', 'activate', 'icon_image', 'geo_coords'];
        $unknownParam = [];

        if (($objectId = $this->getObjectId($params[self::ORDER_UNIQUENAME])) != 0) {
            $listParam = explode('|', $params[1]);
            $exportedFields = [];
            $resultString = "";
            $paramString = "";
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
                            if (!preg_match("/^hg_/", $paramSearch)) {
                                $field = "hg_" . $paramSearch;
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
     * @param null $parameters
     *
     * @return array
     * @throws CentreonClapiException
     * @throws PDOException
     */
    public function initUpdateParameters($parameters = null)
    {
        $params = explode($this->delim, $parameters);

        if (count($params) < self::NB_UPDATE_PARAMS) {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }

        $objectId = $this->getObjectId($params[self::ORDER_UNIQUENAME]);
        if ($objectId != 0) {
            if (($params[1] == "icon_image" || $params[1] == "map_icon_image")) {
                $params[2] = $this->getIdIcon($params[2]);
            }
            if (!preg_match("/^hg_/", $params[1]) && $params[1] != "geo_coords") {
                $params[1] = "hg_" . $params[1];
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
     * @param $path
     *
     * @return mixed
     * @throws PDOException
     */
    public function getIdIcon($path)
    {
        $iconData = explode('/', $path);
        $dirStatement = $this->db->prepare("SELECT dir_id FROM view_img_dir WHERE dir_name = :IconData");
        $dirStatement->bindValue(':IconData', $iconData[0], PDO::PARAM_STR);
        $dirStatement->execute();
        $row = $dirStatement->fetch();
        $dirId = $row['dir_id'];

        $imgStatement = $this->db->prepare("SELECT img_id FROM view_img WHERE img_path = :iconData");
        $imgStatement->bindValue(':iconData', $iconData[1], PDO::PARAM_STR);
        $imgStatement->execute();
        $row = $imgStatement->fetch();
        $iconId = $row['img_id'];

        $vidrStatement = $this->db->prepare("SELECT vidr_id FROM view_img_dir_relation " .
            "WHERE dir_dir_parent_id = :dirId AND img_img_id = :iconId");
        $vidrStatement->bindValue(':dirId', (int) $dirId, PDO::PARAM_INT);
        $vidrStatement->bindValue(':iconId', (int) $iconId, PDO::PARAM_INT);
        $vidrStatement->execute();
        $row = $vidrStatement->fetch();
        return $row['vidr_id'];
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
        /* Get the method name */
        $name = strtolower($name);
        /* Get the action and the object */
        if (preg_match("/^(get|set|add|del)(member|host|servicegroup)$/", $name, $matches)) {
            /* Parse arguments */
            if (!isset($arg[0])) {
                throw new CentreonClapiException(self::MISSINGPARAMETER);
            }
            $args = explode($this->delim, $arg[0]);
            $hgIds = $this->object->getIdByParameter($this->object->getUniqueLabelField(), [$args[0]]);
            if (!count($hgIds)) {
                throw new CentreonClapiException(self::OBJECT_NOT_FOUND . ":" . $args[0]);
            }
            $groupId = $hgIds[0];

            if ($matches[2] == "host" || $matches[2] == "member") {
                $relobj = new Centreon_Object_Relation_Host_Group_Host($this->dependencyInjector);
                $obj = new Centreon_Object_Host($this->dependencyInjector);
            } elseif ($matches[2] == "servicegroup") {
                $relobj = new Centreon_Object_Relation_Host_Group_Service_Group($this->dependencyInjector);
                $obj = new Centreon_Object_Service_Group($this->dependencyInjector);
            }
            if ($matches[1] == "get") {
                $tab = $relobj->getTargetIdFromSourceId($relobj->getSecondKey(), $relobj->getFirstKey(), $hgIds);
                echo "id" . $this->delim . "name" . "\n";
                foreach ($tab as $value) {
                    $tmp = $obj->getParameters($value, [$obj->getUniqueLabelField()]);
                    echo $value . $this->delim . $tmp[$obj->getUniqueLabelField()] . "\n";
                }
            } else {
                if (!isset($args[1])) {
                    throw new CentreonClapiException(self::MISSINGPARAMETER);
                }

                $centreonConfig = new CentreonConfigurationChange($this->dependencyInjector['configuration_db']);
                $hostIds = $centreonConfig->findHostsForConfigChangeFlagFromHostGroupIds([$groupId]);
                $previousPollerIds = $centreonConfig->findPollersForConfigChangeFlagFromHostIds($hostIds);

                $relation = $args[1];
                $relations = explode("|", $relation);
                $relationTable = [];
                foreach ($relations as $rel) {
                    $tab = $obj->getIdByParameter($obj->getUniqueLabelField(), [$rel]);
                    if (isset($tab[0]) && $tab[0] != '') {
                        $relationTable[] = $tab[0];
                    } elseif ($rel != '') {
                        throw new CentreonClapiException(self::OBJECT_NOT_FOUND . ":" . $rel);
                    }
                }
                if ($matches[1] == "set") {
                    $relobj->delete($groupId);
                }
                $existingRelationIds = $relobj->getTargetIdFromSourceId(
                    $relobj->getSecondKey(),
                    $relobj->getFirstKey(),
                    [$groupId]
                );
                foreach ($relationTable as $relationId) {
                    if ($matches[1] == "del") {
                        $relobj->delete($groupId, $relationId);
                    } elseif ($matches[1] == "set" || $matches[1] == "add") {
                        if (!in_array($relationId, $existingRelationIds)) {
                            $relobj->insert($groupId, $relationId);
                        }
                    }
                }

                $centreonConfig->signalConfigurationChange(
                    CentreonConfigurationChange::RESOURCE_TYPE_HOSTGROUP,
                    $groupId,
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
     * Export
     *
     * @param null $filterName
     *
     * @return false|void
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
        $relObj = new Centreon_Object_Relation_Host_Group_Host($this->dependencyInjector);
        $hostObj = new Centreon_Object_Host($this->dependencyInjector);
        $hFieldName = $hostObj->getUniqueLabelField();
        $elements = $relObj->getMergedParameters(
            [$labelField],
            [$hFieldName, 'host_id'],
            -1,
            0,
            $labelField,
            'ASC',
            $filters,
            'AND'
        );
        foreach ($elements as $element) {
            echo $this->action . $this->delim
                . "addhost" . $this->delim
                . $element[$labelField] . $this->delim
                . $element[$hFieldName] . "\n";
        }
    }
}
