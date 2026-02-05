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

use Centreon\Domain\Repository\Traits\CheckListOfIdsTrait;
use Centreon\Infrastructure\DatabaseConnection;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;

class CommandRepository extends AbstractRepositoryRDB
{
    use CheckListOfIdsTrait;

    /**
     * @param DatabaseConnection $db
     */
    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    /**
     * Check list of IDs
     *
     * @return bool
     */
    public function checkListOfIds(array $ids): bool
    {
        return $this->checkListOfIdsTrait($ids, 'command', 'command_id');
    }

    /**
     * Export
     *
     * @param int[] $pollerIds
     * @return array
     */
    public function export(array $pollerIds): array
    {
        // prevent SQL exception
        if (! $pollerIds) {
            return [];
        }

        $ids = join(',', $pollerIds);

        $sql = <<<SQL
            SELECT
                t1.*
            FROM command AS t1
            INNER JOIN cfg_nagios AS cn1 ON
                cn1.global_service_event_handler = t1.command_id OR
                cn1.global_host_event_handler = t1.command_id
            WHERE
                cn1.nagios_id IN ({$ids})
            GROUP BY t1.command_id

            UNION

            SELECT
                t2.*
            FROM command AS t2
            INNER JOIN poller_command_relations AS pcr2 ON pcr2.command_id = t2.command_id
            WHERE
                pcr2.poller_id IN ({$ids})
            GROUP BY t2.command_id

            UNION

            SELECT
                t3.*
            FROM command AS t3
            INNER JOIN host AS h3 ON
                h3.command_command_id = t3.command_id OR
                h3.command_command_id2 = t3.command_id
            INNER JOIN ns_host_relation AS nhr3 ON nhr3.host_host_id = h3.host_id
            WHERE
                nhr3.nagios_server_id IN ({$ids})
            GROUP BY t3.command_id

            UNION

            SELECT
                t4.*
            FROM command AS t4
            INNER JOIN host AS h4 ON
                h4.command_command_id = t4.command_id OR
                h4.command_command_id2 = t4.command_id
            INNER JOIN ns_host_relation AS nhr4 ON nhr4.host_host_id = h4.host_id
            WHERE
                nhr4.nagios_server_id IN ({$ids})
            GROUP BY t4.command_id

            UNION

            SELECT
                t.*
            FROM command AS t
            INNER JOIN service AS s ON
                s.command_command_id = t.command_id OR
                s.command_command_id2 = t.command_id
            INNER JOIN host_service_relation AS hsr ON
                hsr.service_service_id = s.service_id
            LEFT JOIN hostgroup_relation AS hgr ON hgr.hostgroup_hg_id = hsr.hostgroup_hg_id
            LEFT JOIN ns_host_relation AS nhr ON
            	nhr.host_host_id = hsr.host_host_id OR
            	nhr.host_host_id = hgr.host_host_id
            WHERE
                nhr.nagios_server_id IN ({$ids})
            GROUP BY t.command_id
            SQL;
        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        $result = [];

        while ($row = $stmt->fetch()) {
            $result[] = $row;
        }

        return $result;
    }

    /**
     * Export
     *
     * @param int[] $list
     * @return array
     */
    public function exportList(array $list): array
    {
        // prevent SQL exception
        if (! $list) {
            return [];
        }

        $ids = join(',', $list);

        $sql = <<<SQL
            SELECT
                t.*
            FROM command AS t
            WHERE t.command_id IN ({$ids})
            GROUP BY t.command_id
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
            TRUNCATE TABLE `command`;
            TRUNCATE TABLE `connector`;
            TRUNCATE TABLE `command_arg_description`;
            TRUNCATE TABLE `command_categories_relation`;
            TRUNCATE TABLE `command_categories`;
            TRUNCATE TABLE `on_demand_macro_command`;
            SQL;
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
    }
}
