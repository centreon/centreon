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

use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\ConnectionInterface;
use Adaptation\Database\Connection\Exception\ConnectionException;
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use Adaptation\Database\QueryBuilder\Adapter\Dbal\DbalQueryBuilderAdapter;
use Centreon\Domain\Log\LoggerTrait;
use Core\ActionLog\Application\Repository\WriteActionLogRepositoryInterface;
use Core\ActionLog\Domain\Model\ActionLog;
use Core\Common\Domain\Exception\CollectionException;
use Core\Common\Domain\Exception\RepositoryException;
use Core\Common\Domain\Exception\ValueObjectException;
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
     * @param ConnectionInterface $db
     */
    public function __construct(ConnectionInterface $db)
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
        } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
            $this->error(
                "Add action log failed : {$exception->getMessage()}",
                [
                    'action_log' => $actionLog,
                    'exception' => $exception->getContext(),
                ]
            );

            throw new RepositoryException(
                "Add action log failed : {$exception->getMessage()}",
                ['action_log' => $actionLog],
                $exception
            );
        }
    }

    /**
     * @param ActionLog $actionLog
     * @param array<string,mixed> $details
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

        if ($actionLog->getId() === null) {
            throw new RepositoryException('Action log id is required to add details');
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
                    QueryParameter::string('field_value', (string) $fieldValue),
                    QueryParameter::int('action_log_id', (int) $actionLog->getId()),
                ]));
            }

            if (! $aleadyInTransction) {
                $this->db->commitTransaction();
            }
        } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
            $this->error(
                "Add action log failed : {$exception->getMessage()}",
                [
                    'action_log' => $actionLog,
                    'exception' => $exception->getContext(),
                ]
            );

            if (! $aleadyInTransction) {
                try {
                    $this->db->rollBackTransaction();
                } catch (ConnectionException $rollbackException) {
                    $this->error(
                        "Rollback failed for action logs: {$rollbackException->getMessage()}",
                        [
                            'action_log' => $actionLog,
                            'exception' => $rollbackException->getContext(),
                        ]
                    );

                    throw new RepositoryException(
                        "Rollback failed for action logs: {$rollbackException->getMessage()}",
                        ['action_log' => $actionLog],
                        $rollbackException
                    );
                }
            }

            throw new RepositoryException(
                "Add action log failed : {$exception->getMessage()}",
                ['action_log' => $actionLog],
                $exception
            );
        }
    }
}
