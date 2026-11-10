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

declare(strict_types=1);

namespace Tests\App\MonitoringConfiguration\Infrastructure;

use Doctrine\DBAL\Connection;

/**
 * Bulk fixtures for inheritance trees: thousands of templates and links in one statement each.
 */
trait InsertsHostTemplateInheritance
{
    /**
     * Inserts $count active, unlocked templates named "<prefix>-00001"… in one statement.
     *
     * @param string $prefix unique per test, so the names do not collide with other rows
     *
     * @return list<int> their ids, in name order
     */
    private function insertTemplates(Connection $connection, int $count, string $prefix): array
    {
        $names = array_map(static fn (int $index): string => sprintf('%s-%05d', $prefix, $index), range(1, $count));
        $connection->executeStatement(
            'INSERT INTO host (host_name, host_register, host_activate, host_locked) VALUES '
                . implode(', ', array_fill(0, $count, "(?, '0', '1', 0)")),
            $names,
        );

        /** @var list<int|string> $ids */
        $ids = $connection->fetchFirstColumn('SELECT host_id FROM host WHERE host_name LIKE ? ORDER BY host_name', ["{$prefix}-%"]);

        return array_map(intval(...), $ids);
    }

    /**
     * @param array{int, int} ...$links [child, parent] pairs: the child inherits from the parent
     */
    private function linkTemplates(Connection $connection, array ...$links): void
    {
        $connection->executeStatement(
            'INSERT INTO host_template_relation (host_host_id, host_tpl_id, `order`) VALUES '
                . implode(', ', array_fill(0, count($links), '(?, ?, 1)')),
            array_merge(...array_map(array_values(...), $links)),
        );
    }
}
