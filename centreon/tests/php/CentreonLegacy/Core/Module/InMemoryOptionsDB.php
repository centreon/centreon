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

namespace Tests\CentreonLegacy\Core\Module;

use Centreon\Test\Mock\CentreonDB;
use Centreon\Test\Mock\CentreonDBStatement;

/**
 * The options table in memory, as UpgradeProgress queries it.
 */
final class InMemoryOptionsDB extends CentreonDB
{
    /** @var array<string, string> */
    public array $rows = [];

    /** @var list<string> the executed writes */
    public array $writes = [];

    public function prepare(string $query, array $options = []): CentreonDBStatement|false
    {
        return new class ($query, [], $this) extends CentreonDBStatement {
            /** @var array{value: string}|false */
            private array|false $row = false;

            private int $affectedRows = 0;

            public function execute($parameters = null): bool
            {
                /** @var InMemoryOptionsDB $db */
                $db = $this->db;
                $key = (string) ($this->params[':key'] ?? '');

                $verb = (string) strtok($this->query, ' ');
                if ($verb !== 'SELECT') {
                    $db->writes[] = $verb;
                }

                match ($verb) {
                    'SELECT' => $this->row = isset($db->rows[$key]) ? ['value' => $db->rows[$key]] : false,
                    'UPDATE' => $this->update($db, $key),
                    'INSERT' => $db->rows[$key] ??= (string) $this->params[':value'],
                    'DELETE' => $db->rows = array_diff_key($db->rows, [$key => true]),
                    default => throw new \LogicException('Unexpected query: ' . $this->query),
                };

                return true;
            }

            public function rowCount(): int
            {
                return $this->affectedRows;
            }

            private function update(InMemoryOptionsDB $db, string $key): void
            {
                $value = (string) $this->params[':value'];
                if (! isset($db->rows[$key])) {
                    $this->affectedRows = 0;

                    return;
                }

                // As MySQL, an unchanged row is not counted as affected.
                $this->affectedRows = $db->rows[$key] !== $value ? 1 : 0;
                $db->rows[$key] = $value;
            }

            public function fetch(int $mode = \PDO::FETCH_DEFAULT, int $cursorOrientation = \PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): false|array
            {
                return $this->row;
            }
        };
    }
}
