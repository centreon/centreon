<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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

declare(strict_types=1);

namespace Core\ActionLog\Infrastructure\Repository;

use Adaptation\Database\Collection\QueryParameters;
use Adaptation\Database\Exception\ConnectionException;
use Adaptation\Database\ValueObject\QueryParameter;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Infrastructure\DatabaseConnection;
use Core\ActionLog\Application\Repository\WriteActionLogRepositoryInterface;
use Core\ActionLog\Domain\Model\ActionLog;
use Core\Common\Domain\Exception\RepositoryException;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;

/**
 * Class
 *
 * @class   DbWriteActionLogRepository
 * @package Core\ActionLog\Infrastructure\Repository
 */
class DbWriteActionLogRepository extends AbstractRepositoryRDB implements WriteActionLogRepositoryInterface
{
    use LoggerTrait;

    /**
     * DbWriteActionLogRepository constructor
     *
     * @param DatabaseConnection $db
     */
    public function __construct(DatabaseConnection $db)
    {
        $this->db = $db;
    }

    /**
     * @param ActionLog $actionLog
     *
     * @throws RepositoryException
     * @return int
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
        try {
            $this->db->insert($request, QueryParameters::create([
                QueryParameter::int('creation_date', $actionLog->getCreationDate()->getTimestamp()),
                QueryParameter::string('object_type', $actionLog->getObjectType()),
                QueryParameter::int('object_id', $actionLog->getObjectId()),
                QueryParameter::string('object_name', $actionLog->getObjectName()),
                QueryParameter::string('action_type', $actionLog->getActionType()),
                QueryParameter::int('contact_id', $actionLog->getContactId()),
            ]));

            return (int) $this->db->getLastInsertId();
        } catch (\Throwable $ex) {
            $this->error(
                "Add action log failed : {$ex->getMessage()}",
                [
                    'action_log' => $actionLog,
                    'exception' => [
                        'message' => $ex->getMessage(),
                        'trace' => $ex->getTraceAsString(),
                    ],
                ]
            );

            throw new RepositoryException($ex->getMessage(), ['action_log' => $actionLog], $ex);
        }
    }

    /**
     * @param ActionLog $actionLog
     * @param array $details
     *
     * @throws ConnectionException
     * @throws RepositoryException
     * @return void
     */
    public function addActionDetails(ActionLog $actionLog, array $details): void
    {
        if ($details === []) {
            return;
        }

        $aleadyInTransction = $this->db->isTransactionActive();
        if (! $aleadyInTransction) {
            $this->db->startTransaction();
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

            foreach ($details as $fieldName => $fieldValue) {
                $this->db->insert($request, QueryParameters::create([
                    QueryParameter::string('field_name', $fieldName),
                    QueryParameter::string('field_value', $fieldValue),
                    QueryParameter::int('action_log_id', $actionLog->getId()),
                ]));
            }

            if (! $aleadyInTransction) {
                $this->db->commit();
            }
        } catch (\Throwable $ex) {
            $this->error(
                "Add action log failed : {$ex->getMessage()}",
                [
                    'action_log' => $actionLog,
                    'exception' => [
                        'message' => $ex->getMessage(),
                        'trace' => $ex->getTraceAsString(),
                    ],
                ]
            );

            if (! $aleadyInTransction) {
                try {
                    $this->db->rollBack();
                } catch (ConnectionException $e) {
                    $this->error(
                        "Rollback failed : {$e->getMessage()}",
                        [
                            'action_log' => $actionLog,
                            'exception' => [
                                'message' => $e->getMessage(),
                                'trace' => $e->getTraceAsString(),
                            ],
                        ]
                    );
                }
            }

            throw new RepositoryException($ex->getMessage(), ['action_log' => $actionLog], $ex);
        }
    }
}
