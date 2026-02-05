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

class HostTemplateRelationRepository extends ServiceEntityRepository
{
    /**
     * Export host's templates relation
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
            FROM host_template_relation AS t
            INNER JOIN ns_host_relation AS hr ON hr.host_host_id = t.host_host_id
            WHERE hr.nagios_server_id IN ({$ids})
            GROUP BY t.host_host_id, t.host_tpl_id
            SQL;
        if ($templateChainList) {
            $list = join(',', $templateChainList);
            $sql .= <<<SQL

                UNION

                SELECT
                    tt.*
                FROM host_template_relation AS tt
                WHERE tt.host_host_id IN ({$list})
                GROUP BY tt.host_host_id, tt.host_tpl_id
                SQL;
        }

        $sql .= <<<'SQL'
            ) AS l
            GROUP BY l.host_host_id, l.host_tpl_id
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
     * Get a chain of the related objects
     *
     * @param int[] $pollerIds
     * @param int[] $ba
     * @return array
     */
    public function getChainByPoller(array $pollerIds, ?array $ba = null): array
    {
        // prevent SQL exception
        if (! $pollerIds) {
            return [];
        }

        $ids = join(',', $pollerIds);
        $sql = <<<SQL
            SELECT l.* FROM (
            SELECT
                t.host_tpl_id AS `id`
            FROM host_template_relation AS t
            INNER JOIN ns_host_relation AS hr ON hr.host_host_id = t.host_host_id
            WHERE hr.nagios_server_id IN ({$ids})
            GROUP BY t.host_tpl_id
            SQL;

        // Extract BA hosts
        if ($ba) {
            foreach ($ba as $key => $val) {
                $ba[$key] = "'ba_{$val}'";
            }

            $ba = implode(',', $ba);
            $sql .= ' UNION SELECT t2.host_host_id AS `id`'
                . ' FROM host_service_relation AS t2'
                . ' INNER JOIN service s2 ON s2.service_id = t2.service_service_id'
                . " AND s2.service_description IN({$ba}) GROUP BY t2.host_host_id";
        }

        $sql .= ') AS l GROUP BY l.id';

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        $result = [];

        while ($row = $stmt->fetch()) {
            $result[$row['id']] = $row['id'];
            $this->getChainByParant($row['id'], $result);
        }

        return $result;
    }

    public function getChainByParant($id, &$result)
    {
        $sql = <<<'SQL'
            SELECT
                t.host_tpl_id AS `id`
            FROM host_template_relation AS t
            WHERE t.host_host_id = :id
            GROUP BY t.host_tpl_id
            SQL;
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        while ($row = $stmt->fetch()) {
            $result[$row['id']] = $row['id'];

            $this->getChainByParant($row['id'], $result);
        }

        return $result;
    }
}
