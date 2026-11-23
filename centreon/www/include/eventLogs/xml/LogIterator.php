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

/**
 * Gives the rows of an executed logs query one by one.
 *
 * With an unbuffered query, PHP then holds a single row at a time, whatever the number of rows of the search.
 *
 * @implements IteratorAggregate<int, array<string, mixed>>
 */
readonly class LogIterator implements IteratorAggregate
{
    public function __construct(private PDOStatement $statement)
    {
    }

    /**
     * @throws PDOException
     *
     * @return Generator<int, array<string, mixed>>
     */
    public function getIterator(): Generator
    {
        try {
            while (($log = $this->statement->fetch(PDO::FETCH_ASSOC)) !== false) {
                yield $log;
            }
        } finally {
            // An unbuffered query blocks its connection until its cursor is closed.
            $this->statement->closeCursor();
        }
    }
}
