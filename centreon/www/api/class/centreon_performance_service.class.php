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

require_once _CENTREON_PATH_ . '/www/class/centreonDB.class.php';
require_once _CENTREON_PATH_ . '/www/class/centreonACL.class.php';
require_once _CENTREON_PATH_ . '/www/class/centreonHook.class.php';
require_once __DIR__ . '/centreon_configuration_objects.class.php';

/**
 * Class
 *
 * @class CentreonPerformanceService
 */
class CentreonPerformanceService extends CentreonConfigurationObjects
{
    /** @var array */
    public $arguments;

    /** @var CentreonDB */
    protected $pearDBMonitoring;

    /**
     * CentreonPerformanceService constructor
     */
    public function __construct()
    {
        $this->pearDBMonitoring = new CentreonDB('centstorage');
    }

    /**
     * @throws PDOException
     * @throws RestBadRequestException
     * @return array
     */
    public function getList()
    {
        global $centreon, $conf_centreon;

        $userId = $centreon->user->user_id;
        $isAdmin = $centreon->user->admin;
        $additionalTables = '';
        $additionalValues = [];
        $additionalCondition = '';
        $bindParams = [];
        $excludeAnomalyDetection = false;

        // Get ACL if user is not admin
        $acl = null;
        if (! $isAdmin) {
            $acl = new CentreonACL($userId, $isAdmin);
        }

        $bindParams[':fullName'] = false === isset($this->arguments['q']) ? '%%' : '%' . (string) $this->arguments['q'] . '%';

        if (isset($this->arguments['e']) && strcmp('anomaly', $this->arguments['e']) == 0) {
            $excludeAnomalyDetection = true;
        }

        $query = 'SELECT SQL_CALC_FOUND_ROWS DISTINCT 1 AS REALTIME, fullname, host_id, service_id, index_id '
            . 'FROM ( '
            . '( SELECT CONCAT(i.host_name, " - ", i.service_description) as fullname, i.host_id, '
            . 'i.service_id, m.index_id '
            . 'FROM index_data i, metrics m, services s ' . (! $isAdmin ? ', centreon_acl acl ' : '');
        if (isset($this->arguments['hostgroup'])) {
            $additionalTables .= ',hosts_hostgroups hg ';
        }
        if (isset($this->arguments['servicegroup'])) {
            $additionalTables .= ',services_servicegroups sg ';
        }

        $query .= $additionalTables
            . 'WHERE i.id = m.index_id '
            . 'AND s.enabled = 1 '
            . 'AND i.service_id = s.service_id '
            . 'AND i.host_name NOT LIKE "\_Module\_%" '
            . 'AND CONCAT(i.host_name, " - ", i.service_description) LIKE :fullName ';

        if (! $isAdmin) {
            $query .= 'AND acl.host_id = i.host_id '
                . 'AND acl.service_id = i.service_id '
                . 'AND acl.group_id IN (' . $acl->getAccessGroupsString() . ') ';
        }

        if ($excludeAnomalyDetection) {
            $additionalCondition .= 'AND s.service_id NOT IN (SELECT service_id 
            FROM `' . $conf_centreon['db'] . '`.mod_anomaly_service) ';
        }
        if (isset($this->arguments['hostgroup'])) {
            $additionalCondition .= 'AND (hg.host_id = i.host_id '
                . 'AND hg.hostgroup_id IN (';
            $params = [];
            foreach ($this->arguments['hostgroup'] as $k => $v) {
                if (! is_numeric($v)) {
                    throw new RestBadRequestException('Error, host group id must be numerical');
                }
                $params[':hgId' . $v] = (int) $v;
            }
            $bindParams = array_merge($bindParams, $params);
            $additionalCondition .= implode(',', array_keys($params)) . ')) ';
        }

        if (isset($this->arguments['servicegroup'])) {
            $additionalCondition .= 'AND (sg.host_id = i.host_id AND sg.service_id = i.service_id '
                . 'AND sg.servicegroup_id IN (';
            $params = [];
            foreach ($this->arguments['servicegroup'] as $k => $v) {
                if (! is_numeric($v)) {
                    throw new RestBadRequestException('Error, service group id must be numerical');
                }
                $params[':sgId' . $v] = (int) $v;
            }
            $bindParams = array_merge($bindParams, $params);
            $additionalCondition .= implode(',', array_keys($params)) . ')) ';
        }

        if (isset($this->arguments['host'])) {
            $additionalCondition .= 'AND i.host_id IN (';
            $params = [];
            foreach ($this->arguments['host'] as $k => $v) {
                if (! is_numeric($v)) {
                    throw new RestBadRequestException('Error, host id must be numerical');
                }
                $params[':hostId' . $v] = (int) $v;
            }
            $bindParams = array_merge($bindParams, $params);
            $additionalCondition .= implode(',', array_keys($params)) . ') ';
        }
        $query .= $additionalCondition . ') ';
        if (isset($acl)) {
            $virtualObject = $this->getVirtualServicesCondition(
                $additionalTables,
                $additionalCondition,
                $additionalValues,
                $acl
            );
            $virtualServicesCondition = $virtualObject['query'];
            $virtualValues = $virtualObject['value'];
        } else {
            $virtualObject = $this->getVirtualServicesCondition(
                $additionalTables,
                $additionalCondition,
                $additionalValues
            );
            $virtualServicesCondition = $virtualObject['query'];
            $virtualValues = $virtualObject['value'];
        }

        $query .= $virtualServicesCondition . ') as t_union '
            . 'WHERE fullname LIKE :fullName '
            . 'GROUP BY host_id, service_id, fullname, index_id '
            . 'ORDER BY fullname ';

