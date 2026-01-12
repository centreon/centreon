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

class DowntimePeriodRepository extends ServiceEntityRepository
{
    /**
     * Export
     *
     * @param int[] $pollerIds
     * @param array $hostTemplateChain
     * @param array $serviceTemplateChain
     * @return array
     */
    public function export(array $pollerIds, ?array $hostTemplateChain = null, ?array $serviceTemplateChain = null): array
    {
        if (! $pollerIds) {
            return [];
        }

        $sqlFilter = DowntimeRepository::getFilterSql($pollerIds, $hostTemplateChain, $serviceTemplateChain);
        $sql = <<<SQL
            SELECT
                t.*
            FROM downtime_period AS t
            WHERE t.dt_id IN ({$sqlFilter})
            GROUP BY t.dt_id
            SQL;

        $sql2 = <<<'SQL'
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
