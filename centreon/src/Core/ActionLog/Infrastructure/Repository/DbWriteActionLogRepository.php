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

namespace Core\ActionLog\Infrastructure\Repository;

use Centreon\Domain\Log\LoggerTrait;
use Centreon\Infrastructure\DatabaseConnection;
use Core\ActionLog\Application\Repository\WriteActionLogRepositoryInterface;
use Core\ActionLog\Domain\Model\ActionLog;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;

class DbWriteActionLogRepository extends AbstractRepositoryRDB implements WriteActionLogRepositoryInterface
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
    public function addAction(ActionLog $actionLog): int
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
                    :creation_date,
                    :object_type,
                    :object_id,
                    :object_name,
                    :action_type,
                    :contact_id
                )
                SQL
        );

        $statement = $this->db->prepare($request);
        $statement->bindValue(':creation_date', $actionLog->getCreationDate()->getTimestamp(), \PDO::PARAM_INT);
        $statement->bindValue(':object_type', $actionLog->getObjectType(), \PDO::PARAM_STR);
        $statement->bindValue(':object_id', $actionLog->getObjectId(), \PDO::PARAM_INT);
        $statement->bindValue(':object_name', $actionLog->getObjectName(), \PDO::PARAM_STR);
        $statement->bindValue(':action_type', $actionLog->getActionType(), \PDO::PARAM_STR);
        $statement->bindValue(':contact_id', $actionLog->getContactId(), \PDO::PARAM_INT);

        $statement->execute();

        return (int) $this->db->lastInsertId();
    }

    /**
     * @inheritDoc
     */
    public function addActionDetails(ActionLog $actionLog, array $details): void
    {
        if ($details === []) {
            return;
        }

        $aleadyInTransction = $this->db->inTransaction();
        if (! $aleadyInTransction) {
            $this->db->beginTransaction();
        }

        try {
            $request = $this->translateDbName(
                <<<'SQL'
                    INSERT INTO `:dbstg`.`log_action_modification` (
                        `field_name`,
                        `field_value`,
                        `action_log_id`
                    ) VALUES (
                        :field_name,
                        :field_value,
                        :action_log_id
                    )
                    SQL
            );

            $statement = $this->db->prepare($request);

            foreach ($details as $fieldName => $fieldValue) {
                $statement->bindValue(':field_name', $fieldName, \PDO::PARAM_STR);
                $statement->bindValue(':field_value', $fieldValue, \PDO::PARAM_STR);
                $statement->bindValue(':action_log_id', $actionLog->getId(), \PDO::PARAM_INT);

                $statement->execute();
            }

            if (! $aleadyInTransction) {
                $this->db->commit();
            }
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);

            if (! $aleadyInTransction) {
                $this->db->rollBack();
            }

            throw $ex;
        }
    }
}
