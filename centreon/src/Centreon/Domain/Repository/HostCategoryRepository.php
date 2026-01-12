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

class HostCategoryRepository extends ServiceEntityRepository
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
            FROM hostcategories AS t
            INNER JOIN hostcategories_relation AS hc ON hc.hostcategories_hc_id = t.hc_id
            INNER JOIN host AS h ON h.host_id = hc.host_host_id
            INNER JOIN ns_host_relation AS hr ON hr.host_host_id = h.host_id
            WHERE hr.nagios_server_id IN ({$ids})
            GROUP BY t.hc_id
            SQL;
        if ($templateChainList) {
            $list = join(',', $templateChainList);
            $sql .= <<<SQL

                UNION

                SELECT
                    tt.*
                FROM hostcategories AS tt
                INNER JOIN hostcategories_relation AS hc ON hc.hostcategories_hc_id = tt.hc_id AND hc.host_host_id IN ({$list})
                GROUP BY tt.hc_id
                SQL;
        }

        $sql .= <<<'SQL'
            ) AS l
            GROUP BY l.hc_id
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
