<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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

namespace Core\Common\Infrastructure\Repository;

use Adaptation\Database\Connection\ConnectionInterface;
use Adaptation\Database\Connection\Exception\ConnectionException;
use Core\Common\Domain\Exception\RepositoryException;
use Core\Common\Domain\Repository\RepositoryManagerInterface;

/**
 * Class
 *
 * @class DatabaseRepositoryManager
 * @package Core\Common\Infrastructure\Repository
 */
class DatabaseRepositoryManager implements RepositoryManagerInterface
{
    /**
     * DatabaseRepositoryManager constructor
     *
     * @param ConnectionInterface $connection
     */
    public function __construct(private readonly ConnectionInterface $connection) {}

    /**
     * @inheritDoc
     */
    public function isTransactionActive(): bool
    {
        return $this->connection->isTransactionActive();
    }

    /**
     * @inheritDoc
     */
    public function startTransaction(): void
    {
        try {
            $this->connection->startTransaction();
        } catch (ConnectionException $exception) {
            throw new RepositoryException(
                message : 'Unable to start transaction from database repository manager',
                previous : $exception,
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function commitTransaction(): bool
    {
        try {
            return $this->connection->commitTransaction();
        } catch (ConnectionException $exception) {
            throw new RepositoryException(
                message : 'Unable to commit transaction from database repository manager',
                previous : $exception,
            );
        }
    }

    /**
     * @inheritDoc
     */
    public function rollBackTransaction(): bool
    {
        try {
            return $this->connection->rollBackTransaction();
        } catch (ConnectionException $exception) {
            throw new RepositoryException(
                message : 'Unable to rollback transaction from database repository manager',
                previous : $exception,
            );
        }
    }
}
