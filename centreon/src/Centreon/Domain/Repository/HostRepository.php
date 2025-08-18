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

namespace Centreon\Domain\Repository;

use Centreon\Infrastructure\DatabaseConnection;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;

class HostRepository extends AbstractRepositoryRDB
{
    /**
     * @param DatabaseConnection $db
     */
    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    /**
     * Export hosts
     *
     * @param int[] $pollerIds
     * @param array $templateChainList
     * @return array
     */
    public function export(array $pollerIds, ?array $templateChainList = null): array
    {
        // prevent SQL exception
        if (! $pollerIds) {
            return [];
        }

        $ids = join(',', $pollerIds);

        $sql = <<<SQL
            SELECT l.* FROM(
            SELECT
                t.*,
                hr.nagios_server_id AS `_nagios_id`
            FROM host AS t
            INNER JOIN ns_host_relation AS hr ON hr.host_host_id = t.host_id
            WHERE hr.nagios_server_id IN ({$ids})
            GROUP BY t.host_id
            SQL;

        if ($templateChainList) {
            $list = join(',', $templateChainList);
            $sql .= <<<SQL

                UNION

                SELECT
                    tt.*,
                    NULL AS `_nagios_id`
                FROM host AS tt
                WHERE tt.host_id IN ({$list})
                GROUP BY tt.host_id
                SQL;
        }

        $sql .= <<<'SQL'
            ) AS l
            GROUP BY l.host_id
            SQL;

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        $result = [];

        while ($row = $stmt->fetch()) {
            $result[] = $row;
        }

        return $result;
    }

    public function truncate(): void
    {
        $sql = <<<'SQL'
            TRUNCATE TABLE `ns_host_relation`;
            TRUNCATE TABLE `hostgroup_relation`;
            TRUNCATE TABLE `hostgroup`;
            TRUNCATE TABLE `hostcategories_relation`;
            TRUNCATE TABLE `hostcategories`;
            TRUNCATE TABLE `host_hostparent_relation`;
            TRUNCATE TABLE `on_demand_macro_host`;
            TRUNCATE TABLE `hostgroup_hg_relation`;
            TRUNCATE TABLE `extended_host_information`;
            TRUNCATE TABLE `host`;
            TRUNCATE TABLE `host_template_relation`;
            SQL;
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
    }
}