        if (isset($this->arguments['page_limit'], $this->arguments['page'])) {
            if (
                ! is_numeric($this->arguments['page'])
                || ! is_numeric($this->arguments['page_limit'])
                || $this->arguments['page_limit'] < 1
            ) {
                throw new RestBadRequestException('Error, limit must be an integer greater than zero');
            }
            $offset = ($this->arguments['page'] - 1) * $this->arguments['page_limit'];
            $query .= 'LIMIT :offset, :limit';
            $bindParams[':offset'] = (int) $offset;
            $bindParams[':limit'] = (int) $this->arguments['page_limit'];
        }

        $stmt = $this->pearDBMonitoring->prepare($query);
        $stmt->bindValue(':fullName', $bindParams[':fullName'], PDO::PARAM_STR);
        unset($bindParams[':fullName']);
        foreach ($bindParams as $k => $v) {
            $stmt->bindValue($k, $v, PDO::PARAM_INT);
        }

        if (isset($virtualValues['metaService'])) {
            foreach ($virtualValues['metaService'] as $k => $v) {
                $stmt->bindValue(':' . $k, $v, PDO::PARAM_INT);
            }
        }
        if (isset($virtualValues['virtualService'])) {
            foreach ($virtualValues['virtualService'] as $k => $v) {
                $stmt->bindValue(':' . $k, $v, PDO::PARAM_INT);
            }
        }
        $stmt->execute();
        $serviceList = [];
        while ($data = $stmt->fetch()) {
            $serviceCompleteName = $data['fullname'];
            $serviceCompleteId = $data['host_id'] . '-' . $data['service_id'];
            $serviceList[] = ['id' => htmlentities($serviceCompleteId), 'text' => $serviceCompleteName];
        }

        return ['items' => $serviceList, 'total' => (int) $this->pearDBMonitoring->query('SELECT FOUND_ROWS() AS REALTIME')->fetchColumn()];
    }

    /**
     * @param $additionalTables
     * @param $additionalCondition
     * @param $additionalValues
     * @param CentreonACL|null $aclObj
     * @throws RestBadRequestException
     * @return array
     */
    private function getVirtualServicesCondition(
        $additionalTables,
        $additionalCondition,
        $additionalValues,
        $aclObj = null
    ) {
        global $centreon;

        $userId = $centreon->user->user_id;
        $isAdmin = $centreon->user->admin;

        // Get ACL if user is not admin
        $acl = null;
        if (! $isAdmin) {
            $acl = new CentreonACL($userId, $isAdmin);
        }

        // First, get virtual services for metaservices
        $metaServiceCondition = '';
        $metaValues = $additionalValues;
        if (isset($aclObj)) {
            $metaServices = $aclObj->getMetaServices();
            $virtualServices = [];
            foreach ($metaServices as $metaServiceId => $metaServiceName) {
                $virtualServices[] = 'meta_' . $metaServiceId;
            }
            if ($virtualServices !== []) {
                $metaServiceCondition = 'AND s.description IN (';
                $explodedValues = '';
                foreach ($virtualServices as $k => $v) {
                    $explodedValues .= ':meta' . $k . ',';
                    $metaValues['metaService']['meta' . $k] = (string) $v;
                }
                $explodedValues = rtrim($explodedValues, ',');
                $metaServiceCondition .= $explodedValues . ') ';
            }
        }

        $metaServiceCondition .= 'AND s.description LIKE "meta_%" ';

        $virtualServicesCondition = 'UNION ALL ('
            . 'SELECT CONCAT("Meta - ", s.display_name) as fullname, i.host_id, i.service_id, m.index_id '
            . 'FROM index_data i, metrics m, services s ' . (! $isAdmin ? ', centreon_acl acl ' : '')
            . $additionalTables
            . 'WHERE i.id = m.index_id '
            . 'AND s.enabled = 1 '
            . $additionalCondition
            . $metaServiceCondition
            . 'AND i.service_id = s.service_id ';
        if (! $isAdmin) {
            $virtualServicesCondition .= 'AND acl.host_id = i.host_id '
                . 'AND acl.service_id = i.service_id '
                . 'AND acl.group_id IN (' . $acl->getAccessGroupsString() . ') ';
        }
        $virtualServicesCondition .= ') ';

        // Then, get virtual services for modules if not in anomaly detection context
        $allVirtualServiceIds = [];
        if (($this->arguments['e'] ?? null) !== 'anomaly') {
            $allVirtualServiceIds = CentreonHook::execute('Service', 'getVirtualServiceIds');
        }
        foreach ($allVirtualServiceIds as $moduleVirtualServiceIds) {
            foreach ($moduleVirtualServiceIds as $hostname => $virtualServiceIds) {
                if (count($virtualServiceIds)) {
                    $virtualServicesCondition .= 'UNION ALL ('
                        . 'SELECT CONCAT("' . $hostname . ' - ", s.display_name) as fullname, '
                        . 'i.host_id, i.service_id, m.index_id '
                        . 'FROM index_data i, metrics m, services s '
                        . $additionalTables
                        . 'WHERE i.id = m.index_id '
                        . 'AND s.enabled = 1 '
                        . $additionalCondition
                        . 'AND s.service_id IN (';

                    $explodedValues = '';
                    foreach ($virtualServiceIds as $k => $v) {
                        if (! is_numeric($v)) {
                            throw new RestBadRequestException('Error, virtual service id must be numerical');
                        }
                        $explodedValues .= ':vService' . $v . ',';
                        $metaValues['virtualService']['vService' . $v] = (int) $v;
                    }
                    $explodedValues = rtrim($explodedValues, ',');

                    $virtualServicesCondition .= $explodedValues . ') '
                        . 'AND i.service_id = s.service_id '
                        . ') ';
                }
            }
        }

        return ['query' => $virtualServicesCondition, 'value' => $metaValues];
    }
}
