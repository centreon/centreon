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

/**
 * Class
 *
 * @class CentreonPerformanceService
 * @description Description of centreonPerformanceService
 */
class CentreonPerformanceService
{
    /** @var CentreonDB */
    protected $dbMon;

    /** @var CentreonACL */
    protected $aclObj;

    /**
     * CentreonPerformanceService constructor
     *
     * @param $dbMon
     * @param $aclObj
     */
    public function __construct($dbMon, $aclObj)
    {
        $this->dbMon = $dbMon;
        $this->aclObj = $aclObj;
    }

    /**
     * @param array $filters
     *
     * @throws PDOException
     * @throws RestBadRequestException
     * @return array
     */
    public function getList($filters = [])
    {
        $additionnalTables = '';
        $additionnalCondition = '';

        $serviceDescription = isset($filters['service']) === false ? '' : $filters['service'];

        if (isset($filters['page_limit'], $filters['page'])) {
            $limit = (int) (($filters['page'] - 1) * $filters['page_limit']);
            $range = 'LIMIT ' . $limit . ',' . (int) $filters['page_limit'];
        } else {
            $range = '';
        }

        $filterBindValues = [];
        if (isset($filters['hostgroup']) && $filters['hostgroup'] !== []) {
            $hgTokens = [];
            foreach ($filters['hostgroup'] as $idx => $hgId) {
                $key = ':hg_' . $idx;
                $hgTokens[] = $key;
                $filterBindValues[$key] = (int) $hgId;
            }
            $additionnalTables .= ',hosts_hostgroups hg ';
            $additionnalCondition .= 'AND (hg.host_id = i.host_id AND hg.hostgroup_id IN ('
                . implode(',', $hgTokens) . ')) ';
        }
        if (isset($filters['servicegroup']) && $filters['servicegroup'] !== []) {
            $sgTokens = [];
            foreach ($filters['servicegroup'] as $idx => $sgId) {
                $key = ':sg_' . $idx;
                $sgTokens[] = $key;
                $filterBindValues[$key] = (int) $sgId;
            }
            $additionnalTables .= ',services_servicegroups sg ';
            $additionnalCondition .= 'AND (sg.host_id = i.host_id AND sg.service_id = i.service_id '
                . 'AND sg.servicegroup_id IN (' . implode(',', $sgTokens) . ')) ';
        }
        if (isset($filters['host']) && $filters['host'] !== []) {
            $hostTokens = [];
            foreach ($filters['host'] as $idx => $hostId) {
                $key = ':host_' . $idx;
                $hostTokens[] = $key;
                $filterBindValues[$key] = (int) $hostId;
            }
            $additionnalCondition .= 'AND i.host_id IN (' . implode(',', $hostTokens) . ') ';
        }

        $virtualServicesCondition = $this->getVirtualServicesCondition($additionnalCondition, $additionnalTables);

        $query = 'SELECT SQL_CALC_FOUND_ROWS DISTINCT fullname, host_id, service_id, index_id '
            . 'FROM ( '
            . '( SELECT CONCAT(i.host_name, " - ", i.service_description) as fullname, '
            . 'i.host_id, i.service_id, m.index_id '
            . 'FROM index_data i, metrics m ' . $additionnalTables . (! $this->aclObj->admin ? ', centreon_acl acl ' : '')
            . 'WHERE i.id = m.index_id '
            . 'AND i.host_name NOT LIKE "\_Module\_%" '
            . (! $this->aclObj->admin
                ? ' AND acl.host_id = i.host_id AND acl.service_id = i.service_id AND acl.group_id IN ('
                . $this->aclObj->getAccessGroupsString() . ') ' : '')
            . $additionnalCondition
            . ') '
            . $virtualServicesCondition
            . ') as t_union '
            . 'WHERE fullname LIKE :serviceDesc '
            . 'GROUP BY host_id, service_id '
            . 'ORDER BY fullname '
            . $range;

        $stmt = $this->dbMon->prepare($query);
        $stmt->bindValue(':serviceDesc', '%' . $serviceDescription . '%');
        foreach ($filterBindValues as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        }
        $stmt->execute();
        $serviceList = [];
        while ($data = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $serviceCompleteName = $data['fullname'];
            $serviceCompleteId = $data['host_id'] . '-' . $data['service_id'];
            $serviceList[] = ['id' => $serviceCompleteId, 'text' => $serviceCompleteName];
        }

        return $serviceList;
    }

    /**
     * @param string $additionnalCondition
     * @param string $additionnalTables
     *
     * @return string
     */
    private function getVirtualServicesCondition($additionnalCondition, $additionnalTables = '')
    {
        // First, get virtual services for metaservices
        $metaServiceCondition = '';
        if (! $this->aclObj->admin) {
            $metaServices = $this->aclObj->getMetaServices();
            $virtualServices = [];
            foreach ($metaServices as $metaServiceId => $metaServiceName) {
                $virtualServices[] = "'meta_" . $metaServiceId . "'";
            }
            if ($virtualServices !== []) {
                $metaServiceCondition = 'AND s.description IN (' . implode(',', $virtualServices) . ') ';
            } else {
                return '';
            }
        } else {
            $metaServiceCondition = 'AND s.description LIKE "meta_%" ';
        }

        $virtualServicesCondition = 'UNION ALL ('
            . 'SELECT CONCAT("Meta - ", s.display_name) as fullname, i.host_id, i.service_id, m.index_id '
            . 'FROM index_data i, metrics m, services s ' . $additionnalTables
            . 'WHERE i.id = m.index_id '
            . $additionnalCondition
            . $metaServiceCondition
            . 'AND i.service_id = s.service_id '
            . ') ';

        // Then, get virtual services for modules
        $allVirtualServiceIds = CentreonHook::execute('Service', 'getVirtualServiceIds');
        foreach ($allVirtualServiceIds as $moduleVirtualServiceIds) {
            foreach ($moduleVirtualServiceIds as $hostname => $virtualServiceIds) {
                if (count($virtualServiceIds)) {
                    $virtualServicesCondition .= 'UNION ALL ('
                        . 'SELECT CONCAT("' . $hostname . ' - ", s.display_name) as fullname, i.host_id, '
                        . 'i.service_id, m.index_id '
                        . 'FROM index_data i, metrics m, services s ' . $additionnalTables
                        . 'WHERE i.id = m.index_id '
                        . $additionnalCondition
                        . 'AND s.service_id IN (' . implode(',', $virtualServiceIds) . ') '
                        . 'AND i.service_id = s.service_id '
                        . ') ';
                }
            }
        }

        return $virtualServicesCondition;
    }
}
