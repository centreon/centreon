<?php

/*
 * Copyright 2005-2024 Centreon
 * Centreon is developed by : Julien Mathis and Romain Le Merlus under
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
 * As a special exception, the copyright holders of this program give Centreon
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of Centreon choice, provided that
 * Centreon also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
 *
 * For more information : contact@centreon.com
 *
 */

require_once _CENTREON_PATH_ . '/www/class/centreonDB.class.php';
require_once __DIR__ . '/centreon_configuration_objects.class.php';

class CentreonConfigurationHostgroup extends CentreonConfigurationObjects
{
    /** @var CentreonDB */
    protected $pearDBMonitoring;

    /**
     * CentreonConfigurationHostgroup constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->pearDBMonitoring = new CentreonDB('centstorage');
    }

    /**
     * @throws Exception
     *
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
            WHERE hg.hg_name LIKE :hostGroupName $aclHostGroups
            ORDER BY hg.hg_name
            $range
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
                'id' => htmlentities((string) $record['hg_id']),
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
     * @throws RestBadRequestException
     *
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
            $hostGroupIds = array_values(explode(',', (string) $hostGroupIdsString));

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

        $request = <<<'SQL'
            SELECT SQL_CALC_FOUND_ROWS DISTINCT
                h.host_name,
                h.host_id
            FROM hostgroup hg
            INNER JOIN hostgroup_relation hgr
                ON hg.hg_id = hgr.hostgroup_hg_id
            INNER JOIN host h
                ON h.host_id = hgr.host_host_id
        SQL;

        if ($filters !== []) {
            $whereCondition .= empty($whereCondition) ? ' WHERE ' : ' AND ';
            $whereCondition .= implode(' AND ', $filters);
        }

        $request .= $whereCondition . $range;

        $statement = $this->pearDB->prepare($request);

        foreach ($queryValues as $key => $value) {
            $statement->bindValue($key, $value, \PDO::PARAM_INT);
        }

        $statement->execute();

        $hostList = [];
        while ($record = $statement->fetch()) {
            $hostList[] = [
                'id' => htmlentities((string) $record['host_id']),
                'text' => $record['host_name'],
            ];
        }

        return [
            'items' => $hostList,
            'total' => (int) $this->pearDB->numberRows(),
        ];
    }
}
