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
use App\Upgrade\Application\ModuleNotInstalledException;
use App\Upgrade\Application\Query\GetMigrationStatusQuery;
use App\Upgrade\Application\Query\GetMigrationStatusQueryHandler;
use App\Upgrade\Application\TargetVersionRecorder;
use App\Upgrade\Domain\MigrationSet;
use PHPUnit\Framework\TestCase;
use Tests\App\Upgrade\Infrastructure\Double\FakeMigrationRunner;
use Tests\App\Upgrade\Infrastructure\Double\FakeModuleRepository;
use Tests\App\Upgrade\Infrastructure\Double\FakeUpdateRepository;
use Tests\App\Upgrade\Infrastructure\Double\FakeUpdateScriptFinder;

final class GetMigrationStatusQueryHandlerTest extends TestCase
{
    public function testReturnsTheStatusOfTheRequestedSet(): void
    {
        $runner = new FakeMigrationRunner();
        $runner->sets = [MigrationSet::core(MigrationSet::REALTIME)];

        $configuration = MigrationSet::core(MigrationSet::CONFIGURATION);

        $statuses = (new GetMigrationStatusQueryHandler($runner, new FakeModuleRepository(), $this->recorder()))(new GetMigrationStatusQuery($configuration));

        self::assertEquals([new MigrationSetStatus($configuration, [], ['configuration\Version1'])], $statuses);
    }

    public function testComputesTheStatusOfTheInstalledModulesOnly(): void
    {
        $runner = new FakeMigrationRunner();
        $runner->sets = [MigrationSet::core(MigrationSet::CONFIGURATION), MigrationSet::module('centreon-bam-server'), MigrationSet::module('centreon-open-tickets')];

        $statuses = (new GetMigrationStatusQueryHandler($runner, new FakeModuleRepository(['centreon-open-tickets']), $this->recorder()))(new GetMigrationStatusQuery());

        self::assertEquals([MigrationSet::core(MigrationSet::CONFIGURATION), MigrationSet::module('centreon-open-tickets')], $runner->statusRequests);
        self::assertEquals($runner->statusRequests, array_map(static fn (MigrationSetStatus $status): MigrationSet => $status->set, $statuses));
    }

    public function testASetFailingItsChecksDoesNotHideTheOthers(): void
    {
        $runner = new FakeMigrationRunner();
        $runner->sets = [MigrationSet::core(MigrationSet::REALTIME), MigrationSet::core(MigrationSet::CONFIGURATION)];
        $runner->failingSets = [MigrationSet::REALTIME];

        $statuses = (new GetMigrationStatusQueryHandler($runner, new FakeModuleRepository(), $this->recorder()))(new GetMigrationStatusQuery());

        self::assertEquals([
            new MigrationSetStatus(MigrationSet::core(MigrationSet::REALTIME), [], [], 'Status of "realtime" failed'),
            new MigrationSetStatus(MigrationSet::core(MigrationSet::CONFIGURATION), [], ['configuration\Version1']),
        ], $statuses);
    }

    public function testReportsThatMigrateRefusesTheCoreSetsWhileLegacyScriptsArePendingWithoutHidingThem(): void
    {
        $runner = new FakeMigrationRunner();
        $runner->sets = [MigrationSet::core(MigrationSet::CONFIGURATION), MigrationSet::module('centreon-open-tickets')];

        $statuses = (new GetMigrationStatusQueryHandler($runner, new FakeModuleRepository(['centreon-open-tickets']), $this->recorder(['26.12.0'])))(new GetMigrationStatusQuery());

        self::assertEquals([
            new MigrationSetStatus(MigrationSet::core(MigrationSet::CONFIGURATION), [], ['configuration\Version1'], 'The update scripts of 26.12.0 are pending: update the platform first, with the upgrade wizard or the API.'),
            new MigrationSetStatus(MigrationSet::module('centreon-open-tickets'), [], ['module:centreon-open-tickets\Version1']),
        ], $statuses);
    }

    public function testFailsWhenTheRequestedModuleIsNotInstalled(): void
    {
        // A typo or a module to install must not pass for a module with nothing to show.
        $this->expectException(ModuleNotInstalledException::class);
        $this->expectExceptionMessage('The module "centreon-open-ticket" is not installed');

        (new GetMigrationStatusQueryHandler(new FakeMigrationRunner(), new FakeModuleRepository(['centreon-open-tickets']), $this->recorder()))(new GetMigrationStatusQuery(MigrationSet::module('centreon-open-ticket')));
    }

    /**
     * @param list<string> $pendingLegacyScripts
     */
    private function recorder(array $pendingLegacyScripts = []): TargetVersionRecorder
    {
        $scriptFinder = new FakeUpdateScriptFinder();
        $scriptFinder->availableUpdates = $pendingLegacyScripts;

        return new TargetVersionRecorder(new FakeUpdateRepository(), $scriptFinder, '26.12.0');
    }
}
