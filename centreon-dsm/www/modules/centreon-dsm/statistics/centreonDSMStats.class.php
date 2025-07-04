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

require_once __DIR__ . '/../../../class/centreonDB.class.php';
require_once __DIR__ . '/../../../class/exceptions/StatisticException.php';

class CentreonDSMStats
{
    /** @var CentreonDB */
    private $db;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->db = new CentreonDB();
    }

    /**
     * Get statistics of module
     * @throws StatisticException
     * @return array{
     *     dsm: array{}|array{
     *         pools: scalar,
     *         slot_min: scalar,
     *         slot_max: scalar,
     *         slot_avg: scalar,
     *     }
     * }
     */
    public function getStats(): array
    {
        try {
            $data = $this->getSlotsUsage();

            return ['dsm' => $data];
        } catch (Throwable $e) {
            throw new StatisticException(
                message: 'Unable to get Centreon DSM statistics: ' . $e->getMessage(),
                previous: $e
            );
        }
    }

    /**
     * Get Auto Discovery services rules usage
     * @return array{}|array{
     *     pools: scalar,
     *     slot_min: scalar,
     *     slot_max: scalar,
     *     slot_avg: scalar,
     * }
     */
    public function getSlotsUsage(): array
    {
        $data = [];

        $query = <<<'SQL'
            SELECT COUNT(pool_id) AS pools, 
                   MIN(pool_number) AS min,
                   MAX(pool_number) AS max,
                   AVG(pool_number) as avg
            FROM mod_dsm_pool
            SQL;
        $result = $this->db->query($query);
        while ($row = $result->fetch()) {
            $data = [
                'pools' => $row['pools'],
                'slot_min' => $row['min'],
                'slot_max' => $row['max'],
                'slot_avg' => $row['avg'],
            ];
        }

        return $data;
    }
}
