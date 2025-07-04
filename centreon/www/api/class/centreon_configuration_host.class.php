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
require_once _CENTREON_PATH_ . '/www/class/centreonHost.class.php';
require_once _CENTREON_PATH_ . '/www/class/centreonHook.class.php';
require_once __DIR__ . '/centreon_configuration_objects.class.php';

/**
 * Class
 *
 * @class CentreonConfigurationHost
 */
class CentreonConfigurationHost extends CentreonConfigurationObjects
{
    /** @var CentreonDB */
    protected $pearDBMonitoring;

    /**
     * CentreonConfigurationHost constructor
     */
    public function __construct()
    {
        global $pearDBO;
        parent::__construct();
        $this->pearDBMonitoring = new CentreonDB('centstorage');
        $pearDBO = $this->pearDBMonitoring;
    }

    /**
     * @throws PDOException
     * @throws RestBadRequestException
     * @return array
     */
    public function getList()
    {
        global $centreon;

        $userId = $centreon->user->user_id;
        $isAdmin = $centreon->user->admin;
        $aclHosts = '';
        $additionalTables = '';
        $additionalCondition = '';
        $explodedValues = '';
        $queryValues = [];
        $query = '';

        // Check for select2 'q' argument
        $queryValues['hostName'] = false === isset($this->arguments['q']) ? '%%' : '%' . (string) $this->arguments['q'] . '%';
        $query .= 'SELECT SQL_CALC_FOUND_ROWS DISTINCT host_name, host_id, host_activate '
            . 'FROM ( '
            . '( SELECT DISTINCT h.host_name, h.host_id, h.host_activate '
            . 'FROM host h ';

        if (isset($this->arguments['hostgroup'])) {
            $additionalTables .= ',hostgroup_relation hg ';
            $additionalCondition .= 'AND hg.host_host_id = h.host_id AND hg.hostgroup_hg_id IN (';
            foreach (explode(',', $this->arguments['hostgroup']) as $hgId => $hgValue) {
                if (! is_numeric($hgValue)) {
                    throw new RestBadRequestException('Error, host group id must be numerical');
                }
                $explodedValues .= ':hostgroup' . $hgId . ',';
                $queryValues['hostgroup'][$hgId] = (int) $hgValue;
            }
            $explodedValues = rtrim($explodedValues, ',');
            $additionalCondition .= $explodedValues . ') ';
        }
        $query .= $additionalTables . 'WHERE h.host_register = "1" ';

        // Get ACL if user is not admin
        if (! $isAdmin) {
            $acl = new CentreonACL($userId, $isAdmin);
            $aclHosts .= 'AND h.host_id IN (' . $acl->getHostsString('ID', $this->pearDBMonitoring) . ') ';
        }
        $query .= $aclHosts;
        $query .= $additionalCondition . ') ';

        // Check for virtual hosts
        $virtualHostCondition = '';
        if (! isset($this->arguments['hostgroup']) && isset($this->arguments['h']) && $this->arguments['h'] == 'all') {
            $allVirtualHosts = CentreonHook::execute('Host', 'getVirtualHosts');
            foreach ($allVirtualHosts as $virtualHosts) {
                foreach ($virtualHosts as $vHostId => $vHostName) {
                    $virtualHostCondition .= 'UNION ALL '
                        . "(SELECT :hostNameTable{$vHostId} as host_name, "
                        . ":virtualHostId{$vHostId} as host_id, "
                        . "'1' AS host_activate ) ";
                    $queryValues['virtualHost'][$vHostId] = (string) $vHostName;
                }
            }
        }
        $query .= $virtualHostCondition
            . ') t_union '
            . 'WHERE host_name LIKE :hostName '
            . 'ORDER BY host_name ';

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
            $queryValues['offset'] = (int) $offset;
            $queryValues['limit'] = (int) $this->arguments['page_limit'];
        }

        $stmt = $this->pearDB->prepare($query);
        $stmt->bindParam(':hostName', $queryValues['hostName'], PDO::PARAM_STR);

        if (isset($queryValues['hostgroup'])) {
            foreach ($queryValues['hostgroup'] as $hgId => $hgValue) {
                $stmt->bindValue(':hostgroup' . $hgId, $hgValue, PDO::PARAM_INT);
            }
        }
        if (isset($queryValues['virtualHost'])) {
            foreach ($queryValues['virtualHost'] as $vhId => $vhValue) {
                $stmt->bindValue(':hostNameTable' . $vhId, $vhValue, PDO::PARAM_STR);
                $stmt->bindValue(':virtualHostId' . $vhId, $vhId, PDO::PARAM_INT);
            }
        }
        if (isset($queryValues['offset'])) {
            $stmt->bindParam(':offset', $queryValues['offset'], PDO::PARAM_INT);
            $stmt->bindParam(':limit', $queryValues['limit'], PDO::PARAM_INT);
        }
        $stmt->execute();
        $hostList = [];
        while ($data = $stmt->fetch()) {
            $hostList[] = ['id' => htmlentities($data['host_id']), 'text' => $data['host_name'], 'status' => (bool) $data['host_activate']];
        }

        return ['items' => $hostList, 'total' => (int) $this->pearDB->numberRows()];
    }

    /**
     * @throws PDOException
     * @throws RestBadRequestException
     * @return array
     */
    public function getServices()
    {
        // Check for id
        if (false === isset($this->arguments['id'])) {
            throw new RestBadRequestException('Missing host id');
        }
        $id = $this->arguments['id'];

        $allServices = false;
        if (isset($this->arguments['all'])) {
            $allServices = true;
        }

        $hostObj = new CentreonHost($this->pearDB);
        $serviceList = [];
        $serviceListRaw = $hostObj->getServices($id, false, $allServices);

        foreach ($serviceListRaw as $service_id => $service_description) {
            if ($allServices || service_has_graph($id, $service_id)) {
                $serviceList[$service_id] = $service_description;
            }
        }

        return $serviceList;
    }
}
