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

namespace Core\Command\Infrastructure\Repository;

use Centreon\Domain\Log\LoggerTrait;
use Centreon\Infrastructure\DatabaseConnection;
use Core\Command\Application\Repository\ReadCommandRepositoryInterface;
use Core\Common\Domain\CommandType;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;

class DbReadCommandRepository extends AbstractRepositoryRDB implements ReadCommandRepositoryInterface
{
    use LoggerTrait;

    /**
     * @param DatabaseConnection $db
     */
    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

        /**
     * @inheritDoc
     */
    public function exists(int $commandId): bool
    {
        $this->info(sprintf('Check existence of command with id #%d', $commandId));

        $request = $this->translateDbName(
            <<<'SQL'
                SELECT 1
                FROM `:db`.command
                WHERE command_id = :commandId
                SQL
        );
        $statement = $this->db->prepare($request);
        $statement->bindValue(':commandId', $commandId, \PDO::PARAM_INT);
        $statement->execute();

        return (bool) $statement->fetchColumn();
    }

    /**
     * @inheritDoc
     */
    public function existsByIdAndCommandType(int $commandId, CommandType $commandType): bool
    {
        $this->info(sprintf('Check existence of command with id #%d and type %s', $commandId, $commandType->value));

        $request = $this->translateDbName(
            <<<'SQL'
                SELECT 1
                FROM `:db`.command
                WHERE command_id = :commandId
                    AND command_type = :commandType
                SQL
        );
        $statement = $this->db->prepare($request);
        $statement->bindValue(':commandId', $commandId, \PDO::PARAM_INT);
        $statement->bindValue(':commandType', $commandType->value, \PDO::PARAM_INT);
        $statement->execute();

        return (bool) $statement->fetchColumn();
    }
}
