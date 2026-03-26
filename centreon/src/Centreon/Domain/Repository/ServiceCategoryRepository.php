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

class ServiceCategoryRepository extends ServiceEntityRepository
{
    /**
     * Export
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
            FROM service_categories AS t
            INNER JOIN service_categories_relation AS scr ON scr.sc_id = t.sc_id
            INNER JOIN host_service_relation AS hsr ON hsr.service_service_id = scr.service_service_id
            LEFT JOIN hostgroup AS hg ON hg.hg_id = hsr.hostgroup_hg_id
            LEFT JOIN hostgroup_relation AS hgr ON hgr.hostgroup_hg_id = hg.hg_id
            INNER JOIN ns_host_relation AS hr ON hr.host_host_id = hsr.host_host_id OR hr.host_host_id = hgr.host_host_id
            WHERE hr.nagios_server_id IN ({$ids})
            GROUP BY t.sc_id
            SQL;

        if ($templateChainList) {
            $list = join(',', $templateChainList);
            $sql .= <<<SQL

                UNION

                SELECT
                    tt.*
                FROM service_categories AS tt
                INNER JOIN service_categories_relation AS _scr ON _scr.sc_id = tt.sc_id AND _scr.service_service_id IN ({$list})
                GROUP BY tt.sc_id
                SQL;
        }

        $sql .= <<<'SQL'
            ) AS l
            GROUP BY l.sc_id
            SQL;

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        $result = [];

        while ($row = $stmt->fetch()) {
            $result[] = $row;
        }

        return $result;
    }
}
