<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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

declare(strict_types = 1);

namespace Core\Common\Infrastructure\Repository;

use Centreon\Infrastructure\DatabaseConnection;
use Core\Common\Application\Repository\WriteLogActionRepositoryInterface;
use Core\Common\Domain\LogAction;

class DbWriteLogActionRepository extends AbstractRepositoryRDB implements WriteLogActionRepositoryInterface
{
    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     */
    public function add(LogAction $log): void
    {
        $request = $this->translateDbName(
            <<<'SQL'
                INSERT INTO `:dbstg`.`log_action` (
                    `action_log_date`,
                    `object_type`,
                    `object_id`,
                    `object_name`,
                    `action_type`,
                    `log_contact_id`
                ) VALUES (
                    :action_log_date,
                    :object_type,
                    :object_id,
                    :object_name,
                    :action_type,
                    :log_contact_id
                )
                SQL
        );

        $statement = $this->db->prepare($request);
        $statement->bindValue(':action_log_date', $log->getDateTime()->getTimestamp(), \PDO::PARAM_INT);
        $statement->bindValue(':object_type', $log->getObjectType(), \PDO::PARAM_STR);
        $statement->bindValue(':object_id', $log->getObjectId(), \PDO::PARAM_INT);
        $statement->bindValue(':object_name', $log->getObjectName(), \PDO::PARAM_STR);
        $statement->bindValue(':action_type', $log->getActionType(), \PDO::PARAM_STR);
        $statement->bindValue(':log_contact_id', $log->getContactId(), \PDO::PARAM_INT);

        $statement->execute();
    }
}
