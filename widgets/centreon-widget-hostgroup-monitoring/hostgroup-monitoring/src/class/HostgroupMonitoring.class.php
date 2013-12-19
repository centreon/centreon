<?php
/**
 * Copyright 2005-2011 MERETHIS
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
 * As a special exception, the copyright holders of this program give MERETHIS
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of MERETHIS choice, provided that
 * MERETHIS also meet, for each linked independent module, the terms  and conditions
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
     * @param bool $isNdo
     * @param string $ndoPrefix
     */
    public function getHostStates(&$data, $detailFlag = false, $admin, $aclObj, $preferences, $isNdo = false, $ndoPrefix = "nagios_")
    {
        if (!count($data)) {
            return array();
        }
        if ($isNdo == false) {
            $query = "SELECT h.host_id, h.state, h.name, hhg.hostgroup_id, hg.name as hgname
                    FROM hosts_hostgroups hhg, hosts h, hostgroups hg
                    WHERE h.host_id = hhg.host_id
                    AND h.enabled = 1
                    AND hhg.hostgroup_id = hg.hostgroup_id
                    AND hg.name IN ('".implode("', '", array_keys($data))."') ";
            if (!$admin) {
                $query .= $aclObj->queryBuilder("AND", "h.host_id", $aclObj->getHostsString("ID", $this->dbb));
            }
            $query .= " ORDER BY h.name ";
        } else {
            $query = "SELECT h.host_id, hs.current_state as state, h.display_name as name, hhg.hostgroup_id, o.name1 as hgname
                    FROM {$ndoPrefix}hostgroup_members hhg, {$ndoPrefix}hosts h, {$ndoPrefix}hostgroups hg, {$ndoPrefix}hoststatus hs, {$ndoPrefix}objects o
                    WHERE h.host_object_id = hs.host_object_id
                    AND h.config_type = 0
                    AND hs.host_object_id = hhg.host_object_id
                    AND hhg.hostgroup_id = hg.hostgroup_id
                    AND hg.hostgroup_object_id = o.object_id
                    AND o.name1 IN ('".implode("', '", array_keys($data))."') ";
            if (!$admin) {
                $query .= $aclObj->queryBuilder("AND", "h.display_name", $aclObj->getHostsString("NAME", $this->dbb));
            }
            $query .= " ORDER BY h.display_name ";
        }
        $res = $this->dbb->query($query);
        while ($row = $res->fetchRow()) {
            $k = $row['hgname'];
            if ($detailFlag == true) {
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
     * @param bool $isNdo
     * @param string $ndoPrefix
     */
    public function getServiceStates(&$data, $detailFlag = false, $admin, $aclObj, $preferences, $isNdo = false, $ndoPrefix = "nagios_")
    {
        if (!count($data)) {
            return array();
        }
        if ($isNdo == false) {
            $query = "SELECT DISTINCT h.host_id, s.state, h.name, s.service_id, s.description, hhg.hostgroup_id, hg.name as hgname ";
            $query .= "FROM hosts_hostgroups hhg, hosts h, services s, hostgroups hg ";
            if (!$admin) {
                $query .= ", centreon_acl acl ";
            }
            $query .= "WHERE h.host_id = hhg.host_id
                                    AND hhg.host_id = s.host_id
                    AND s.enabled = 1
                    AND hhg.hostgroup_id = hg.hostgroup_id
                    AND hg.name IN ('".implode("', '", array_keys($data))."') ";
            if (!$admin) {
                $query .= " AND h.host_id = acl.host_id
                                                    AND acl.service_id = s.service_id
                                                    AND acl.group_id IN (".$aclObj->getAccessGroupsString().")";
            }
            $query .= " ORDER BY h.name ";
        } else {
            $query = "SELECT DISTINCT h.host_id, ss.current_state as state, s.service_id, o.name1 as hgname,
                                      h.display_name as name, s.service_object_id, s.display_name as description, hhg.hostgroup_id ";
            $query .= "FROM {$ndoPrefix}hostgroup_members hhg, {$ndoPrefix}hosts h, {$ndoPrefix}hostgroups hg, {$ndoPrefix}objects o,
                            {$ndoPrefix}services s, {$ndoPrefix}servicestatus ss ";
            if (!$admin) {
                $query .= ", centreon_acl acl ";
            }
            $query .= "WHERE h.host_object_id = hhg.host_object_id
                       AND h.config_type = 0
                       AND hhg.hostgroup_id = hg.hostgroup_id
                       AND hg.hostgroup_object_id = o.object_id
                       AND o.objecttype_id = 3
                       AND hhg.host_object_id = s.host_object_id
                       AND s.service_object_id = ss.service_object_id
                       AND s.host_object_id = h.host_object_id
                       AND s.config_type = 0
                       AND o.name1 IN ('".implode("', '", array_keys($data))."') ";
            if (!$admin) {
                $query .= " AND h.display_name = acl.host_name
                            AND acl.service_description = s.display_name
                            AND acl.group_id IN (".$aclObj->getAccessGroupsString().")";
            }
            $query .= " ORDER BY h.display_name ";
        }
        $res = $this->dbb->query($query);
        while ($row = $res->fetchRow()) {
            $k = $row['hgname'];
            if ($detailFlag == true) {
                if (!isset($data[$k]['service_state'][$row['host_id']])) {
                    $data[$k]['service_state'][$row['host_id']] = array();
                }
                if (isset($data[$k]['service_state'][$row['host_id']]) 
                        && !isset($data[$k]['service_state'][$row['host_id']][$row['service_id']])) {
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
?>
