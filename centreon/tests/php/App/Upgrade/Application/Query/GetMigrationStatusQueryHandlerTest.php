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

namespace Tests\App\Upgrade\Application\Query;

use App\Upgrade\Application\MigrationSetStatus;
use App\Upgrade\Application\Query\GetMigrationStatusQuery;
use App\Upgrade\Application\Query\GetMigrationStatusQueryHandler;
use App\Upgrade\Domain\MigrationSet;
use PHPUnit\Framework\TestCase;
use Tests\App\Upgrade\Infrastructure\Double\FakeMigrationRunner;
use Tests\App\Upgrade\Infrastructure\Double\FakeModuleRepository;

final class GetMigrationStatusQueryHandlerTest extends TestCase
{
    public function testReturnsTheStatusOfTheRequestedSet(): void
    {
        $runner = new FakeMigrationRunner();
        $runner->sets = [MigrationSet::core(MigrationSet::REALTIME)];

        $configuration = MigrationSet::core(MigrationSet::CONFIGURATION);

        $statuses = (new GetMigrationStatusQueryHandler($runner, new FakeModuleRepository()))(new GetMigrationStatusQuery($configuration));

        self::assertEquals([new MigrationSetStatus($configuration, [], ['configuration\Version1'])], $statuses);
    }

    public function testComputesTheStatusOfTheInstalledModulesOnly(): void
    {
        $runner = new FakeMigrationRunner();
        $runner->sets = [MigrationSet::core(MigrationSet::CONFIGURATION), MigrationSet::module('centreon-bam-server'), MigrationSet::module('centreon-open-tickets')];

        $statuses = (new GetMigrationStatusQueryHandler($runner, new FakeModuleRepository(['centreon-open-tickets'])))(new GetMigrationStatusQuery());

        self::assertEquals([MigrationSet::core(MigrationSet::CONFIGURATION), MigrationSet::module('centreon-open-tickets')], $runner->statusRequests);
        self::assertEquals($runner->statusRequests, array_map(static fn (MigrationSetStatus $status): MigrationSet => $status->set, $statuses));
    }
}
