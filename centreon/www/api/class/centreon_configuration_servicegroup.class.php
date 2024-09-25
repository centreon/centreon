<?php

/*
 * Copyright 2005-2020 Centreon
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
 * this program; if not, see <htcontact://www.gnu.org/licenses>.
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
     * @return array
     * @throws PDOException
     * @throws RestBadRequestException
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
            WHERE sg_name LIKE :serviceGroupName $aclServicegroups
            ORDER BY sg.sg_name
            $range
        SQL;

        $statement = $this->pearDB->prepare($request);

        $statement->bindValue(':serviceGroupName', $queryValues['serviceGroupName'], \PDO::PARAM_STR);

        if (isset($queryValues['offset'])) {
            $statement->bindParam(':offset', $queryValues['offset'], \PDO::PARAM_INT);
            $statement->bindParam(':limit', $queryValues['limit'], \PDO::PARAM_INT);
        }
        $statement->execute();
        $serviceGroups = [];
        while ($record = $statement->fetch(\PDO::FETCH_ASSOC)) {
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
     * @return array
     * @throws PDOException
     * @throws RestBadRequestException
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

        $request = <<<SQL
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
        SQL;

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
            $statement->bindValue($key, $value, \PDO::PARAM_INT);
        }

        $statement->execute();

        $serviceList = [];
        while ($record = $statement->fetch(\PDO::FETCH_ASSOC)) {
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
