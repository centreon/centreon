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
        if (! count($data)) {
            return [];
        }
        $query = "SELECT 1 AS REALTIME, h.host_id, h.state, h.name, h.alias, hhg.hostgroup_id, hg.name as hgname
            FROM hosts_hostgroups hhg, hosts h, hostgroups hg
            WHERE h.host_id = hhg.host_id
            AND h.enabled = 1
            AND hhg.hostgroup_id = hg.hostgroup_id
            AND hg.name IN ('" . implode("', '", array_keys($data)) . "') ";
        if (! $admin) {
            $query .= $aclObj->queryBuilder('AND', 'h.host_id', $aclObj->getHostsString('ID', $this->dbb));
        }
        $query .= ' ORDER BY h.name ';
        $res = $this->dbb->query($query);
        while ($row = $res->fetch()) {
            $k = $row['hgname'];
            if ($detailFlag === true) {
                if (! isset($data[$k]['host_state'][$row['name']])) {
                    $data[$k]['host_state'][$row['name']] = [];
                }
                foreach ($row as $key => $val) {
                    $data[$k]['host_state'][$row['name']][$key] = $val;
                }
            } else {
                if (! isset($data[$k]['host_state'][$row['state']])) {
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
        if (! count($data)) {
            return [];
        }
        $query = 'SELECT DISTINCT 1 AS REALTIME,
                h.host_id, s.state, h.name, s.service_id, s.description, hhg.hostgroup_id, hg.name as hgname,
                (case s.state when 0 then 3 when 2 then 0 when 3 then 2  when 3 then 2 else s.state END) as tri
            FROM hosts_hostgroups hhg, hosts h, services s, hostgroups hg ';
        if (! $admin) {
            $query .= ', centreon_acl acl ';
        }
        $query .= "WHERE h.host_id = hhg.host_id
            AND hhg.host_id = s.host_id
            AND s.enabled = 1
            AND h.enabled = 1
            AND hhg.hostgroup_id = hg.hostgroup_id
            AND hg.name IN ('" . implode("', '", array_keys($data)) . "') ";
        if (! $admin) {
            $query .= ' AND h.host_id = acl.host_id
                AND acl.service_id = s.service_id
                AND acl.group_id IN (' . $aclObj->getAccessGroupsString() . ')';
        }
        $query .= ' ORDER BY tri, description ASC';
        $res = $this->dbb->query($query);
        while ($row = $res->fetch()) {
            $k = $row['hgname'];
            if ($detailFlag === true) {
                if (! isset($data[$k]['service_state'][$row['host_id']])) {
                    $data[$k]['service_state'][$row['host_id']] = [];
                }
                if (
                    isset($data[$k]['service_state'][$row['host_id']])
                    && ! isset($data[$k]['service_state'][$row['host_id']][$row['service_id']])
                ) {
                    $data[$k]['service_state'][$row['host_id']][$row['service_id']] = [];
                }
                foreach ($row as $key => $val) {
                    $data[$k]['service_state'][$row['host_id']][$row['service_id']][$key] = $val;
                }
            } else {
                if (! isset($data[$k]['service_state'][$row['state']])) {
                    $data[$k]['service_state'][$row['state']] = 0;
                }
                $data[$k]['service_state'][$row['state']]++;
            }
        }
    }
}
