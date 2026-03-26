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
 * @class CentreonConfigurationServicegroup
 */
class CentreonConfigurationServicegroup extends CentreonConfigurationObjects
{
    /** @var CentreonDB */
    protected $pearDBMonitoring;

    /**
     * CentreonConfigurationServicegroup constructor
     */
    public function __construct()
    {
        $this->pearDBMonitoring = new CentreonDB('centstorage');
        parent::__construct();
    }

    /**
     * @throws PDOException
     * @throws RestBadRequestException
     * @return array
     */
    public function getList()
    {
        global $centreon;

        $isAdmin = $centreon->user->admin;
        $userId = $centreon->user->user_id;
        $queryValues = [];
        $aclServicegroups = '';

        $query = filter_var(
            $this->arguments['q'] ?? '',
            FILTER_SANITIZE_FULL_SPECIAL_CHARS
        );

        $limit = array_key_exists('page_limit', $this->arguments)
            ? filter_var($this->arguments['page_limit'], FILTER_VALIDATE_INT)
            : null;

        $page = array_key_exists('page', $this->arguments)
            ? filter_var($this->arguments['page'], FILTER_VALIDATE_INT)
            : null;

        if ($limit === false) {
            throw new RestBadRequestException('Error, limit must be an integer greater than zero');
        }

        if ($page === false) {
            throw new RestBadRequestException('Error, page must be an integer greater than zero');
        }

        $queryValues['serviceGroupName'] = $query !== '' ? '%' . $query . '%' : '%%';

        $range = '';
        if (
            $page !== null
            && $limit !== null
        ) {
            $range = ' LIMIT :offset, :limit';
            $queryValues['offset'] = (int) (($page - 1) * $limit);
            $queryValues['limit'] = $limit;
        }
        // Get ACL if user is not admin or does not have access to all servicegroups
        if (
            ! $isAdmin
            && $centreon->user->access->hasAccessToAllServiceGroups === false
        ) {
            $acl = new CentreonACL($userId, $isAdmin);
            $aclServicegroups .= ' AND sg_id IN (' . $acl->getServiceGroupsString('ID') . ') ';
        }

        $request = <<<SQL
                SELECT SQL_CALC_FOUND_ROWS DISTINCT
                    sg.sg_id,
                    sg.sg_name,
                    sg.sg_activate
                FROM servicegroup sg
                WHERE sg_name LIKE :serviceGroupName {$aclServicegroups}
                ORDER BY sg.sg_name
                {$range}
            SQL;

        $statement = $this->pearDB->prepare($request);

        $statement->bindValue(':serviceGroupName', $queryValues['serviceGroupName'], PDO::PARAM_STR);

        if (isset($queryValues['offset'])) {
            $statement->bindParam(':offset', $queryValues['offset'], PDO::PARAM_INT);
            $statement->bindParam(':limit', $queryValues['limit'], PDO::PARAM_INT);
        }
        $statement->execute();
        $serviceGroups = [];
        while ($record = $statement->fetch(PDO::FETCH_ASSOC)) {
            $serviceGroups[] = [
                'id' => htmlentities($record['sg_id']),
                'text' => $record['sg_name'],
                'status' => (bool) $record['sg_activate'],
            ];
        }

        return [
            'items' => $serviceGroups,
            'total' => (int) $this->pearDB->numberRows(),
        ];
    }

    /**
     * @throws PDOException
     * @throws RestBadRequestException
     * @return array
     */
    public function getServiceList()
    {
        global $centreon;

        $isAdmin = $centreon->user->admin;
        $userId = $centreon->user->user_id;

        $queryValues = [];

        $serviceGroupIdsString = filter_var($this->arguments['sgid'] ?? '', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        $whereCondition = '';

        // Handle the search by service groups IDs
        if ($serviceGroupIdsString !== '') {
            $serviceGroupIds = array_values(explode(',', $serviceGroupIdsString));

            foreach ($serviceGroupIds as $key => $serviceGroupId) {
                if (! is_numeric($serviceGroupId)) {
                    throw new RestBadRequestException('Error, service group id must be numerical');
                }
                $queryValues[':serviceGroupId' . $key] = (int) $serviceGroupId;
            }

            $whereCondition .= ' WHERE sg.sg_id IN (' . implode(',', array_keys($queryValues)) . ')';
        }

        $filters = [];
        // Get ACL if user is not admin
        if (! $isAdmin) {
            $acl = new CentreonACL($userId, $isAdmin);
            if ($centreon->user->access->hasAccessToAllServiceGroups === false) {
                $filters[] = ' sg.sg_id IN (' . $acl->getServiceGroupsString() . ') ';
            }

            $filters[] = ' s.service_id IN (' . $acl->getServicesString($this->pearDBMonitoring) . ') ';
        }

        $request = <<<'SQL_WRAP'
                SELECT SQL_CALC_FOUND_ROWS DISTINCT
                    s.service_id,
                    s.service_description,
                    h.host_name,
                    h.host_id
                FROM servicegroup sg
                INNER JOIN servicegroup_relation sgr
                    ON sgr.servicegroup_sg_id = sg.sg_id
                INNER JOIN service s
                    ON s.service_id = sgr.service_service_id
                INNER JOIN host_service_relation hsr
                    ON hsr.service_service_id = s.service_id
                INNER JOIN host h ON h.host_id = hsr.host_host_id
            SQL_WRAP;

        if ($filters !== []) {
            $whereCondition .= empty($whereCondition) ? ' WHERE ' : ' AND ';
            $whereCondition .= implode(' AND ', $filters);
        }

        // Handle pagination and limit
        $limit = array_key_exists('page_limit', $this->arguments)
            ? filter_var($this->arguments['page_limit'], FILTER_VALIDATE_INT)
            : null;

        $page = array_key_exists('page', $this->arguments)
            ? filter_var($this->arguments['page'], FILTER_VALIDATE_INT)
            : null;

        if ($limit === false) {
            throw new RestBadRequestException('Error, limit must be an integer greater than zero');
        }

        if ($page === false) {
            throw new RestBadRequestException('Error, page must be an integer greater than zero');
        }

        $range = '';
        if (
            $page !== null
            && $limit !== null
        ) {
            $range = ' LIMIT :offset, :limit';
            $queryValues['offset'] = (int) (($page - 1) * $limit);
            $queryValues['limit'] = $limit;
        }

        $request .= $whereCondition . $range;

        $statement = $this->pearDB->prepare($request);

        foreach ($queryValues as $key => $value) {
            $statement->bindValue($key, $value, PDO::PARAM_INT);
        }

        $statement->execute();

        $serviceList = [];
        while ($record = $statement->fetch(PDO::FETCH_ASSOC)) {
            $serviceList[] = [
                'id' => $record['host_id'] . '_' . $record['service_id'],
                'text' => $record['host_name'] . ' - ' . $record['service_description'],
            ];
        }

        return [
            'items' => $serviceList,
            'total' => (int) $this->pearDB->numberRows(),
        ];
    }
}
