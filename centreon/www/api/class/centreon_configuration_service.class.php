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
require_once __DIR__ . '/centreon_configuration_objects.class.php';

/**
 * Class
 *
 * @class CentreonConfigurationService
 */
class CentreonConfigurationService extends CentreonConfigurationObjects
{
    /** @var CentreonDB */
    public $db;

    /** @var CentreonDB */
    protected $pearDBMonitoring;

    /**
     * CentreonConfigurationService constructor
     */
    public function __construct()
    {
        global $pearDBO;
        parent::__construct();
        $this->pearDBMonitoring = new CentreonDB('centstorage');
        $pearDBO = $this->pearDBMonitoring;
    }

    /**
     * @throws RestBadRequestException
     * @return array
     */
    public function getList()
    {

        global $centreon;

        $userId = $centreon->user->user_id;
        $isAdmin = $centreon->user->admin;
        $aclServices = '';
        $aclMetaServices = '';
        $range = [];

        // Get ACL if user is not admin
        if (! $isAdmin) {
            $acl = new CentreonACL($userId, $isAdmin);
            $aclServices .= 'AND s.service_id IN (' . $acl->getServicesString('ID', $this->pearDBMonitoring) . ') ';
            $aclMetaServices .= 'AND ms.service_id IN ('
                . $acl->getMetaServiceString() . ') ';
        }

        // Check for select2 'q' argument
        $q = isset($this->arguments['q']) ? (string) $this->arguments['q'] : '';

        // Check for service enable
        if (isset($this->arguments['e'])) {
            $enableList = ['enable', 'disable'];
            if (in_array(strtolower($this->arguments['e']), $enableList)) {
                $e = $this->arguments['e'];
            } else {
                throw new RestBadRequestException('Error, bad enable status');
            }
        } else {
            $e = '';
        }

        // Check for service type
        if (isset($this->arguments['t'])) {
            $typeList = ['hostgroup', 'host'];
            if (in_array(strtolower($this->arguments['t']), $typeList)) {
                $t = $this->arguments['t'];
            } else {
                throw new RestBadRequestException('Error, bad service type');
            }
        } else {
            $t = 'host';
        }

        // Check for service with graph
        $g = false;
        if (isset($this->arguments['g'])) {
            $g = $this->arguments['g'];
            if ($g == '1') {
                $g = true;
            }
        }

        // Check for service type
        if (isset($this->arguments['s'])) {
            $sTypeList = ['s', 'm', 'all'];
            if (in_array(strtolower($this->arguments['s']), $sTypeList)) {
                $s = $this->arguments['s'];
            } else {
                throw new RestBadRequestException('Error, bad service type');
            }
        } else {
            $s = 'all';
        }

        if (isset($this->arguments['page_limit'], $this->arguments['page'])) {
            if (
                ! is_numeric($this->arguments['page'])
                || ! is_numeric($this->arguments['page_limit'])
                || $this->arguments['page_limit'] < 1
            ) {
                throw new RestBadRequestException('Error, limit must be an integer greater than zero');
            }
            $offset = ($this->arguments['page'] - 1) * $this->arguments['page_limit'];
            $range[] = (int) $offset;
            $range[] = (int) $this->arguments['page_limit'];
        }

        switch ($t) {
            default:
            case 'host':
                $serviceList = $this->getServicesByHost($q, $aclServices, $range, $g, $aclMetaServices, $s, $e);
                break;
            case 'hostgroup':
                $serviceList = $this->getServicesByHostgroup($q, $aclServices, $range);
                break;
        }

        return $serviceList;
    }

