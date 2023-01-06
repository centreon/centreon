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

namespace Core\HostGroup\Infrastructure\Repository;

use Centreon\Domain\Log\LoggerTrait;
use Centreon\Infrastructure\DatabaseConnection;
use Centreon\Infrastructure\Repository\AbstractRepositoryDRB;
use Core\HostGroup\Application\Repository\WriteHostGroupRepositoryInterface;

class DbWriteHostGroupRepository extends AbstractRepositoryDRB implements WriteHostGroupRepositoryInterface
{
    use LoggerTrait;

    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    /**
     * @param int $hostGroupId
     *
     * @throws \PDOException
     */
    public function deleteHostGroup(int $hostGroupId): void
    {
        $this->info('Delete host group', ['id' => $hostGroupId]);

        $query = <<<'SQL'
            DELETE FROM `:db`.`hostgroup`
            WHERE hg_id = :hostgroup_id
            SQL;

        $statement = $this->db->prepare($this->translateDbName($query));
        $statement->bindValue(':hostgroup_id', $hostGroupId, \PDO::PARAM_INT);
        $statement->execute();
    }
}
