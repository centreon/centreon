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

namespace Core\Migration\Infrastructure\Repository;

use Centreon\Domain\Log\LoggerTrait;
use Core\Migration\Application\Repository\MigrationInterface;
use Core\Migration\Application\Repository\ReadAvailableMigrationRepositoryInterface;
use Core\Migration\Domain\Model\NewMigration;

class FsReadAvailableMigrationRepository implements ReadAvailableMigrationRepositoryInterface
{
    use LoggerTrait;

    /** @var MigrationInterface[] */
    private $migrations;

    /**
     * @param \Traversable<MigrationInterface> $migrations
     */
    public function __construct(
        \Traversable $migrations,
    ) {
        if (iterator_count($migrations) === 0) {
            throw new \Exception('Migrations not found');
        }

        $this->migrations = iterator_to_array($migrations);
    }

    /**
     * {@inheritDoc}
     */
    public function findAll(): array
    {
        $migrations = [];

        foreach ($this->migrations as $migration) {
            $shortName = (new \ReflectionClass($migration))->getShortName();

            $migrations[] = new NewMigration(
                $shortName,
                $migration->getModuleName(),
                $migration->getDescription(),
            );
        }

        return $migrations;
    }
}
