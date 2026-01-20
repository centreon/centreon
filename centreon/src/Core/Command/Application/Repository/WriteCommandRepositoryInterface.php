<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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

namespace Core\Command\Application\Repository;

use Core\Command\Domain\Model\NewCommand;
use Core\Command\Domain\Model\Command;

interface WriteCommandRepositoryInterface
{
    /**
     * Add a command.
     *
     * @param NewCommand $command
     *
     * @throws \Throwable
     *
     * @return int
     */
    public function add(NewCommand $command): int;

    /**
     * Update a command.
     *
     * @param Command $command
     *
     * @throws \Throwable
     *
     * @return int
     */
    public function update(Command $originalCommand, Command $updatedCommand): void;

     /**
      * Delete a command by its id.
      *
      * @param int $commandId
      *
      * @throws \Throwable
      *
      * @return void
      */
     public function delete(int $commandId): void;
}
