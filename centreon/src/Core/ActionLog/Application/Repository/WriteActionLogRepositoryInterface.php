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

namespace Core\ActionLog\Application\Repository;

use Core\ActionLog\Domain\Model\ActionLog;
use Core\Common\Domain\Exception\RepositoryException;

/**
 * Interface
 *
 * @class WriteActionLogRepositoryInterface
 * @package Core\ActionLog\Application\Repository
 */
interface WriteActionLogRepositoryInterface
{
    /**
     * @param ActionLog $actionLog
     *
     * @throws RepositoryException
     * @return int
     */
    public function addAction(ActionLog $actionLog): int;

    /**
     * @param ActionLog $actionLog
     * @param array<string, string|int|bool> $details
     *
     * @throws RepositoryException
     */
    public function addActionDetails(ActionLog $actionLog, array $details): void;
}
