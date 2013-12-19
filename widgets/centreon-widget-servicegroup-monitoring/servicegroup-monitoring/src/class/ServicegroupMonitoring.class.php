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

class ServicegroupMonitoring
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
     * @param string $sgName
     * @param int $detailFlag
     * @param int $admin
     * @param CentreonACL $aclObj
     * @param array $preferences
     * @param bool $isNdo
     * @param string $ndoPrefix
     * @return array
     */
    public function getHostStates($sgName, $detailFlag = false, $admin, $aclObj, $preferences, $isNdo = false, $ndoPrefix = "nagios_")
    {
        if ($isNdo == false) {
            $query = "SELECT DISTINCT h.host_id, h.state, h.name, ssg.servicegroup_id
                      FROM `services_servicegroups` ssg, `hosts` h, `servicegroups` sg
                      WHERE h.host_id = ssg.host_id
                      AND h.name NOT LIKE '_Module_%'
                      AND h.enabled = 1
                      AND ssg.servicegroup_id = sg.servicegroup_id
                      AND sg.name = '" . $this->dbb->escape($sgName)."' ";
            if (!$admin) {
                $query .= $aclObj->queryBuilder("AND", "h.host_id", $aclObj->getHostsString("ID", $this->dbb));
            }
            $query .= " ORDER BY h.name ";
        } else {
            $query = "SELECT DISTINCT h.host_id, hs.current_state as state, h.display_name as name, ssg.servicegroup_id
                      FROM `{$ndoPrefix}servicegroup_members` ssg, `{$ndoPrefix}hosts` h, `{$ndoPrefix}services` s,
                           `{$ndoPrefix}hoststatus` hs, `{$ndoPrefix}servicegroups` sg, `{$ndoPrefix}objects` o
                      WHERE h.host_object_id = s.host_object_id
                      AND s.host_object_id = hs.host_object_id
                      AND s.service_object_id = ssg.service_object_id
                      AND ssg.servicegroup_id = sg.servicegroup_id
                      AND sg.servicegroup_object_id = o.object_id
                      AND h.display_name NOT LIKE '_Module_%'                                            
                      AND o.name1 = '" . $this->dbb->escape($sgName)."' ";
            if (!$admin) {
                $query .= $aclObj->queryBuilder("AND", "h.display_name", $aclObj->getHostsString("NAME", $this->dbb));
            }
            $query .= " ORDER BY h.display_name ";
        }
        $res = $this->dbb->query($query);
        $tab = array();
        $detailTab = array();
        while ($row = $res->fetchRow()) {
            if (!isset($tab[$row['state']])) {
                $tab[$row['state']] = 0;
            }
            if (!isset($detailTab[$row['name']])) {
                $detailTab[$row['name']] = array();
            }
            foreach ($row as $key => $val) {
                $detailTab[$row['name']][$key] = $val;
            }
            $tab[$row['state']]++;
        }
        if ($detailFlag == true) {
            return $detailTab;
        }
        return $tab;
    }

    /**
     * Get Service States
     *
     * @param string $sgName
     * @param int $detailFlag
     * @param int $admin
     * @param CentreonACL $aclObj
     * @param array $preferences
     * @param bool $isNdo
     * @param string $ndoPrefix
     * @return string
     */
    public function getServiceStates($sgName, $detailFlag = false, $admin, $aclObj, $preferences, $isNdo = false, $ndoPrefix = "nagios_")
    {
        if ($isNdo == false) {
            $query = "SELECT DISTINCT h.host_id, s.state, h.name, s.service_id, s.description, ssg.servicegroup_id
                      FROM `services_servicegroups` ssg, `services` s, `hosts` h, `servicegroups` sg ";
            if (!$admin) {
                $query .= ", centreon_acl acl ";
            }
            $query .= "WHERE h.host_id = s.host_id
                       AND h.name NOT LIKE '_Module_%'
                       AND s.enabled = 1
                       AND s.host_id = ssg.host_id
                       AND ssg.service_id = s.service_id
                       AND ssg.servicegroup_id = sg.servicegroup_id
                       AND sg.name = '".$this->dbb->escape($sgName) ."' ";
            if (!$admin) {
                $query .= " AND h.host_id = acl.host_id
                            AND acl.service_id = s.service_id ";
                $query .= " AND acl.group_id IN (" .$aclObj->getAccessGroupsString().") ";
            }
            $query .= " ORDER BY h.name ";
        } else {
            $query = "SELECT DISTINCT h.host_id, ss.current_state as state, h.display_name as name, 
                                      s.service_id, s.display_name as description, ssg.servicegroup_id
                      FROM `{$ndoPrefix}servicegroup_members` ssg, `{$ndoPrefix}hosts` h, `{$ndoPrefix}services` s,
                           `{$ndoPrefix}servicestatus` ss, `{$ndoPrefix}servicegroups` sg, `{$ndoPrefix}objects` o ";
            if (!$admin) {
                $query .= ", centreon_acl acl ";
            }
            $query .= "WHERE h.host_object_id = s.host_object_id
                       AND s.service_object_id = ss.service_object_id
                       AND h.display_name NOT LIKE '_Module_%'                       
                       AND s.service_object_id = ssg.service_object_id
                       AND ssg.servicegroup_id = sg.servicegroup_id
                       AND sg.servicegroup_object_id = o.object_id
                       AND s.config_type = 0
                       AND o.name1 = '".$this->dbb->escape($sgName) ."' ";
            if (!$admin) {
                $query .= " AND h.display_name = acl.host_name
                            AND acl.service_description = s.display_name ";
                $query .= " AND acl.group_id IN (" .$aclObj->getAccessGroupsString().") ";
            }
            $query .= " ORDER BY h.display_name ";
        }
        $res = $this->dbb->query($query);
        $tab = array();
        $detailTab = array();
        while ($row = $res->fetchRow()) {
            if (!isset($tab[$row['state']])) {
                $tab[$row['state']] = 0;
            }
            if (!isset($detailTab[$row['host_id']])) {
                $detailTab[$row['host_id']] = array();
            }
            if (isset($detailTab[$row['name']]) && !isset($detailTab[$row['name']][$row['service_id']])) {
                $detailTab[$row['host_id']][$row['service_id']] = array();
            }
            foreach ($row as $key => $val) {
                $detailTab[$row['host_id']][$row['service_id']][$key] = $val;
            }
            $tab[$row['state']]++;
        }
        if ($detailFlag == true) {
            return $detailTab;
        }
        return $tab;
    }
}
?>