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
 * @class CentreonConfigurationHostgroup
 */
class CentreonConfigurationHostgroup extends CentreonConfigurationObjects
{
    /** @var CentreonDB */
    protected $pearDBMonitoring;

    /**
     * CentreonConfigurationHostgroup constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->pearDBMonitoring = new CentreonDB('centstorage');
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
        $aclHostGroups = '';
        $queryValues = [];

        // Get ACL if user is not admin
        if (
            ! $isAdmin
            && $centreon->user->access->hasAccessToAllHostGroups === false
        ) {
            $acl = new CentreonACL($userId, $isAdmin);
            $aclHostGroups .= ' AND hg.hg_id IN (' . $acl->getHostGroupsString() . ') ';
        }

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

        $queryValues['hostGroupName'] = $query !== '' ? '%' . $query . '%' : '%%';

        $range = '';
        if (
            $page !== null
            && $limit !== null
        ) {
            $range = ' LIMIT :offset, :limit';
            $queryValues['offset'] = (int) (($page - 1) * $limit);
            $queryValues['limit'] = $limit;
        }

        $request = <<<SQL
                SELECT SQL_CALC_FOUND_ROWS DISTINCT
                    hg.hg_name,
                    hg.hg_id,
                    hg.hg_activate
                FROM hostgroup hg
                WHERE hg.hg_name LIKE :hostGroupName {$aclHostGroups}
                ORDER BY hg.hg_name
                {$range}
            SQL;

        $statement = $this->pearDB->prepare($request);

        $statement->bindValue(':hostGroupName', $queryValues['hostGroupName'], PDO::PARAM_STR);

        if (isset($queryValues['offset'])) {
            $statement->bindValue(':offset', $queryValues['offset'], PDO::PARAM_INT);
            $statement->bindValue(':limit', $queryValues['limit'], PDO::PARAM_INT);
        }

        $statement->execute();

        $hostGroupList = [];

        while ($record = $statement->fetch()) {
            $hostGroupList[] = [
                'id' => htmlentities($record['hg_id']),
                'text' => $record['hg_name'],
                'status' => (bool) $record['hg_activate'],
            ];
        }

        return [
            'items' => $hostGroupList,
            'total' => (int) $this->pearDB->numberRows(),
        ];
    }

    /**
     * @throws PDOException
     * @throws RestBadRequestException
     * @return array
     */
    public function getHostList()
    {
        global $centreon;

        $userId = $centreon->user->user_id;
        $isAdmin = $centreon->user->admin;
        $queryValues = [];

        // Get ACL if user is not admin
        if (! $isAdmin) {
            $acl = new CentreonACL($userId, $isAdmin);
            if ($centreon->user->access->hasAccessToAllHostGroups === false) {
                $filters[] = ' hg.hg_id IN (' . $acl->getHostGroupsString() . ') ';
            }

            $filters[] = ' h.host_id IN (' . $acl->getHostsString($this->pearDBMonitoring) . ') ';
        }

        // Handle search by host group ids
        $hostGroupIdsString = filter_var($this->arguments['hgid'] ?? '', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        $whereCondition = '';
        if ($hostGroupIdsString !== '') {
            $hostGroupIds = array_values(explode(',', $hostGroupIdsString));

            foreach ($hostGroupIds as $key => $hostGroupId) {
                if (! is_numeric($hostGroupId)) {
                    throw new RestBadRequestException('Error, host group id must be numerical');
                }
                $queryValues[':hostGroupId' . $key] = (int) $hostGroupId;
            }

            $whereCondition .= ' WHERE hg.hg_id IN (' . implode(',', $queryValues) . ')';
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

        $request = <<<'SQL_WRAP'
            SELECT SQL_CALC_FOUND_ROWS DISTINCT
                h.host_name,
                h.host_id
            FROM hostgroup hg
            INNER JOIN hostgroup_relation hgr
                ON hg.hg_id = hgr.hostgroup_hg_id
            INNER JOIN host h
                ON h.host_id = hgr.host_host_id
            SQL_WRAP;

        if ($filters !== []) {
            $whereCondition .= empty($whereCondition) ? ' WHERE ' : ' AND ';
            $whereCondition .= implode(' AND ', $filters);
        }

        $request .= $whereCondition . $range;

        $statement = $this->pearDB->prepare($request);

        foreach ($queryValues as $key => $value) {
            $statement->bindValue($key, $value, PDO::PARAM_INT);
        }

        $statement->execute();

        $hostList = [];
        while ($record = $statement->fetch()) {
            $hostList[] = [
                'id' => htmlentities($record['host_id']),
                'text' => $record['host_name'],
            ];
        }

        return [
            'items' => $hostList,
            'total' => (int) $this->pearDB->numberRows(),
        ];
    }
}
