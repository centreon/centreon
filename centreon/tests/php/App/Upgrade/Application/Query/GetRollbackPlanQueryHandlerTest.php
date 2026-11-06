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

use App\Upgrade\Application\ModuleNotInstalledException;
use App\Upgrade\Application\Query\GetRollbackPlanQuery;
use App\Upgrade\Application\Query\GetRollbackPlanQueryHandler;
use App\Upgrade\Domain\MigrationSet;
use App\Upgrade\Domain\WebMigrationSetEnum;
use PHPUnit\Framework\TestCase;
use Tests\App\Upgrade\Infrastructure\Double\FakeMigrationRunner;
use Tests\App\Upgrade\Infrastructure\Double\FakeModuleRepository;

final class GetRollbackPlanQueryHandlerTest extends TestCase
{
    public function testPlansTheRollbackWithoutRevertingAnything(): void
    {
        $migrationRunner = new FakeMigrationRunner();

        $plan = (new GetRollbackPlanQueryHandler($migrationRunner, new FakeModuleRepository()))(new GetRollbackPlanQuery(MigrationSet::web(WebMigrationSetEnum::Configuration), 'prev'));

        self::assertSame(['configuration\Version1'], $plan);
        self::assertSame(['plan-rollback:configuration:prev'], $migrationRunner->journal->getArrayCopy());
    }

    public function testPlansTheRollbackOfAnInstalledModule(): void
    {
        $migrationRunner = new FakeMigrationRunner();

        (new GetRollbackPlanQueryHandler($migrationRunner, new FakeModuleRepository(['centreon-open-tickets'])))(new GetRollbackPlanQuery(MigrationSet::module('centreon-open-tickets'), 'first'));

        self::assertSame(['plan-rollback:module:centreon-open-tickets:first'], $migrationRunner->journal->getArrayCopy());
    }

    public function testRefusesToPlanTheRollbackOfAModuleNotInstalled(): void
    {
        $migrationRunner = new FakeMigrationRunner();

        try {
            (new GetRollbackPlanQueryHandler($migrationRunner, new FakeModuleRepository()))(new GetRollbackPlanQuery(MigrationSet::module('centreon-open-tickets'), 'prev'));
            self::fail('Expected the plan to be refused');
        } catch (ModuleNotInstalledException) {
            self::assertSame([], $migrationRunner->journal->getArrayCopy());
        }
    }
}
