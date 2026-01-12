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
use PDO;

class TimezoneRepository extends ServiceEntityRepository
{
    /**
     * Get by ID
     *
     * @param int $id
     * @return array
     */
    public function get(int $id): ?array
    {
        $sql = <<<'SQL'
            SELECT
                t.*
            FROM timezone AS t
            WHERE t.timezone_id = :id
            LIMIT 0, 1
            SQL;
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() === 0) {
            return null;
        }

        return $stmt->fetch();
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
                tz.*,
                t.nagios_id AS `_nagios_id`
            FROM cfg_nagios AS t
            INNER JOIN timezone AS tz ON tz.timezone_id = t.use_timezone
            WHERE t.nagios_id IN ({$ids})
            GROUP BY tz.timezone_id
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
