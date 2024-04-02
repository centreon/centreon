<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

declare(strict_types=1);

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

        if (! empty($query)) {
            $filterAclgroup[] = ' (ag.acl_group_name LIKE :aclGroup OR ag.acl_group_alias LIKE :aclGroup) ';
            $queryValues['aclGroup'] = '%' . $query . '%';
        }

        $limit = array_key_exists('page_limit', $this->arguments)
            ? filter_var($this->arguments['page_limit'], FILTER_VALIDATE_INT)
            : null;

        $page = array_key_exists('page', $this->arguments)
            ? filter_var($this->arguments['page'], FILTER_VALIDATE_INT)
            : null;

        $forCloud = filter_var(
            $this->arguments['for_cloud'] ?? false,
            FILTER_VALIDATE_BOOL
        );

        $allHostGroupsFilter = filter_var(
            $this->arguments['all_hostgroups_filter'] ?? false,
            FILTER_VALIDATE_BOOL
        );

        if (
            $limit === false
            || $page === false
        ) {
            throw new RestBadRequestException('Error, limit must be an integer greater than zero');
        }

        if (
            $page !== null
            && $limit !== null
        ) {
            $range = ' LIMIT :offset, :limit';
            $queryValues['offset'] = (int) (($page - 1) * $limit);
            $queryValues['limit'] = $limit;
        } else {
            $range = '';
        }

        $query = <<<'SQL'
                SELECT SQL_CALC_FOUND_ROWS
                    ag.acl_group_id,
                    ag.acl_group_name
                FROM acl_groups ag
            SQL;

        if ($allHostGroupsFilter && ! $isUserAdmin) {
            $query .= <<<'SQL'
                    INNER JOIN acl_res_group_relations argr
                        ON argr.acl_group_id = ag.acl_group_id
                    INNER JOIN acl_resources ar
                        ON ar.acl_res_id = argr.acl_res_id
                SQL;
        }

        $whereCondition = '';

        // In cloud environment we only want to return ACL defines through Resource Access Management page
        if ($forCloud === true) {
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

        $query .= ' ORDER BY ag.acl_group_name ' . $range;

        $statement = $this->pearDB->prepare($query);

        if (isset($queryValues['aclGroup'])) {
            $statement->bindParam(':aclGroup', $queryValues['aclGroup'], PDO::PARAM_STR);
        }
        if (isset($queryValues['offset'])) {
            $statement->bindParam(':offset', $queryValues['offset'], PDO::PARAM_INT);
            $statement->bindParam(':limit', $queryValues['limit'], PDO::PARAM_INT);
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
