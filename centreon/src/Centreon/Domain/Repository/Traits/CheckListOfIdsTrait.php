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

namespace Centreon\Domain\Repository\Traits;

use Centreon\Infrastructure\CentreonLegacyDB\StatementCollector;

trait CheckListOfIdsTrait
{
    /**
     * Check a list of IDs
     *
     * @param array $ids
     * @param string $tableName not needed if entity had metadata
     * @param string $columnNameOfIdentificator not needed if entity had metadata
     * @return bool
     */
    protected function checkListOfIdsTrait(
        array $ids,
        string $tableName,
        string $columnNameOfIdentificator
    ): bool {
        $count = count($ids);

        $collector = new StatementCollector();
        $sql = "SELECT COUNT(*) AS `total` FROM `{$tableName}` ";

        $isWhere = false;
        foreach ($ids as $x => $value) {
            $key = ":id{$x}";

            $sql .= (! $isWhere ? 'WHERE ' : 'OR ') . "`{$columnNameOfIdentificator}` = {$key} ";
            $collector->addValue($key, $value);

            $isWhere = true;
            unset($x, $value);
        }

        $sql .= 'LIMIT 0, 1';

        $stmt = $this->db->prepare($sql);
        $collector->bind($stmt);
        $stmt->execute();

        $result = $stmt->fetch();

        return (int) $result['total'] === $count;
    }
}
