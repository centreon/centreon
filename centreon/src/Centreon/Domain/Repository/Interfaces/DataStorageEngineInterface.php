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

namespace Centreon\Domain\Repository\Interfaces;

use Core\Common\Application\Repository\RepositoryManagerInterface;

/**
 * This interface is designed to perform specific operations on the data storage engine.
 *
 * @deprecated instead use {@see RepositoryManagerInterface}
 */
interface DataStorageEngineInterface
{
    /**
     * Rollback the operations in the transaction.
     *
     * @throws \Exception
     *
     * @return bool
     */
    public function rollbackTransaction(): bool;

    /**
     * Start a transaction.
     *
     * @throws \Exception
     *
     * @return bool
     */
    public function startTransaction(): bool;

    /**
     * Commit the operations in the transaction.
     *
     * @throws \Exception
     *
     * @return bool
     */
    public function commitTransaction(): bool;

    /**
     * Check if a transaction is already started.
     *
     * @return bool
     */
    public function isAlreadyinTransaction(): bool;
}
