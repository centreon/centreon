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

namespace Core\Migration\Application\Repository;

use Core\Migration\Domain\Model\ExecutedMigration;
use Core\Migration\Domain\Model\NewMigration;

interface ReadMigrationRepositoryInterface
{
    /**
     * Return all the migrations.
     *
     * @throws \Throwable
     *
     * @return NewMigration[]
     */
    public function findAvailableMigrations(): array;

    /**
     * Return migrations already executed.
     *
     * @throws \Throwable
     *
     * @return ExecutedMigration[]
     */
    public function findExecutedMigrations(): array;

    /**
     * Return migrations not yet executed.
     *
     * @throws \Throwable
     *
     * @return NewMigration[]
     */
    public function findNewMigrations(): array;
}
