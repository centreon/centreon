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

declare(strict_types=1);

namespace Core\Command\Infrastructure\Repository;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Repository\RepositoryException;
use Centreon\Infrastructure\DatabaseConnection;
use Core\ActionLog\Application\Repository\WriteActionLogRepositoryInterface;
use Core\ActionLog\Domain\Model\ActionLog;
use Core\Command\Application\Repository\WriteCommandRepositoryInterface;
use Core\Command\Domain\Model\NewCommand;
use Core\Common\Infrastructure\Repository\AbstractRepositoryRDB;

class DbWriteCommandActionLogRepository extends AbstractRepositoryRDB implements WriteCommandRepositoryInterface
{
    use LoggerTrait;
    private const COMMAND_OBJECT_TYPE = 'command';

    public function __construct(
        private readonly WriteCommandRepositoryInterface $writeCommandRepository,
        private readonly WriteActionLogRepositoryInterface $writeActionLogRepository,
        private readonly ContactInterface $contact,
        DatabaseConnection $db,
    ) {
        $this->db = $db;
    }

    public function add(NewCommand $command): int
    {
        try {
            $commandId = $this->writeCommandRepository->add($command);
            if ($commandId === 0) {
                throw new RepositoryException('Command ID cannot be 0');
            }

            $actionLog = new ActionLog(
                objectType: self::COMMAND_OBJECT_TYPE,
                objectId: $commandId,
                objectName: $command->getName(),
                actionType: ActionLog::ACTION_TYPE_ADD,
                contactId: $this->contact->getId()
            );

            $this->writeActionLogRepository->addAction($actionLog);

            return $commandId;
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);

            throw $ex;
        }
    }
}