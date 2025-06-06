<?php

/*
 * Copyright 2005 - 2021 Centreon (https://www.centreon.com/)
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

namespace Centreon\Infrastructure\Repository;

use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Centreon\Infrastructure\DatabaseConnection;

/**
 * This class is designed to perform specific operations on the database
 *
 * @package Centreon\Infrastructure\Repository
 *
 * @deprecated instead use {@see DatabaseRepositoryManager}
 */
class DataStorageEngineRdb implements DataStorageEngineInterface
{
    /**
     * @param DatabaseConnection $db
     */
    public function __construct(readonly private DatabaseConnection $db)
    {
    }

    /**
     * @inheritDoc
     */
    public function startTransaction(): bool
    {
        return $this->db->beginTransaction();
    }

    /**
     * @inheritDoc
     */
    public function commitTransaction(): bool
    {
        return $this->db->commit();
    }

    /**
     * @inheritDoc
     */
    public function rollbackTransaction(): bool
    {
        return $this->db->rollBack();
    }

    /**
     * @inheritDoc
     */
    public function isAlreadyinTransaction(): bool
    {
        return $this->db->inTransaction();
    }
}
