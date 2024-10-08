<?php
/**
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

use Centreon_Object_Acl_Group;
use Centreon_Object_Acl_Menu;
use Centreon_Object_Relation_Acl_Group_Menu;
use CentreonTopology;
use Exception;
use PDOException;
use Pimple\Container;

require_once "centreonObject.class.php";
require_once __DIR__ . "/../../../lib/Centreon/Object/Acl/Group.php";
require_once __DIR__ . "/../../../lib/Centreon/Object/Acl/Menu.php";
require_once __DIR__ . "/../../../lib/Centreon/Object/Relation/Acl/Group/Menu.php";
require_once _CENTREON_PATH_ . "www/class/centreonDB.class.php";
require_once _CENTREON_PATH_ . '/www/class/centreonTopology.class.php';

/**
 * Class
 *
 * @class CentreonACLMenu
 * @package CentreonClapi
 * @description Class for managing ACL Menu rules
 */
class CentreonACLMenu extends CentreonObject
{
    public const ORDER_UNIQUENAME = 0;
    public const ORDER_ALIAS = 1;
    public const LEVEL_1 = 0;
    public const LEVEL_2 = 1;
    public const LEVEL_3 = 2;
    public const LEVEL_4 = 3;

    /** @var Centreon_Object_Relation_Acl_Group_Menu */
    protected $relObject;
    /** @var Centreon_Object_Acl_Group */
    protected $aclGroupObj;
    /** @var CentreonTopology */
    protected $topologyObj;

    /**
     * CentreonACLMenu constructor.
     *
     * @param Container $dependencyInjector
     *
     * @throws PDOException
     */
    public function __construct(Container $dependencyInjector)
    {
        parent::__construct($dependencyInjector);
        $this->object = new Centreon_Object_Acl_Menu($dependencyInjector);
        $this->aclGroupObj = new Centreon_Object_Acl_Group($dependencyInjector);
        $this->relObject = new Centreon_Object_Relation_Acl_Group_Menu($dependencyInjector);
        $this->params = ['acl_topo_activate' => '1'];
        $this->nbOfCompulsoryParams = 2;
        $this->activateField = "acl_topo_activate";
        $this->action = "ACLMENU";
        $this->topologyObj = new CentreonTopology($dependencyInjector['configuration_db']);
    }

