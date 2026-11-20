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

use App\Upgrade\Application\Query\GetRollbackPlanQuery;
use App\Upgrade\Application\Query\GetRollbackPlanQueryHandler;
use App\Upgrade\Domain\Exception\MigrationSetRefusedException;
use App\Upgrade\Domain\Exception\ModuleNotInstalledException;
use App\Upgrade\Domain\Exception\RollbackTargetNotFoundException;
use App\Upgrade\Domain\Model\MigrationSet;
use App\Upgrade\Domain\Model\WebMigrationSetEnum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\App\Upgrade\Infrastructure\Double\FakeMigrationRunner;
use Tests\App\Upgrade\Infrastructure\Double\FakeModuleRepository;
use Tests\App\Upgrade\Infrastructure\Double\FakeUpgradeLogger;

final class GetRollbackPlanQueryHandlerTest extends TestCase
{
    public function testPlansTheRollbackWithoutRevertingAnything(): void
    {
        $migrationRunner = new FakeMigrationRunner();

        $plan = (new GetRollbackPlanQueryHandler($migrationRunner, new FakeModuleRepository(), new FakeUpgradeLogger(), '26.11.0'))(new GetRollbackPlanQuery(MigrationSet::web(WebMigrationSetEnum::Configuration), 'prev'));

        self::assertSame(['configuration\Version1'], $plan);
        self::assertSame(['plan-rollback:configuration:prev'], $migrationRunner->journal->getArrayCopy());
    }

    public function testPlansTheRollbackOfAnInstalledModule(): void
    {
        $migrationRunner = new FakeMigrationRunner();

        (new GetRollbackPlanQueryHandler($migrationRunner, new FakeModuleRepository(['centreon-open-tickets']), new FakeUpgradeLogger(), '26.11.0'))(new GetRollbackPlanQuery(MigrationSet::module('centreon-open-tickets'), 'first'));

        self::assertSame(['plan-rollback:module:centreon-open-tickets:first'], $migrationRunner->journal->getArrayCopy());
    }

    public function testRefusesToPlanTheRollbackOfAModuleNotInstalled(): void
    {
        $migrationRunner = new FakeMigrationRunner();
        $logger = new FakeUpgradeLogger();

        try {
            (new GetRollbackPlanQueryHandler($migrationRunner, new FakeModuleRepository(), $logger, '26.11.0'))(new GetRollbackPlanQuery(MigrationSet::module('centreon-open-tickets'), 'prev'));
            self::fail('Expected the plan to be refused');
        } catch (ModuleNotInstalledException) {
            self::assertSame([], $migrationRunner->journal->getArrayCopy());
        }

        self::assertSame(['warning'], array_column($logger->calls, 'method'));
    }

    /**
     * @return iterable<string, array{\RuntimeException}>
     */
    public static function provideFailures(): iterable
    {
        yield 'a lost connection' => [new \RuntimeException('Connection lost')];

        yield 'a migration file failing to load' => [new MigrationSetRefusedException('Cannot load the migrations', new \TypeError('Undefined array key'))];
    }

    #[DataProvider('provideFailures')]
    public function testLogsTheTraceOfAFailedPlan(\RuntimeException $failure): void
    {
        $migrationRunner = new FakeMigrationRunner();
        $migrationRunner->planFailure = $failure;

        $logger = new FakeUpgradeLogger();
        $caught = null;

        try {
            (new GetRollbackPlanQueryHandler($migrationRunner, new FakeModuleRepository(), $logger, '26.11.0'))(new GetRollbackPlanQuery(MigrationSet::web(WebMigrationSetEnum::Configuration), 'prev'));
        } catch (\RuntimeException $exception) {
            $caught = $exception;
        }

        self::assertSame($failure, $caught);
        self::assertSame(['error'], array_column($logger->calls, 'method'));
        self::assertSame($failure, $logger->calls[0]['exception'] ?? null);
    }

    public function testLogsARefusedPlanWithoutItsTrace(): void
    {
        $refusal = new RollbackTargetNotFoundException('Refused');
        $migrationRunner = new FakeMigrationRunner();
        $migrationRunner->planFailure = $refusal;

        $logger = new FakeUpgradeLogger();

        try {
            (new GetRollbackPlanQueryHandler($migrationRunner, new FakeModuleRepository(), $logger, '26.11.0'))(new GetRollbackPlanQuery(MigrationSet::web(WebMigrationSetEnum::Configuration), 'prev'));
            self::fail('Expected the plan to be refused');
        } catch (\RuntimeException $exception) {
            self::assertSame($refusal, $exception);
        }

        self::assertSame(
            [['method' => 'warning', 'version' => '26.11.0', 'message' => 'Rollback plan of the "configuration" migration set refused: Refused']],
            $logger->calls,
        );
    }
}
