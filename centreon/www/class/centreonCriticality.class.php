<?php
/*
 * Copyright 2005-2012 Centreon
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
 * As a special exception, the copyright holders of this program give Centreon
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of Centreon choice, provided that
 * Centreon also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
 *
 * For more information : contact@centreon.com
 *
 */

/**
 * Class
 *
 * @class CentreonCriticality
 * @description Class for managing criticality object
 */
class CentreonCriticality
{
    /** @var CentreonDB */
    protected $db;
    /** @var array */
    protected $tree;

    /**
     * CentreonCriticality constructor
     *
     * @param CentreonDB $db
     */
    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Get data of a criticality object
     *
     * @param int $critId
     * @param bool $service
     *
     * @return array
     * @throws PDOException
     */
    public function getData($critId, $service = false)
    {
        if ($service === false) {
            return $this->getDataForHosts($critId);
        }
        return $this->getDataForServices($critId);
    }

    /**
     * Get data of a criticality object for hosts
     *
     * @param int $critId
     *
     * @return array
     * @throws PDOException
     */
    public function getDataForHosts($critId)
    {
        static $data = [];
        
        if (!isset($data[$critId])) {
            $sql = "SELECT hc_id, hc_name, level, icon_id, hc_comment
                    FROM hostcategories 
                    WHERE level IS NOT NULL
                    ORDER BY level DESC";
            $res = $this->db->query($sql);
            while ($row = $res->fetchRow()) {
                if (!isset($data[$row['hc_id']])) {
                    $row['name'] = $row['hc_name'];
                    $data[$row['hc_id']] = $row;
                }
            }
        }
        return $data[$critId] ?? null;
    }

    /**
     * Get data of a criticality object for services
     *
     * @param int $critId
     *
     * @return array
     * @throws PDOException
     */
    public function getDataForServices($critId)
    {
        static $data = [];
        
        if (!isset($data[$critId])) {
            $sql = "SELECT sc_id, sc_name, level, icon_id, sc_description
                    FROM service_categories 
                    WHERE level IS NOT NULL
                    ORDER BY level DESC";
            $res = $this->db->query($sql);
            while ($row = $res->fetchRow()) {
                if (!isset($data[$row['sc_id']])) {
                    $row['name'] = $row['sc_name'];
                    $data[$row['sc_id']] = $row;
                }
            }
        }
        return $data[$critId] ?? null;
    }
    
    /**
     * Get list of criticality
     *
     * @param string $searchString
     * @param string $orderBy
     * @param string $sort
     * @param int $offset
     * @param int $limit
     * @paaram bool $service
     * @return array
     */
    public function getList(
        $searchString = null,
        $orderBy = "level",
        $sort = 'ASC',
        $offset = null,
        $limit = null,
        $service = false
    ) {
        if ($service === false) {
            $elements = $this->getListForHosts(
                $searchString,
                $orderBy,
                $sort,
                $offset,
                $limit
            );
        } else {
            $elements = $this->getListForServices(
                $searchString,
                $orderBy,
                $sort,
                $offset,
                $limit
            );
        }
        return $elements;
    }

    /**
     * Get real time host criticality id
     *
     */
    public function getRealtimeHostCriticalityId($db, $hostId)
    {
        static $ids = null;

        if (is_null($ids)) {
            $sql = "SELECT cvs.host_id, cvs.value as criticality
                FROM customvariables cvs 
                WHERE cvs.name='CRITICALITY_ID'
                AND cvs.service_id IS NULL";
            $res = $db->query($sql);
            $ids = [];
            while ($row = $res->fetchRow()) {
                $ids[$row['host_id']] = $row['criticality'];
            }
        }
        return $ids[$hostId] ?? 0;
    }


    /**
     * Get real time service criticality id
     *
     */
    public function getRealtimeServiceCriticalityId($db, $serviceId)
    {
        static $ids = null;

        if (is_null($ids)) {
            $sql = "SELECT cvs.service_id, cvs.value as criticality 
                FROM customvariables cvs 
                WHERE cvs.name='CRITICALITY_ID'
                AND cvs.service_id IS NOT NULL";
            $res = $db->query($sql);
            $ids = [];
            while ($row = $res->fetchRow()) {
                $ids[$row['service_id']] = $row['criticality'];
            }
        }
        return $ids[$serviceId] ?? 0;
    }