    /**
     * @param $parameters
     * @return void
     * @throws CentreonClapiException
     */
    public function initInsertParameters($parameters): void
    {
        $params = explode($this->delim, $parameters);
        if (count($params) < $this->nbOfCompulsoryParams) {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }
        $addParams = [];
        $addParams[$this->object->getUniqueLabelField()] = $params[self::ORDER_UNIQUENAME];
        $addParams['acl_topo_alias'] = $params[self::ORDER_ALIAS];
        $this->params = array_merge($this->params, $addParams);
        $this->checkParameters();
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
            $params[1] = $params[1] == "comment" ? "acl_comments" : "acl_topo_" . $params[1];
            $updateParams = [$params[1] => $params[2]];
            $updateParams['objectId'] = $objectId;
            return $updateParams;
        } else {
            throw new CentreonClapiException(self::OBJECT_NOT_FOUND . ":" . $params[self::ORDER_UNIQUENAME]);
        }
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
        $params = ["acl_topo_id", "acl_topo_name", "acl_topo_alias", "acl_comments", "acl_topo_activate"];
        $paramString = str_replace("acl_topo_", "", implode($this->delim, $params));
        $paramString = str_replace("acl_", "", $paramString);
        $paramString = str_replace("comments", "comment", $paramString);
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
            $str = "";
            foreach ($tab as $key => $value) {
                $str .= $value . $this->delim;
            }
            $str = trim($str, $this->delim) . "\n";
            echo $str;
        }
    }

    /**
     * Split params
     *
     * @param string $parameters
     *
     * @return array
     * @throws CentreonClapiException
     * @throws PDOException
     */
    protected function splitParams($parameters)
    {
        $params = explode($this->delim, $parameters);
        if (count($params) < 3) {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }
        $aclMenuId = $this->object->getIdByParameter($this->object->getUniqueLabelField(), [$params[0]]);
        if (!count($aclMenuId)) {
            throw new CentreonClapiException(self::OBJECT_NOT_FOUND . ":" . $params[0]);
        }
        $processChildren = ($params[1] == '0') ? false : true;
        $levels = [];
        $menus = [];
        $topologies = [];
        $levels[self::LEVEL_1] = $params[2];
        if (isset($params[3])) {
            $levels[self::LEVEL_2] = $params[3];
        }
        if (isset($params[4])) {
            $levels[self::LEVEL_3] = $params[4];
        }
        if (isset($params[5])) {
            $levels[self::LEVEL_4] = $params[5];
        }
        foreach ($levels as $level => $menu) {
            if ($menu) {
                switch ($level) {
                    case self::LEVEL_1:
                        $length = 1;
                        break;
                    case self::LEVEL_2:
                        $length = 3;
                        break;
                    case self::LEVEL_3:
                        $length = 5;
                        break;
                    case self::LEVEL_4:
                        $length = 7;
                        break;
                    default:
                        break;
                }
                if (is_numeric($menu)) {
                    $sql = "SELECT topology_id, topology_page
							FROM topology
							WHERE topology_page = ?
							AND LENGTH(topology_page) = ?";
                    $res = $this->db->query($sql, [$menu, $length]);
                } elseif ($level == self::LEVEL_1) {
                    $sql = "SELECT topology_id, topology_page
                        		FROM topology
                        		WHERE topology_name = ?
                        		AND LENGTH(topology_page) = ?
                        		AND topology_parent IS NULL";
                    $res = $this->db->query($sql, [$menu, $length]);
                } else {
                    $sql = "SELECT topology_id, topology_page
                        		FROM topology
                        		WHERE topology_name = ?
                        		AND LENGTH(topology_page) = ?
                        		AND topology_parent = ?";
                    $res = $this->db->query($sql, [$menu, $length, $topologies[($level - 1)]]);
                }
                $row = $res->fetch();
                if (!isset($row['topology_id'])) {
                    throw new CentreonClapiException(self::OBJECT_NOT_FOUND . ":" . $menu);
                }
                unset($res);
                $menus[$level] = $row['topology_id'];
                $topologies[$level] = $row['topology_page'];
            } else {
                break;
            }
        }
        return [$aclMenuId[0], $menus, $topologies, $processChildren];
    }

    /**
     * Get Acl Group
     *
     * @param string $aclMenuName
     *
     * @return void
     * @throws CentreonClapiException
     */
    public function getaclgroup($aclMenuName): void
    {
        if (!isset($aclMenuName) || !$aclMenuName) {
            throw new CentreonClapiException(self::MISSINGPARAMETER);
        }
        $aclMenuId = $this->object->getIdByParameter($this->object->getUniqueLabelField(), [$aclMenuName]);
        if (!count($aclMenuId)) {
            throw new CentreonClapiException(self::OBJECT_NOT_FOUND . ":" . $aclMenuName);
        }
        $groupIds = $this->relObject->getacl_group_idFromacl_topology_id($aclMenuId[0]);
        echo "id;name" . "\n";
        if (count($groupIds)) {
            foreach ($groupIds as $groupId) {
                $result = $this->aclGroupObj->getParameters($groupId, $this->aclGroupObj->getUniqueLabelField());
                echo $groupId . $this->delim . $result[$this->aclGroupObj->getUniqueLabelField()] . "\n";
            }
        }
    }

    /**
     * Process children of topology
     * Recursive method
     *
     * @param string $action
     * @param null $aclMenuId
     * @param null $parentTopologyId
     *
     * @return void
     * @throws PDOException
     */
    protected function processChildrenOf(
        $action = "grant",
        $aclMenuId = null,
        $parentTopologyId = null
    ) {
        $sql = "SELECT topology_id, topology_page FROM topology WHERE topology_parent = ?";
        $res = $this->db->query($sql, [$parentTopologyId]);
        $rows = $res->fetchAll();
        foreach ($rows as $row) {
            $this->db->query(
                "DELETE FROM acl_topology_relations WHERE acl_topo_id = ? AND topology_topology_id = ?",
                [$aclMenuId, $row['topology_id']]
            );
            if ($action == "grant") {
                $this->db->query(
                    "INSERT INTO acl_topology_relations (acl_topo_id, topology_topology_id) VALUES (?, ?)",
                    [$aclMenuId, $row['topology_id']]
                );
            }
            if ($action == "grantro") {
                $query = "INSERT INTO acl_topology_relations (acl_topo_id, topology_topology_id, access_right) " .
                    "VALUES (?, ?, 2)";
                $this->db->query($query, [$aclMenuId, $row['topology_id']]);
            }
            $this->processChildrenOf($action, $aclMenuId, $row['topology_page']);
        }
    }


    /**
     * old Grant menu
     *
     * @param string $parameters
     * @return void
     */
    public function grant($parameters): void
    {
        $this->grantRw($parameters);
    }

    /**
     * Grant menu
     *
     * @param string $parameters
     *
     * @return void
     * @throws CentreonClapiException
     * @throws PDOException
     */
    public function grantRw($parameters): void
    {
        [$aclMenuId, $menus, $topologies, $processChildren] = $this->splitParams($parameters);
        foreach ($menus as $level => $menuId) {
            $this->db->query(
                "DELETE FROM acl_topology_relations WHERE acl_topo_id = ? AND topology_topology_id = ?",
                [$aclMenuId, $menuId]
            );
            $this->db->query(
                "INSERT INTO acl_topology_relations (acl_topo_id, topology_topology_id) VALUES (?, ?)",
                [$aclMenuId, $menuId]
            );
            if ($processChildren && !isset($menus[$level + 1]) && $level != self::LEVEL_4) {
                $this->processChildrenOf("grant", $aclMenuId, $topologies[$level]);
            }
        }
    }

    /**
     * Grant menu
     *
     * @param string $parameters
     *
     * @return void
     * @throws CentreonClapiException
     * @throws PDOException
     */
    public function grantRo($parameters): void
    {
        [$aclMenuId, $menus, $topologies, $processChildren] = $this->splitParams($parameters);
        foreach ($menus as $level => $menuId) {
            $this->db->query(
                "DELETE FROM acl_topology_relations WHERE acl_topo_id = ? AND topology_topology_id = ?",
                [$aclMenuId, $menuId]
            );
            $this->db->query(
                "INSERT INTO acl_topology_relations (acl_topo_id, topology_topology_id, access_right) VALUES (?, ?, 2)",
                [$aclMenuId, $menuId]
            );
            if ($processChildren && !isset($menus[$level + 1]) && $level != self::LEVEL_4) {
                $this->processChildrenOf("grantro", $aclMenuId, $topologies[$level]);
            }
        }
    }

    /**
     * Revoke menu
     *
     * @param string $parameters
     *
     * @return void
     * @throws CentreonClapiException
     * @throws PDOException
     */
    public function revoke($parameters): void
    {
        [$aclMenuId, $menus, $topologies, $processChildren] = $this->splitParams($parameters);
        foreach ($menus as $level => $menuId) {
            if ($processChildren && !isset($menus[$level + 1])) {
                $this->db->query(
                    "DELETE FROM acl_topology_relations WHERE acl_topo_id = ? AND topology_topology_id = ?",
                    [$aclMenuId, $menuId]
                );
                $this->processChildrenOf("revoke", $aclMenuId, $topologies[$level]);
            }
        }
    }

    /**
     * @param null $filterName
     *
     * @return bool|void
     * @throws Exception
     */
    public function export($filterName = null)
    {
        if (!$this->canBeExported($filterName)) {
            return false;
        }

        $labelField = $this->object->getUniqueLabelField();
        $filters = [];
        if (!is_null($filterName)) {
            $filters[$labelField] = $filterName;
        }
        $aclMenuList = $this->object->getList(
            '*',
            -1,
            0,
            $labelField,
            'ASC',
            $filters
        );

        $exportLine = '';
        foreach ($aclMenuList as $aclMenu) {
            $exportLine .= $this->action . $this->delim . "ADD" . $this->delim
                . $aclMenu['acl_topo_name'] . $this->delim
                . $aclMenu['acl_topo_alias'] . $this->delim . "\n";

            $exportLine .= $this->action . $this->delim .
                "SETPARAM" . $this->delim .
                $aclMenu['acl_topo_name'] . $this->delim;

            if (!empty($aclMenu['acl_comments'])) {
                $exportLine .= 'comment' . $this->delim . $aclMenu['acl_comments'] . $this->delim;
            }

            $exportLine .= 'activate' . $this->delim . $aclMenu['acl_topo_activate'] . $this->delim . "\n";
            $exportLine .= $this->grantMenu($aclMenu['acl_topo_id'], $aclMenu['acl_topo_name']);

            echo $exportLine;
            $exportLine = '';
        }
    }

    /**
     * @param int $aclTopoId
     * @param string $aclTopoName
     *
     * @return string
     * @throws PDOException
     */
    private function grantMenu($aclTopoId, $aclTopoName)
    {
        $grantedMenu = '';

        $grantedMenuTpl = $this->action . $this->delim .
            '%s' . $this->delim .
            $aclTopoName . $this->delim .
            '%s' . $this->delim .
            '%s' . $this->delim . "\n";

        $grantedPossibilities = ['1' => 'GRANTRW', '2' => 'GRANTRO'];

        $queryAclMenuRelations = 'SELECT t.topology_page, t.topology_id, t.topology_name, atr.access_right ' .
            'FROM acl_topology_relations atr ' .
            'LEFT JOIN topology t ON t.topology_id = atr.topology_topology_id ' .
            "WHERE atr.access_right <> '0' " .
            'AND atr.acl_topo_id = :topoId';
        $stmt = $this->db->prepare($queryAclMenuRelations);
        $stmt->bindParam(':topoId', $aclTopoId);
        $stmt->execute();
        $grantedTopologyList = $stmt->fetchAll();

        if (!empty($grantedTopologyList) && isset($grantedTopologyList)) {
            foreach ($grantedTopologyList as $grantedTopology) {
                $grantedTopologyBreadCrumb = $this->topologyObj->getBreadCrumbFromTopology(
                    $grantedTopology['topology_page'],
                    $grantedTopology['topology_name'],
                    ';'
                );
                $grantedMenu .= sprintf(
                    $grantedMenuTpl,
                    $grantedPossibilities[$grantedTopology['access_right']],
                    '0',
                    $grantedTopologyBreadCrumb
                );
            }
        }

        return $grantedMenu;
    }
}
