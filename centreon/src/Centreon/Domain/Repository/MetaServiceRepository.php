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

use Centreon\Infrastructure\CentreonLegacyDB\ServiceEntityRepository;

class MetaServiceRepository extends ServiceEntityRepository
{
    /**
     * Export
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
                t.*
            FROM meta_service AS t
            INNER JOIN meta_service_relation AS msr ON msr.meta_id = t.meta_id
            INNER JOIN ns_host_relation AS hr ON hr.host_host_id = msr.host_id
            WHERE hr.nagios_server_id IN ({$ids})
            GROUP BY t.meta_id
            SQL;

        if ($templateChainList) {
            $list = join(',', $templateChainList);
            $sql .= <<<SQL

                UNION

                SELECT
                    tt.*

                FROM meta_service AS tt
                INNER JOIN meta_service_relation AS _msr ON _msr.meta_id = tt.meta_id
                WHERE _msr.host_id IN ({$list})
                GROUP BY tt.meta_id
                SQL;
        }

        $sql .= <<<'SQL'
            ) AS l
            GROUP BY l.meta_id
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
            FROM meta_service AS t
            WHERE t.meta_id IN ({$ids})
            GROUP BY t.meta_id
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
            TRUNCATE TABLE `meta_service_relation`;
            TRUNCATE TABLE `meta_service`;
            SQL;
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
    }
}
