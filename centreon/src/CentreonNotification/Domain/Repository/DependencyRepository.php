<?php

/*
 * Copyright 2005 - 2019 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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

namespace CentreonNotification\Domain\Repository;

use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Infrastructure\CentreonLegacyDB\StatementCollector;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;

class DependencyRepository extends AbstractRepositoryRDB
{
    /**
     * @param DatabaseConnection $db
     */
    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    /**
     * Remove dependency by ID
     *
     * @param int $id
     * @return void
     */
    public function removeById(int $id): void
    {
        $request = <<<SQL
            DELETE FROM `:db`.`dependency` WHERE dep_id = :dependencyId
        SQL;

        $collector = new StatementCollector();
        $collector->addValue(':dependencyId', $id);

        $statement = $this->db->prepare($this->translateDbName($request));
        $collector->bind($statement);
        $statement->execute();
    }
}
