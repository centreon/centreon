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
use Core\Migration\Application\Repository\MigrationsCollectorRepositoryInterface;
use Core\Migration\Domain\Model\NewMigration;
use Doctrine\Bundle\MigrationsBundle\Collector\MigrationsCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SymfonyMigrationsCollectorRepository implements MigrationsCollectorRepositoryInterface
{
    use LoggerTrait;

    public function __construct(
        private readonly MigrationsCollector $migrationsCollector,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function findAll(): array
    {
        $this->migrationsCollector->collect(new Request(), new Response());

        $migrationsData = $this->migrationsCollector->getData();

        if (!array_key_exists('new_migrations', $migrationsData)) {
            throw new \Exception('Cannot retrieve migrations');
        }

        $migrations = [];
        foreach($migrationsData['new_migrations'] as $newMigration) {
            $shortName = str_replace('Migrations\\', '', $newMigration['version']);

            $migrations[] = new NewMigration(
                $shortName,
                'unknown',
                $newMigration['description'],
            );
        }

        return $migrations;
    }
}
