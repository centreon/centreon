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

    public function prepare(string $query, array $options = []): CentreonDBStatement|false
    {
        return new class ($query, [], $this) extends CentreonDBStatement {
            /** @var array{value: string}|false */
            private array|false $row = false;

            public function execute($parameters = null): bool
            {
                /** @var InMemoryOptionsDB $db */
                $db = $this->db;
                $key = (string) ($this->params[':key'] ?? '');

                match (strtok($this->query, ' ')) {
                    'SELECT' => $this->row = isset($db->rows[$key]) ? ['value' => $db->rows[$key]] : false,
                    'UPDATE' => isset($db->rows[$key]) ? $db->rows[$key] = (string) $this->params[':value'] : null,
                    'INSERT' => $db->rows[$key] ??= (string) $this->params[':value'],
                    'DELETE' => $db->rows = array_diff_key($db->rows, [$key => true]),
                    default => throw new \LogicException('Unexpected query: ' . $this->query),
                };

                return true;
            }

            public function fetch(int $mode = \PDO::FETCH_DEFAULT, int $cursorOrientation = \PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): false|array
            {
                return $this->row;
            }
        };
    }
}
