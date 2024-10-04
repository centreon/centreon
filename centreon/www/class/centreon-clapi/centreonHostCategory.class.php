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
 *
 */

namespace CentreonClapi;

use Centreon_Object_Host;
use Centreon_Object_Host_Category;
use Centreon_Object_Relation_Host_Category_Host;
use Exception;
use PDO;
use PDOException;
use Pimple\Container;

require_once "centreonObject.class.php";
require_once "centreonSeverityAbstract.class.php";
require_once "centreonACL.class.php";
require_once "Centreon/Object/Host/Host.php";
require_once "Centreon/Object/Host/Category.php";
require_once "Centreon/Object/Relation/Host/Category/Host.php";

/**
 * Class
 *
 * @class CentreonHostCategory
 * @package CentreonClapi
 * @description Class for managing host categories
 */
class CentreonHostCategory extends CentreonSeverityAbstract
{
    /** @var string[] */
    public static $aDepends = ['HOST'];

    /**
     * CentreonHostCategory constructor
     *
     * @param Container $dependencyInjector
     *
     * @throws PDOException
     */
    public function __construct(Container $dependencyInjector)
    {
        parent::__construct($dependencyInjector);
        $this->object = new Centreon_Object_Host_Category($dependencyInjector);
        $this->params = ['hc_activate' => '1'];
        $this->insertParams = ['hc_name', 'hc_alias'];
        $this->exportExcludedParams = array_merge(
            $this->insertParams,
            [$this->object->getPrimaryKey(), 'level', 'icon_id']
        );
        $this->action = "HC";
        $this->nbOfCompulsoryParams = count($this->insertParams);
        $this->activateField = "hc_activate";
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
        $params = ['hc_id', 'hc_name', 'hc_alias', 'level'];
        $paramString = str_replace("hc_", "", implode($this->delim, $params));
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
            if (!$tab['level']) {
                $tab['level'] = 'none';
            }
            echo implode($this->delim, $tab) . "\n";
        }
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
        $addParams['hc_alias'] = $params[self::ORDER_ALIAS];
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
            if (!preg_match("/^hc_/", $params[1])) {
                $params[1] = "hc_" . $params[1];
            }
            $updateParams = [$params[1] => $params[2]];
            $updateParams['objectId'] = $objectId;
            return $updateParams;
        } else {
            throw new CentreonClapiException(self::OBJECT_NOT_FOUND . ":" . $params[self::ORDER_UNIQUENAME]);
        }
    }

    /**
     * Set severity
     *
     * @param string $parameters
     * @throws CentreonClapiException
     */
    public function setseverity($parameters): void
    {
        parent::setseverity($parameters);
    }

    /**
     * Unset severity
     *
     * @param string $parameters
     * @throws CentreonClapiException
     */
    public function unsetseverity($parameters): void
    {
        parent::unsetseverity($parameters);
    }

    /**
     * @param $name
     * @param $arg
     * @throws CentreonClapiException
     */
    public function __call($name, $arg)
    {
        /* Get the method name */
        $name = strtolower($name);
        /* Get the action and the object */
        if (preg_match("/^(get|set|add|del)member$/", $name, $matches)) {
            $relobj = new Centreon_Object_Relation_Host_Category_Host($this->dependencyInjector);
            $obj = new Centreon_Object_Host($this->dependencyInjector);

            /* Parse arguments */
            if (!isset($arg[0])) {
                throw new CentreonClapiException(self::MISSINGPARAMETER);
            }
            $args = explode($this->delim, $arg[0]);
            $hcIds = $this->object->getIdByParameter($this->object->getUniqueLabelField(), [$args[0]]);
            if (!count($hcIds)) {
                throw new CentreonClapiException(self::OBJECT_NOT_FOUND . ":" . $args[0]);
            }
            $categoryId = $hcIds[0];

            if ($matches[1] == "get") {
                $tab = $relobj->getTargetIdFromSourceId($relobj->getSecondKey(), $relobj->getFirstKey(), $hcIds);
                echo "id" . $this->delim . "name" . "\n";
                foreach ($tab as $value) {
                    $tmp = $obj->getParameters($value, [$obj->getUniqueLabelField()]);
                    echo $value . $this->delim . $tmp[$obj->getUniqueLabelField()] . "\n";
                }
            } else {
                if (!isset($args[1])) {
                    throw new CentreonClapiException(self::MISSINGPARAMETER);
                }
                $relation = $args[1];
                $relations = explode("|", $relation);
                $relationTable = [];
                foreach ($relations as $rel) {
                    $tab = $obj->getIdByParameter($obj->getUniqueLabelField(), [$rel]);
                    if (!count($tab)) {
                        throw new CentreonClapiException(self::OBJECT_NOT_FOUND . ":" . $rel);
                    }
                    $relationTable[] = $tab[0];
                }
                if ($matches[1] == "set") {
                    $relobj->delete($categoryId);
                }
                $existingRelationIds = $relobj->getTargetIdFromSourceId(
                    $relobj->getSecondKey(),
                    $relobj->getFirstKey(),
                    [$categoryId]
                );
                foreach ($relationTable as $relationId) {
                    if ($matches[1] == "del") {
                        $relobj->delete($categoryId, $relationId);
                    } elseif ($matches[1] == "set" || $matches[1] == "add") {
                        if (!in_array($relationId, $existingRelationIds)) {
                            $relobj->insert($categoryId, $relationId);
                        }
                    }
                }
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
     * @return void
     * @throws PDOException
     */
    public function export($filterName = null): void
    {
        if (! parent::export($filterName)) {
            return;
        }

        $hostCategories = $this->findHostCategories();
        foreach ($hostCategories as $category) {
            if ($category['host_name'] !== null) {
                printf(
                    "%s%saddmember%s%s%s%s\n",
                    $this->action,
                    $this->delim,
                    $this->delim,
                    $category['name'],
                    $this->delim,
                    $category['host_name']
                );
            }

            if ($category['level'] !== null) {
                printf(
                    "%s%ssetseverity%s%s%s%s%s%s\n",
                    $this->action,
                    $this->delim,
                    $this->delim,
                    $category['name'],
                    $this->delim,
                    $category['level'],
                    $this->delim,
                    $category['img_path'],
                );
            }
        }
    }

    /**
     * @return array<array{name: string, host_name: string, level: int|null, img_path: string|null}>
     * @throws PDOException
     */
    private function findHostCategories(): array
    {
        $statement = $this->db->query(<<<'SQL'
            SELECT hc.hc_name, hc.level, host.host_name,
                   CONCAT(dir.dir_name, '/' ,img.img_path) AS img_path
            FROM hostcategories hc
            LEFT JOIN hostcategories_relation rel
                ON rel.hostcategories_hc_id = hc.hc_id
            LEFT JOIN host
                ON host.host_id = rel.host_host_id
            LEFT JOIN view_img_dir_relation rel2
                ON rel2.img_img_id = hc.icon_id
            LEFT JOIN view_img img
                ON img.img_id = rel2.img_img_id
            LEFT JOIN view_img_dir dir
                ON dir.dir_id = rel2.dir_dir_parent_id
            ORDER BY hc.hc_name
            SQL
        );
        $hostCategories = [];
        while (($result = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            $hostCategories[] = [
                'name' => $result['hc_name'],
                'host_name' => $result['host_name'],
                'level' => $result['level'],
                'img_path' => $result['img_path'],
            ];
        }
        return $hostCategories;
    }
}
