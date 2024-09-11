<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

namespace Core\Common\Infrastructure\Repository;

use Centreon\Domain\Log\LoggerTrait;
use Centreon\Infrastructure\DatabaseConnection;

class AbstractRepositoryRDB
{
    use LoggerTrait;

    /** @var positive-int Maximum number of elements an SQL query can return */
    protected int $maxItemsByRequest = 5000;

    protected DatabaseConnection $db;

    /**
     * Replace all instances of :dbstg and :db by the real db names.
     * The table names of the database are defined in the services.yaml
     * configuration file.
     *
     * @param string $request Request to translate
     *
     * @return string Request translated
     */
    protected function translateDbName(string $request): string
    {
        return str_replace(
            [':dbstg', ':db'],
            [$this->db->getStorageDbName(), $this->db->getCentreonDbName()],
            $request
        );
    }

    /**
     * Calculate the number of rows returned by a SELECT FOUND_ROWS() query.
     * Don't forget to call this method just after the query that you want to
     * count the number of rows.
     * The previous query must be a SELECT SQL_CALC_FOUND_ROWS query.
     *
     * @return int|null
     */
    protected function calculateNumberOfRows(): ?int
    {
        if (
            false === ($result = $this->db->query('SELECT FOUND_ROWS()'))
            || false === ($value = $result->fetchColumn())
        ) {
            return null;
        }

        return (int) $value;
    }
}
