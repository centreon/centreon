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

namespace Core\Common\Domain\Repository;

use Core\Common\Domain\Exception\RepositoryException;

/**
 * Interface
 *
 * @class RepositoryManagerInterface
 * @package Core\Common\Domain\Repository
 */
interface RepositoryManagerInterface
{
    /**
     * Checks whether a transaction is currently active.
     *
     * @return bool TRUE if a transaction is currently active, FALSE otherwise
     */
    public function isTransactionActive(): bool;

    /**
     * Opens a new transaction. This must be closed by calling one of the following methods:
     * {@see commitTransaction} or {@see rollBackTransaction}
     *
     * @throws RepositoryException
     * @return void
     */
    public function startTransaction(): void;

    /**
     * To validate a transaction.
     *
     * @throws RepositoryException
     * @return bool
     */
    public function commitTransaction(): bool;

    /**
     * To cancel a transaction.
     *
     * @throws RepositoryException
     * @return bool
     */
    public function rollBackTransaction(): bool;
}
