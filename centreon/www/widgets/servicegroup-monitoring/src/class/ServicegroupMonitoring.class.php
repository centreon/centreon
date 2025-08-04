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
     * @return array
     */
    public function getHostStates($sgName, $admin, $aclObj, $preferences, $detailFlag = false)
    {
        $query = "SELECT DISTINCT h.host_id, h.state, h.name, h.alias, ssg.servicegroup_id
            FROM `services_servicegroups` ssg, `hosts` h, `servicegroups` sg
            WHERE h.host_id = ssg.host_id
                AND h.name NOT LIKE '_Module_%'
                AND h.enabled = 1
                AND ssg.servicegroup_id = sg.servicegroup_id
                AND sg.name = '" . $this->dbb->escape($sgName) . "' ";
        if (! $admin) {
            $query .= $aclObj->queryBuilder('AND', 'h.host_id', $aclObj->getHostsString('ID', $this->dbb));
        }
        $query .= ' ORDER BY h.name ';
        $res = $this->dbb->query($query);
        $tab = [];
        $detailTab = [];
        while ($row = $res->fetch()) {
            if (! isset($tab[$row['state']])) {
                $tab[$row['state']] = 0;
            }
            if (! isset($detailTab[$row['name']])) {
                $detailTab[$row['name']] = [];
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
     * @return array
     */
    public function getServiceStates($sgName, $admin, $aclObj, $preferences, $detailFlag = false): array
    {
        $query = 'SELECT DISTINCT h.host_id, s.state, h.name, s.service_id, s.description, ssg.servicegroup_id
            FROM `services_servicegroups` ssg, `services` s, `hosts` h, `servicegroups` sg ';
        if (! $admin) {
            $query .= ', centreon_acl acl ';
        }
        $query .= "WHERE h.host_id = s.host_id
                AND h.name NOT LIKE '_Module_%'
                AND s.enabled = 1
                AND s.host_id = ssg.host_id
                AND ssg.service_id = s.service_id
                AND ssg.servicegroup_id = sg.servicegroup_id
                AND sg.name = '" . $this->dbb->escape($sgName) . "' ";
        if (! $admin) {
            $query .= ' AND h.host_id = acl.host_id
                AND acl.service_id = s.service_id
                AND acl.group_id IN (' . $aclObj->getAccessGroupsString() . ') ';
        }
        $query .= ' ORDER BY h.name ';
        $res = $this->dbb->query($query);
        $tab = [];
        $detailTab = [];
        while ($row = $res->fetch()) {
            if (! isset($tab[$row['state']])) {
                $tab[$row['state']] = 0;
            }
            if (! isset($detailTab[$row['host_id']])) {
                $detailTab[$row['host_id']] = [];
            }
            if (isset($detailTab[$row['name']]) && ! isset($detailTab[$row['name']][$row['service_id']])) {
                $detailTab[$row['host_id']][$row['service_id']] = [];
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
