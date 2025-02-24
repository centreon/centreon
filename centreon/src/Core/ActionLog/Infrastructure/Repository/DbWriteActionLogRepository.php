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

use Adaptation\Database\Connection\Collection\BatchInsertParameters;
use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\Exception\ConnectionException;
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use Centreon\Domain\Log\LoggerTrait;
use Core\ActionLog\Application\Repository\WriteActionLogRepositoryInterface;
use Core\ActionLog\Domain\Model\ActionLog;
use Core\Common\Domain\Exception\CollectionException;
use Core\Common\Domain\Exception\RepositoryException;
use Core\Common\Domain\Exception\ValueObjectException;
use Core\Common\Infrastructure\Repository\DatabaseRepository;

/**
 * Class
 *
 * @class   DbWriteActionLogRepository
 * @package Core\ActionLog\Infrastructure\Repository
 */
class DbWriteActionLogRepository extends DatabaseRepository implements WriteActionLogRepositoryInterface
{
    use LoggerTrait;

    /**
     * @param ActionLog $actionLog
     *
     * @throws RepositoryException
     * @return int
     */
    public function addAction(ActionLog $actionLog): int
    {
        try {
            $this->queryBuilder->insert('`:dbstg`.log_action')
                ->values([
                    'action_log_date' => ':creation_date',
                    'object_type' => ':object_type',
                    'object_id' => ':object_id',
                    'object_name' => ':object_name',
                    'action_type' => ':action_type',
                    'log_contact_id' => ':contact_id',
                ]);
            $query = $this->translateDbName($this->queryBuilder->getQuery());

            $this->connection->insert($query, QueryParameters::create([
                QueryParameter::int('creation_date', $actionLog->getCreationDate()->getTimestamp()),
                QueryParameter::string('object_type', $actionLog->getObjectType()),
                QueryParameter::int('object_id', $actionLog->getObjectId()),
                QueryParameter::string('object_name', $actionLog->getObjectName()),
                QueryParameter::string('action_type', $actionLog->getActionType()),
                QueryParameter::int('contact_id', $actionLog->getContactId()),
            ]));

            return (int) $this->connection->getLastInsertId();
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

        $isTransactionActive = $this->connection->isTransactionActive();

        try {
            if (! $isTransactionActive) {
                $this->connection->startTransaction();
            }

            $batchQueryParameters = [];
            foreach ($details as $fieldName => $fieldValue) {
                $batchQueryParameters[] = QueryParameters::create([
                    QueryParameter::string('field_name', $fieldName),
                    QueryParameter::string('field_value', (string) $fieldValue),
                    QueryParameter::int('action_log_id', (int) $actionLog->getId()),
                ]);
            }

            $this->connection->batchInsert(
                $this->translateDbName('`:dbstg`.`log_action_modification`'),
                ['`field_name`', '`field_value`', '`action_log_id`'],
                BatchInsertParameters::create($batchQueryParameters)
            );

            if (! $isTransactionActive) {
                $this->connection->commitTransaction();
            }
        } catch (ValueObjectException|CollectionException|ConnectionException $exception) {
            $this->error(
                "Add action log failed : {$exception->getMessage()}",
                [
                    'action_log' => $actionLog,
                    'exception' => $exception->getContext(),
                ]
            );

            if (! $isTransactionActive) {
                try {
                    $this->connection->rollBackTransaction();
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