    /**
     * Get list of host criticalities
     *
     * @param null $searchString
     * @param string $orderBy
     * @param string $sort
     * @param null $offset
     * @param null $limit
     *
     * @return array
     * @throws PDOException
     */
    protected function getListForHosts(
        $searchString = null,
        $orderBy = "level",
        $sort = 'ASC',
        $offset = null,
        $limit = null
    ) {
        $sql = "SELECT hc_id, hc_name, level, icon_id, hc_comment
                FROM hostcategories 
                WHERE level IS NOT NULL ";
        if (!is_null($searchString) && $searchString != "") {
            $sql .= " AND hc_name LIKE '%".$this->db->escape($searchString)."%' ";
        }
        if (!is_null($orderBy) && !is_null($sort)) {
            $sql .= " ORDER BY $orderBy $sort ";
        }
        if (!is_null($offset) && !is_null($limit)) {
            $sql .= " LIMIT $offset,$limit";
        }
        $res = $this->db->query($sql);
        $elements = [];
        while ($row = $res->fetchRow()) {
            $elements[$row['hc_id']] = [];
            $elements[$row['hc_id']]['hc_name'] = $row['hc_name'];
            $elements[$row['hc_id']]['level'] = $row['level'];
            $elements[$row['hc_id']]['icon_id'] = $row['icon_id'];
            $elements[$row['hc_id']]['comments'] = $row['hc_comment'];
        }
        return $elements;
    }

    /**
     * Get list of service criticalities
     *
     * @param null $searchString
     * @param string $orderBy
     * @param string $sort
     * @param null $offset
     * @param null $limit
     *
     * @return array
     * @throws PDOException
     */
    protected function getListForServices(
        $searchString = null,
        $orderBy = "level",
        $sort = 'ASC',
        $offset = null,
        $limit = null
    ) {
        $sql = "SELECT sc_id, sc_name, level, icon_id, sc_description
                FROM service_categories 
                WHERE level IS NOT NULL ";
        if (!is_null($searchString) && $searchString != "") {
            $sql .= " AND sc_name LIKE '%".$this->db->escape($searchString)."%' ";
        }
        if (!is_null($orderBy) && !is_null($sort)) {
            $sql .= " ORDER BY $orderBy $sort ";
        }
        if (!is_null($offset) && !is_null($limit)) {
            $sql .= " LIMIT $offset,$limit";
        }
        $res = $this->db->query($sql);
        $elements = [];
        while ($row = $res->fetchRow()) {
            $elements[$row['sc_id']] = [];
            $elements[$row['sc_id']]['sc_name'] = $row['sc_name'];
            $elements[$row['sc_id']]['level'] = $row['level'];
            $elements[$row['sc_id']]['icon_id'] = $row['icon_id'];
            $elements[$row['sc_id']]['description'] = $row['sc_description'];
        }
        return $elements;
    }

    /**
     * Create a buffer with all criticality informations
     *
     * @param $service_id
     * return array
     *
     * @return int|mixed
     * @throws PDOException
     */
    public function criticitiesConfigOnSTpl($service_id)
    {
        global $pearDB, $critCache;

        if (!count($this->tree)) {
            $request = "SELECT service_id, service_template_model_stm_id FROM service
                WHERE service_register = '0' AND service_activate = '1' ORDER BY service_template_model_stm_id ASC";
            $RES = $pearDB->query($request);
            while ($data = $RES->fetchRow()) {
                $this->tree[$data['service_id']] = $this->getServiceCriticality($data["service_id"]);
            }
        }
        if (isset($this->tree[$service_id]) && $this->tree[$service_id] != 0) {
            return $this->tree[$service_id];
        }
        return 0;
    }

    /**
     * Get service criticality
     *
     * @param $service_id
     * return array
     *
     * @return int|mixed
     * @throws PDOException
     */
    protected function getServiceCriticality($service_id)
    {
        global $pearDB;

        if (!isset($service_id) || $service_id == 0) {
            return 0;
        }

        $request = "SELECT service_id, service_template_model_stm_id FROM service
            WHERE service_register = '0' AND service_activate = '1'
                AND service_id = $service_id ORDER BY service_template_model_stm_id ASC";
        $RES = $pearDB->query($request);
        if (isset($RES) && $RES->rowCount()) {
            while ($data = $RES->fetchRow()) {
                $request2 = "select sr.* FROM service_categories_relation sr, service_categories sc
                    WHERE sr.sc_id = sc.sc_id
                        AND sr.service_service_id = '".$data['service_id']."'
                        AND sc.level IS NOT NULL";
                $RES2 = $pearDB->query($request2);
                if ($RES2->rowCount() != 0) {
                    $criticity = $RES2->fetchRow();
                    if ($criticity['sc_id'] && isset($criticity['sc_id'])) {
                        return $criticity["sc_id"];
                    } else {
                        return 0;
                    }
                } else {
                    return $this->getServiceCriticality($data["service_template_model_stm_id"]);
                }
            }
        }
        return 0;
    }
}
