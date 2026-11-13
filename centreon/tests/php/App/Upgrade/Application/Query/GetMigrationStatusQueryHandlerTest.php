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

use App\Upgrade\Application\Query\GetMigrationStatusQuery;
use App\Upgrade\Application\Query\GetMigrationStatusQueryHandler;
use App\Upgrade\Application\Service\TargetVersionRecorder;
use App\Upgrade\Domain\Exception\ModuleNotInstalledException;
use App\Upgrade\Domain\Exception\PlatformVersionUnknownException;
use App\Upgrade\Domain\Model\MigrationSet;
use App\Upgrade\Domain\Model\MigrationSetStatus\MigrationSetStatus;
use App\Upgrade\Domain\Model\WebMigrationSetEnum;
use PHPUnit\Framework\TestCase;
use Tests\App\Upgrade\Infrastructure\Double\FakeMigrationRunner;
use Tests\App\Upgrade\Infrastructure\Double\FakeModuleRepository;
use Tests\App\Upgrade\Infrastructure\Double\FakeUpdateRepository;
use Tests\App\Upgrade\Infrastructure\Double\FakeUpdateScriptFinder;
use Tests\App\Upgrade\Infrastructure\Double\FakeUpgradeLogger;

final class GetMigrationStatusQueryHandlerTest extends TestCase
{
    public function testReturnsTheStatusOfTheRequestedSet(): void
    {
        $runner = new FakeMigrationRunner();
        $runner->sets = [MigrationSet::web(WebMigrationSetEnum::Realtime)];

        $configuration = MigrationSet::web(WebMigrationSetEnum::Configuration);

        $statuses = (new GetMigrationStatusQueryHandler($runner, new FakeModuleRepository(), $this->recorder(), new FakeUpgradeLogger(), '26.12.0'))(new GetMigrationStatusQuery($configuration));

        self::assertEquals([MigrationSetStatus::read($configuration, [], ['configuration\Version1'])], $statuses);
    }

    public function testReportsTheRequestedSetAsUnreadableInsteadOfFailing(): void
    {
        $runner = new FakeMigrationRunner();
        $runner->failingSets = ['configuration'];

        $statuses = (new GetMigrationStatusQueryHandler($runner, new FakeModuleRepository(), $this->recorder(), new FakeUpgradeLogger(), '26.12.0'))(new GetMigrationStatusQuery(MigrationSet::web(WebMigrationSetEnum::Configuration)));

        self::assertEquals([MigrationSetStatus::unreadable(MigrationSet::web(WebMigrationSetEnum::Configuration), 'Status of "configuration" failed')], $statuses);
    }

    public function testComputesTheStatusOfTheInstalledModulesOnly(): void
    {
        $runner = new FakeMigrationRunner();
        $runner->sets = [MigrationSet::web(WebMigrationSetEnum::Configuration), MigrationSet::module('centreon-bam-server'), MigrationSet::module('centreon-open-tickets')];

        $statuses = (new GetMigrationStatusQueryHandler($runner, new FakeModuleRepository(['centreon-open-tickets']), $this->recorder(), new FakeUpgradeLogger(), '26.12.0'))(new GetMigrationStatusQuery());

        self::assertEquals([MigrationSet::web(WebMigrationSetEnum::Configuration), MigrationSet::module('centreon-open-tickets')], $runner->statusRequests);
        self::assertEquals($runner->statusRequests, array_map(static fn (MigrationSetStatus $status): MigrationSet => $status->set, $statuses));
    }

    public function testASetFailingItsChecksDoesNotHideTheOthers(): void
    {
        $runner = new FakeMigrationRunner();
        $runner->sets = [MigrationSet::web(WebMigrationSetEnum::Realtime), MigrationSet::web(WebMigrationSetEnum::Configuration)];
        $runner->failingSets = [WebMigrationSetEnum::Realtime->value];

        $logger = new FakeUpgradeLogger();

        $statuses = (new GetMigrationStatusQueryHandler($runner, new FakeModuleRepository(), $this->recorder(), $logger, '26.12.0'))(new GetMigrationStatusQuery());

        self::assertEquals([
            MigrationSetStatus::unreadable(MigrationSet::web(WebMigrationSetEnum::Realtime), 'Status of "realtime" failed'),
            MigrationSetStatus::read(MigrationSet::web(WebMigrationSetEnum::Configuration), [], ['configuration\Version1']),
        ], $statuses);
        self::assertSame(['error'], array_column($logger->calls, 'method'));
        self::assertInstanceOf(\ParseError::class, $logger->calls[0]['exception'] ?? null);
    }

    public function testReportsThatMigrateRefusesTheWebSetsWhileLegacyScriptsArePendingWithoutHidingThem(): void
    {
        $runner = new FakeMigrationRunner();
        $runner->sets = [MigrationSet::web(WebMigrationSetEnum::Configuration), MigrationSet::module('centreon-open-tickets')];

        $statuses = (new GetMigrationStatusQueryHandler($runner, new FakeModuleRepository(['centreon-open-tickets']), $this->recorder(['26.12.0']), new FakeUpgradeLogger(), '26.12.0'))(new GetMigrationStatusQuery());

        self::assertEquals([
            MigrationSetStatus::read(MigrationSet::web(WebMigrationSetEnum::Configuration), [], ['configuration\Version1'], 'The update scripts of 26.12.0 are pending: update the platform first, with the upgrade wizard or the API.'),
            MigrationSetStatus::read(MigrationSet::module('centreon-open-tickets'), [], ['module:centreon-open-tickets\Version1']),
        ], $statuses);
    }

