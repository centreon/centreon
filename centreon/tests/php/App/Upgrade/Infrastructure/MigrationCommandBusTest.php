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

namespace Tests\App\Upgrade\Infrastructure;

use App\Shared\Application\Command\CommandBus;
use App\Upgrade\Application\Command\MigrateCommand;
use App\Upgrade\Application\Command\RollbackMigrationCommand;
use App\Upgrade\Application\MigrationRunner;
use App\Upgrade\Domain\MigrationSet;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * A DDL migration would implicitly commit a transaction opened by the command bus.
 */
final class MigrationCommandBusTest extends KernelTestCase
{
    public function testTheMigrationsRunOutsideOfTheBusTransaction(): void
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        $runner = new class ($connection) implements MigrationRunner {
            /** @var list<int> */
            public array $nestingLevels = [];

            public function __construct(private readonly Connection $connection)
            {
            }

            public function migrate(MigrationSet $set): array
            {
                $this->nestingLevels[] = $this->connection->getTransactionNestingLevel();

                return [];
            }

            public function rollback(MigrationSet $set, string $versionOrAlias): array
            {
                $this->nestingLevels[] = $this->connection->getTransactionNestingLevel();

                return [];
            }

            public function status(?MigrationSet $set = null): array
            {
                return [];
            }
        };
        self::getContainer()->set(MigrationRunner::class, $runner);
        /** @var CommandBus $commandBus */
        $commandBus = self::getContainer()->get(CommandBus::class);
        $nestingLevel = $connection->getTransactionNestingLevel();

        $commandBus->execute(new MigrateCommand(MigrationSet::module('centreon-bus-fixture')));
        $commandBus->execute(new RollbackMigrationCommand(MigrationSet::module('centreon-bus-fixture'), 'prev'));

        self::assertSame([$nestingLevel, $nestingLevel], $runner->nestingLevels);
    }
}