    /**
     * @param $q
     * @param $aclServices
     * @param array $range
     * @param bool $hasGraph
     * @param $aclMetaServices
     * @param $s
     * @param $e
     * @throws Exception
     * @return array
     */
    private function getServicesByHost(
        $q,
        $aclServices,
        $range = [],
        $hasGraph = false,
        $aclMetaServices = '',
        $s = 'all',
        $e = 'enable'
    ) {
        $queryValues = [];
        if ($e == 'enable') {
            $enableQuery = 'AND s.service_activate = \'1\' AND h.host_activate = \'1\' ';
            $enableQueryMeta = 'AND ms.service_activate = \'1\' AND mh.host_activate = \'1\' ';
        } elseif ($e == 'disable') {
            $enableQuery = 'AND ( s.service_activate = \'0\' OR h.host_activate = \'0\' ) ';
            $enableQueryMeta = 'AND ( ms.service_activate = \'0\' OR mh.host_activate = \'0\') ';
        } else {
            $enableQuery = '';
            $enableQueryMeta = '';
        }

        switch ($s) {
            case 'all':
                $queryService = 'SELECT SQL_CALC_FOUND_ROWS DISTINCT fullname, service_id, host_id, service_activate '
                    . 'FROM ( '
                    . '( SELECT DISTINCT CONCAT(h.host_name, " - ", s.service_description) '
                    . 'as fullname, s.service_id, h.host_id, s.service_activate '
                    . 'FROM host h, service s, host_service_relation hsr '
                    . 'WHERE hsr.host_host_id = h.host_id '
                    . 'AND hsr.service_service_id = s.service_id '
                    . 'AND h.host_register = "1" '
                    . 'AND (s.service_register = "1" OR s.service_register = "3") '
                    . 'AND CONCAT(h.host_name, " - ", s.service_description) LIKE :description '
                    . $enableQuery . $aclServices . ') '
                    . 'UNION ALL ( '
                    . 'SELECT DISTINCT CONCAT("Meta - ", ms.display_name) as fullname, ms.service_id, mh.host_id, ms.service_activate '
                    . 'FROM host mh, service ms '
                    . 'WHERE mh.host_name = "_Module_Meta" '
                    . 'AND mh.host_register = "2" '
                    . 'AND ms.service_register = "2" '
                    . 'AND CONCAT("Meta - ", ms.display_name) LIKE :description '
                    . $enableQueryMeta . $aclMetaServices . ') '
                    . ')  as t_union '
                    . 'ORDER BY fullname ';
                if (! empty($range)) {
                    $queryService .= 'LIMIT :offset, :limit';
                    $queryValues['offset'] = $range[0];
                    $queryValues['limit'] = $range[1];
                }
                $queryValues['description'] = '%' . $q . '%';
                $stmt = $this->pearDB->prepare($queryService);
                $stmt->bindValue(':description', $queryValues['description'], PDO::PARAM_STR);
                if (isset($queryValues['offset'])) {
                    $stmt->bindValue(':offset', $queryValues['offset'], PDO::PARAM_INT);
                    $stmt->bindValue(':limit', $queryValues['limit'], PDO::PARAM_INT);
                }
                $dbResult = $stmt->execute();
                break;
            case 's':
                $queryService = 'SELECT SQL_CALC_FOUND_ROWS DISTINCT CONCAT(h.host_name, " - ", '
                    . 's.service_description) as fullname, s.service_id, h.host_id, s.service_activate '
                    . 'FROM host h, service s, host_service_relation hsr '
                    . 'WHERE hsr.host_host_id = h.host_id '
                    . 'AND hsr.service_service_id = s.service_id '
                    . 'AND h.host_register = "1" '
                    . 'AND (s.service_register = "1" OR s.service_register = "3") '
                    . 'AND CONCAT(h.host_name, " - ", s.service_description) LIKE :description '
                    . $enableQuery . $aclServices
                    . 'ORDER BY fullname ';

                if (! empty($range)) {
                    $queryService .= 'LIMIT :offset, :limit';
                    $queryValues['offset'] = $range[0];
                    $queryValues['limit'] = $range[1];
                }
                $queryValues['description'] = '%' . $q . '%';
                $stmt = $this->pearDB->prepare($queryService);
                $stmt->bindValue(':description', $queryValues['description'], PDO::PARAM_STR);
                if (isset($queryValues['offset'])) {
                    $stmt->bindValue(':offset', $queryValues['offset'], PDO::PARAM_INT);
                    $stmt->bindValue(':limit', $queryValues['limit'], PDO::PARAM_INT);
                }
                $dbResult = $stmt->execute();
                break;
            case 'm':
                $queryService = 'SELECT SQL_CALC_FOUND_ROWS DISTINCT CONCAT("Meta - ", ms.display_name) '
                    . 'as fullname, ms.service_id, mh.host_id, ms.service_activate '
                    . 'FROM host mh, service ms '
                    . 'WHERE mh.host_name = "_Module_Meta" '
                    . 'AND mh.host_register = "2" '
                    . 'AND ms.service_register = "2" '
                    . 'AND CONCAT("Meta - ", ms.display_name) LIKE :description '
                    . $enableQueryMeta . $aclMetaServices
                    . 'ORDER BY fullname ';
                if (! empty($range)) {
                    $queryService .= 'LIMIT :offset, :limit';
                    $queryValues['offset'] = $range[0];
                    $queryValues['limit'] = $range[1];
                }
                $queryValues['description'] = '%' . $q . '%';
                $stmt = $this->pearDB->prepare($queryService);
                $stmt->bindValue(':description', $queryValues['description'], PDO::PARAM_STR);
                if (isset($queryValues['offset'])) {
                    $stmt->bindValue(':offset', $queryValues['offset'], PDO::PARAM_INT);
                    $stmt->bindValue(':limit', $queryValues['limit'], PDO::PARAM_INT);
                }
                $dbResult = $stmt->execute();
                break;
        }
        if (! $dbResult) {
            throw new Exception('An error occured');
        }

        $serviceList = [];
        while ($data = $stmt->fetch()) {
            if ($hasGraph) {
                if (service_has_graph($data['host_id'], $data['service_id'], $this->pearDBMonitoring)) {
                    $serviceCompleteName = $data['fullname'];
                    $serviceCompleteId = $data['host_id'] . '-' . $data['service_id'];
                    $serviceList[] = [
                        'id' => htmlentities($serviceCompleteId),
                        'text' => $serviceCompleteName,
                        'status' => (bool) $data['service_activate'],
                    ];
                }
            } else {
                $serviceCompleteName = $data['fullname'];
                $serviceCompleteId = $data['host_id'] . '-' . $data['service_id'];
                $serviceList[] = [
                    'id' => htmlentities($serviceCompleteId),
                    'text' => $serviceCompleteName,
                    'status' => (bool) $data['service_activate'],
                ];
            }
        }

        return ['items' => $serviceList, 'total' => (int) $this->pearDB->numberRows()];
    }

