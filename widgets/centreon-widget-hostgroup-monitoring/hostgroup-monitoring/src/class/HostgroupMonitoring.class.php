<?php

/*
 * Copyright 2005-2020 Centreon
 * Centreon is developed by : Julien Mathis and Romain Le Merlus under
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

class HostgroupMonitoring
{
    protected $dbb;

    /**
     * Constructor
     *
     * @param CentreonDB $dbb
     * @return void
     */
    public function __construct($dbb)
    {
        $this->dbb = $dbb;
    }

    /**
     * Get Host States
     *
     * @param $data | array('name' => '', 'host_state' => array(), 'service_state' => array())
     * @param int $detailFlag
     * @param int $admin
     * @param CentreonACL $aclObj
     * @param array $preferences
     */
    public function getHostStates(&$data, $admin, $aclObj, $preferences, $detailFlag = false)
    {
        if (!count($data)) {
            return array();
        }
        $query = "SELECT h.host_id, h.state, h.name, h.alias, hhg.hostgroup_id, hg.name as hgname
            FROM hosts_hostgroups hhg, hosts h, hostgroups hg
            WHERE h.host_id = hhg.host_id
            AND h.enabled = 1
            AND hhg.hostgroup_id = hg.hostgroup_id
            AND hg.name IN ('" . implode("', '", array_keys($data)) . "') ";
        if (!$admin) {
            $query .= $aclObj->queryBuilder("AND", "h.host_id", $aclObj->getHostsString("ID", $this->dbb));
        }
        $query .= " ORDER BY h.name ";
        $res = $this->dbb->query($query);
        while ($row = $res->fetch()) {
            $k = $row['hgname'];
            if ($detailFlag === true) {
                if (!isset($data[$k]['host_state'][$row['name']])) {
                    $data[$k]['host_state'][$row['name']] = array();
                }
                foreach ($row as $key => $val) {
                    $data[$k]['host_state'][$row['name']][$key] = $val;
                }
            } else {
                if (!isset($data[$k]['host_state'][$row['state']])) {
                    $data[$k]['host_state'][$row['state']] = 0;
                }
                $data[$k]['host_state'][$row['state']]++;
            }
        }
    }

    /**
     * Get Service States
     *
     * @param array $data | array('name' => '', 'host_state' => array(), 'service_state' => array())
     * @param int $detailFlag
     * @param int $admin
     * @param CentreonACL $aclObj
     * @param array $preferences
     */
    public function getServiceStates(&$data, $admin, $aclObj, $preferences, $detailFlag = false)
    {
        if (!count($data)) {
            return array();
        }
        $query = "SELECT DISTINCT
                h.host_id, s.state, h.name, s.service_id, s.description, hhg.hostgroup_id, hg.name as hgname,
                (case s.state when 0 then 3 when 2 then 0 when 3 then 2  when 3 then 2 else s.state END) as tri
            FROM hosts_hostgroups hhg, hosts h, services s, hostgroups hg ";
        if (!$admin) {
            $query .= ", centreon_acl acl ";
        }
        $query .= "WHERE h.host_id = hhg.host_id
            AND hhg.host_id = s.host_id
            AND s.enabled = 1
            AND h.enabled = 1
            AND hhg.hostgroup_id = hg.hostgroup_id
            AND hg.name IN ('" . implode("', '", array_keys($data)) . "') ";
        if (!$admin) {
            $query .= " AND h.host_id = acl.host_id
                AND acl.service_id = s.service_id
                AND acl.group_id IN (" . $aclObj->getAccessGroupsString() . ")";
        }
        $query .= " ORDER BY tri, description ASC";
        $res = $this->dbb->query($query);
        while ($row = $res->fetch()) {
            $k = $row['hgname'];
            if ($detailFlag === true) {
                if (!isset($data[$k]['service_state'][$row['host_id']])) {
                    $data[$k]['service_state'][$row['host_id']] = array();
                }
                if (
                    isset($data[$k]['service_state'][$row['host_id']])
                    && !isset($data[$k]['service_state'][$row['host_id']][$row['service_id']])
                ) {
                    $data[$k]['service_state'][$row['host_id']][$row['service_id']] = array();
                }
                foreach ($row as $key => $val) {
                    $data[$k]['service_state'][$row['host_id']][$row['service_id']][$key] = $val;
                }
            } else {
                if (!isset($data[$k]['service_state'][$row['state']])) {
                    $data[$k]['service_state'][$row['state']] = 0;
                }
                $data[$k]['service_state'][$row['state']]++;
            }
        }
    }
}