    public function testADatabaseFailureOfTheChecksDoesNotHideTheOtherSets(): void
    {
        $runner = new FakeMigrationRunner();
        $runner->sets = [MigrationSet::web(WebMigrationSetEnum::Realtime), MigrationSet::module('centreon-open-tickets')];

        $modules = new FakeModuleRepository();
        $modules->failure = new \Exception('modules_informations is locked');

        $updateRepository = new FakeUpdateRepository();
        $updateRepository->versionReadFailure = new \Exception('informations is locked');

        $statuses = (new GetMigrationStatusQueryHandler($runner, $modules, new TargetVersionRecorder($updateRepository, new FakeUpdateScriptFinder(), '26.12.0', new FakeUpgradeLogger()), new FakeUpgradeLogger(), '26.12.0'))(new GetMigrationStatusQuery());

        self::assertEquals([
            MigrationSetStatus::unreadable(MigrationSet::web(WebMigrationSetEnum::Realtime), 'informations is locked'),
            MigrationSetStatus::unreadable(MigrationSet::module('centreon-open-tickets'), 'modules_informations is locked'),
        ], $statuses);
    }

    public function testReportsAMissingPlatformVersionOnceWithoutItsTrace(): void
    {
        $updateRepository = new FakeUpdateRepository();
        $updateRepository->currentVersion = null;

        $runner = new FakeMigrationRunner();
        $runner->sets = MigrationSet::webSets();

        $logger = new FakeUpgradeLogger();

        $statuses = (new GetMigrationStatusQueryHandler($runner, new FakeModuleRepository(), new TargetVersionRecorder($updateRepository, new FakeUpdateScriptFinder(), '26.12.0', new FakeUpgradeLogger()), $logger, '26.12.0'))(new GetMigrationStatusQuery());

        $message = (new PlatformVersionUnknownException())->getMessage();
        self::assertEquals(array_map(static fn (MigrationSet $set): MigrationSetStatus => MigrationSetStatus::unreadable($set, $message), MigrationSet::webSets()), $statuses);
        self::assertSame([['method' => 'warning', 'version' => '26.12.0', 'message' => $message]], $logger->calls);
    }

    public function testReportsTheRequestedModuleAsUnreadableWhenItsInstallationCannotBeRead(): void
    {
        $modules = new FakeModuleRepository();
        $modules->failure = new \Exception('modules_informations is locked');

        $logger = new FakeUpgradeLogger();

        $statuses = (new GetMigrationStatusQueryHandler(new FakeMigrationRunner(), $modules, $this->recorder(), $logger, '26.12.0'))(new GetMigrationStatusQuery(MigrationSet::module('centreon-open-tickets')));

        self::assertEquals([MigrationSetStatus::unreadable(MigrationSet::module('centreon-open-tickets'), 'modules_informations is locked')], $statuses);
        self::assertSame(['error'], array_column($logger->calls, 'method'));
    }

    public function testFailsWhenTheRequestedModuleIsNotInstalled(): void
    {
        // A typo or a module to install must not pass for a module with nothing to show.
        $logger = new FakeUpgradeLogger();

        try {
            (new GetMigrationStatusQueryHandler(new FakeMigrationRunner(), new FakeModuleRepository(['centreon-open-tickets']), $this->recorder(), $logger, '26.12.0'))(new GetMigrationStatusQuery(MigrationSet::module('centreon-open-ticket')));
            self::fail('Expected the module to be refused');
        } catch (ModuleNotInstalledException $exception) {
            self::assertStringContainsString('The module "centreon-open-ticket" is not installed', $exception->getMessage());
        }

        self::assertSame(['warning'], array_column($logger->calls, 'method'));
    }

    public function testReportsThatMigrateRefusesTheRequestedWebSetWhileLegacyScriptsArePending(): void
    {
        $configuration = MigrationSet::web(WebMigrationSetEnum::Configuration);

        $statuses = (new GetMigrationStatusQueryHandler(new FakeMigrationRunner(), new FakeModuleRepository(), $this->recorder(['26.12.0']), new FakeUpgradeLogger(), '26.12.0'))(new GetMigrationStatusQuery($configuration));

        self::assertEquals([MigrationSetStatus::read($configuration, [], ['configuration\Version1'], 'The update scripts of 26.12.0 are pending: update the platform first, with the upgrade wizard or the API.')], $statuses);
    }

    public function testTheRequestedModuleDoesNotWaitForTheLegacyScripts(): void
    {
        $module = MigrationSet::module('centreon-open-tickets');

        $statuses = (new GetMigrationStatusQueryHandler(new FakeMigrationRunner(), new FakeModuleRepository(['centreon-open-tickets']), $this->recorder(['26.12.0']), new FakeUpgradeLogger(), '26.12.0'))(new GetMigrationStatusQuery($module));

        self::assertEquals([MigrationSetStatus::read($module, [], ['module:centreon-open-tickets\Version1'])], $statuses);
    }

    /**
     * @param list<string> $pendingLegacyScripts
     */
    private function recorder(array $pendingLegacyScripts = []): TargetVersionRecorder
    {
        $scriptFinder = new FakeUpdateScriptFinder();
        $scriptFinder->availableUpdates = $pendingLegacyScripts;

        return new TargetVersionRecorder(new FakeUpdateRepository(), $scriptFinder, '26.12.0', new FakeUpgradeLogger());
    }
}