    /**
     * @param $q
     * @param $aclServices
     * @param array $range
     * @throws Exception
     * @return array
     */
    private function getServicesByHostgroup($q, $aclServices, $range = [])
    {
        $queryValues = [];
        $queryService = 'SELECT SQL_CALC_FOUND_ROWS DISTINCT CONCAT(hg.hg_name, " - ", s.service_description) '
            . 'as fullname, s.service_id, hg.hg_id '
            . 'FROM hostgroup hg, service s, host_service_relation hsr '
            . 'WHERE hsr.hostgroup_hg_id = hg.hg_id '
            . 'AND hsr.service_service_id = s.service_id '
            . 'AND s.service_register = "1" '
            . 'AND CONCAT(hg.hg_name, " - ", s.service_description) LIKE :description '
            . $aclServices . 'ORDER BY fullname ';
        if (! empty($range)) {
            $queryService .= 'LIMIT :offset,:limit';
            $queryValues['offset'] = $range[0];
            $queryValues['limit'] = $range[1];
        }
        $queryValues['description'] = '%' . $q . '%';

        $stmt = $this->pearDB->prepare($queryService);
        $stmt->bindValue(':description', $queryValues['description'], PDO::PARAM_STR);
        if (isset($queryValues['offset'])) {
            $stmt->bindValue(':offset', $queryValues['offset'], PDO::PARAM_INT);
            $stmt->bindValue(':limit', $queryValues['limit'], PDO::PARAM_INT);
        }
        $dbResult = $stmt->execute();
        if (! $dbResult) {
            throw new Exception('An error occured');
        }
        $serviceList = [];
        while ($data = $stmt->fetch()) {
            $serviceCompleteName = $data['fullname'];
            $serviceCompleteId = $data['hg_id'] . '-' . $data['service_id'];
            $serviceList[] = ['id' => htmlentities($serviceCompleteId), 'text' => $serviceCompleteName];
        }

        return ['items' => $serviceList, 'total' => (int) $this->pearDB->numberRows()];
    }
}
