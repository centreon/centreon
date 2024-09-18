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

require_once __DIR__ . '/centreon_configuration_objects.class.php';

class CentreonAdministrationAclgroup extends CentreonConfigurationObjects
{
    public const ADMIN_ACL_GROUP = 'customer_admin_acl';

    /**
     * @throws RestBadRequestException
     *
     * @return array
     */
    public function getList()
    {
        global $centreon;

        $queryValues = [];
        $filterAclgroup = [];

        $isUserAdmin = $this->isUserAdmin();

        /**
         * Determine if the connected user is an admin (or not). User is admin if
         *  - he is configured as being an admin (onPrem) - is_admin = true
         *  - he belongs to the customer_admin_acl acl_group (cloud).
         */
        if (! $isUserAdmin) {
            $acl = new CentreonACL($centreon->user->user_id, $centreon->user->admin);
            $filterAclgroup[] = ' ag.acl_group_id IN (' . $acl->getAccessGroupsString() . ') ';
        }

        $query = filter_var($this->arguments['q'] ?? '', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        if ($query !== '') {
            $filterAclgroup[] = ' (ag.acl_group_name LIKE :aclGroup OR ag.acl_group_alias LIKE :aclGroup) ';
            $queryValues['aclGroup'] = '%' . $query . '%';
        }

        $limit = array_key_exists('page_limit', $this->arguments)
            ? filter_var($this->arguments['page_limit'], FILTER_VALIDATE_INT)
            : null;

        $page = array_key_exists('page', $this->arguments)
            ? filter_var($this->arguments['page'], FILTER_VALIDATE_INT)
            : null;

        $useResourceAccessManagement = filter_var(
            $this->arguments['use_ram'] ?? false,
            FILTER_VALIDATE_BOOL
        );

        $allHostGroupsFilter = filter_var(
            $this->arguments['all_hostgroups_filter'] ?? false,
            FILTER_VALIDATE_BOOL
        );

        $allServiceGroupsFilter = filter_var(
            $this->arguments['all_servicegroups_filter'] ?? false,
            FILTER_VALIDATE_BOOL
        );

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

        $query = <<<'SQL_WRAP'
            SELECT SQL_CALC_FOUND_ROWS
                ag.acl_group_id,
                ag.acl_group_name
            FROM acl_groups ag
        SQL_WRAP;

        $query .= ! $isUserAdmin
            ? <<<'SQL'
                INNER JOIN acl_res_group_relations argr
                    ON argr.acl_group_id = ag.acl_group_id
                INNER JOIN acl_resources ar
                    ON ar.acl_res_id = argr.acl_res_id
            SQL
            : '';

        $whereCondition = '';

        // In cloud environment we only want to return ACL defines through Resource Access Management page
        if ($useResourceAccessManagement === true) {
            $whereCondition = ' WHERE ag.cloud_specific = 1';
        }

        if ($filterAclgroup !== []) {
            $whereCondition .= empty($whereCondition) ? ' WHERE ' : ' AND ';
            $whereCondition .= implode(' AND ', $filterAclgroup);
        }

        $query .= $whereCondition;
        $query .= ' GROUP BY ag.acl_group_id';

        if ($allHostGroupsFilter && ! $isUserAdmin) {
            $query .= <<<'SQL'
                    HAVING SUM(CASE ar.all_hostgroups WHEN '1' THEN 1 ELSE 0 END) = 0
                SQL;
        }

        if ($allServiceGroupsFilter && ! $isUserAdmin) {
            $query .= <<<'SQL'
                    HAVING SUM(CASE ar.all_servicegroups WHEN '1' THEN 1 ELSE 0 END) = 0
                SQL;
        }

        $query .= ' ORDER BY ag.acl_group_name ' . $range;

        $statement = $this->pearDB->prepare($query);

        if (isset($queryValues['aclGroup'])) {
            $statement->bindValue(':aclGroup', $queryValues['aclGroup'], \PDO::PARAM_STR);
        }
        if (isset($queryValues['offset'])) {
            $statement->bindValue(':offset', $queryValues['offset'], \PDO::PARAM_INT);
            $statement->bindValue(':limit', $queryValues['limit'], \PDO::PARAM_INT);
        }

        $statement->execute();
        $aclGroupList = [];
        while ($data = $statement->fetch()) {
            $aclGroupList[] = [
                'id' => $data['acl_group_id'],
                'text' => $data['acl_group_name'],
            ];
        }

        return [
            'items' => $aclGroupList,
            'total' => (int) $this->pearDB->numberRows(),
        ];
    }

    /**
     * @return bool
     */
    private function isUserAdmin(): bool
    {
        global $centreon;

        if ($centreon->user->admin) {
            return true;
        }

        // Get user's ACL groups
        $acl = new CentreonACL($centreon->user->user_id, $centreon->user->admin);

        return in_array(self::ADMIN_ACL_GROUP, $acl->getAccessGroups(), true);
    }
}
