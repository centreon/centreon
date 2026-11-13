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

namespace Tests\App\Upgrade\Application\Service;

use App\Upgrade\Application\Service\MigrationApplier;
use App\Upgrade\Application\Service\TargetVersionRecorder;
use App\Upgrade\Domain\Exception\LegacyUpdatesPendingException;
use App\Upgrade\Domain\Exception\MigrationFailedException;
use App\Upgrade\Domain\Exception\MigrationInterruptedException;
use App\Upgrade\Domain\Exception\PlatformVersionNotRecordedException;
use App\Upgrade\Domain\Model\MigrationOutcome;
use App\Upgrade\Domain\Model\MigrationSet;
use App\Upgrade\Domain\Model\WebMigrationSetEnum;
use PHPUnit\Framework\TestCase;
use Tests\App\Upgrade\Infrastructure\Double\FakeMigrationRunner;
use Tests\App\Upgrade\Infrastructure\Double\FakeUpdateRepository;
use Tests\App\Upgrade\Infrastructure\Double\FakeUpdateScriptFinder;
use Tests\App\Upgrade\Infrastructure\Double\FakeUpgradeLogger;

final class MigrationApplierTest extends TestCase
{
    private FakeMigrationRunner $migrationRunner;

    private FakeUpdateRepository $updateRepository;

    private FakeUpdateScriptFinder $scriptFinder;

    private FakeUpgradeLogger $logger;

    private MigrationApplier $applier;

    protected function setUp(): void
    {
        $this->migrationRunner = new FakeMigrationRunner();
        $this->updateRepository = new FakeUpdateRepository();
        $this->scriptFinder = new FakeUpdateScriptFinder();
        $this->logger = new FakeUpgradeLogger();
        $this->applier = new MigrationApplier(
            $this->migrationRunner,
            new TargetVersionRecorder($this->updateRepository, $this->scriptFinder, '26.11.0', new FakeUpgradeLogger()),
            $this->logger,
            '26.11.0',
        );
    }

    public function testRunsTheWebSetsAsStepsThenRecordsTheVersionTheyReach(): void
    {
        // MigrationApplier takes no lock: its callers hold it.
        self::assertEquals(new MigrationOutcome(['realtime\Version1', 'configuration\Version1']), $this->applier->applyWebSets());
        self::assertSame(['26.11.0'], $this->updateRepository->updatesRun);
        self::assertSame(['migrations:realtime', 'migrations:configuration'], array_values(array_unique(array_column($this->logger->calls, 'stepName'))));
    }

    public function testReturnsTheWarningOfAPlatformVersionKeptBehind(): void
    {
        $this->updateRepository->installDirectoryPresent = false;

        self::assertSame(
            ['Kept the platform version 24.10.0: without the install directory, a pending Update-next.php cannot be detected.'],
            $this->applier->applyWebSets()->warnings,
        );
    }

    public function testReturnsTheWarningsOfTheMigrationsInTheirOrder(): void
    {
        $this->migrationRunner->warnings = ['realtime' => ['realtime w'], 'configuration' => ['configuration w'], 'module:centreon-open-tickets' => ['module w']];
        $this->updateRepository->installDirectoryPresent = false;

        self::assertSame(
            ['realtime w', 'configuration w', 'Kept the platform version 24.10.0: without the install directory, a pending Update-next.php cannot be detected.'],
            $this->applier->applyWebSets()->warnings,
        );
        self::assertSame(['module w'], $this->applier->applySet(MigrationSet::module('centreon-open-tickets'))->warnings);
    }

    public function testTellsTheWarningsOfTheSetsMigratedBeforeAFailure(): void
    {
        $this->migrationRunner->warnings = ['realtime' => ['realtime w']];
        $this->migrationRunner->failingSets = ['configuration'];

        $this->expectException(MigrationInterruptedException::class);
        $this->expectExceptionMessage('A new run resumes with the "configuration" migration set. Warnings: realtime w.');

        $this->applier->applyWebSets();
    }

    public function testAFailureOfTheFirstWebSetPropagatesAsIsWithItsTraceLogged(): void
    {
        $this->migrationRunner->failingSets = ['realtime'];
        $caught = null;

        try {
            $this->applier->applyWebSets();
        } catch (\RuntimeException $exception) {
            $caught = $exception;
        }

        self::assertInstanceOf(MigrationFailedException::class, $caught);
        self::assertTrue($this->applier->hasLogged($caught));
    }

    public function testAnInterruptionKeepsTheLoggedTraceOfItsCause(): void
    {
        $this->migrationRunner->failingSets = ['configuration'];
        $caught = null;

        try {
            $this->applier->applyWebSets();
        } catch (MigrationInterruptedException $exception) {
            $caught = $exception;
        }

        self::assertInstanceOf(MigrationInterruptedException::class, $caught);
        self::assertTrue($this->applier->hasLogged($caught));
    }

    public function testAFailedVersionRecordingIsLeftToItsCallerToLog(): void
    {
        $this->updateRepository->versionUpdateFailure = new \RuntimeException('informations is locked');
        $caught = null;

        try {
            $this->applier->applyWebSets();
        } catch (PlatformVersionNotRecordedException $exception) {
            $caught = $exception;
        }

        self::assertInstanceOf(PlatformVersionNotRecordedException::class, $caught);
        self::assertFalse($this->applier->hasLogged($caught));
    }

    public function testTellsTheWarningsOfTheMigrationsWhenThePlatformVersionCannotBeRecorded(): void
    {
        $this->migrationRunner->warnings = ['realtime' => ['realtime w']];
        $this->updateRepository->versionUpdateFailure = new \RuntimeException('informations is locked');

        $this->expectException(PlatformVersionNotRecordedException::class);
        $this->expectExceptionMessage('Every Centreon Web migration ran, but recording the platform version failed: informations is locked. Fix the cause, then run centreon:migrations:migrate again: it only records the platform version. Warnings: realtime w.');

        $this->applier->applyWebSets();
    }

    public function testASingleWebSetWarnsThatThePlatformVersionWaitsForBothSets(): void
    {
        $this->migrationRunner->warnings = ['configuration' => ['configuration w']];
        $kept = 'Kept the platform version 24.10.0: the new version is only recorded by centreon:migrations:migrate without --set, once both Centreon Web sets ran.';

        self::assertSame(['configuration w', $kept], $this->applier->applySet(MigrationSet::web(WebMigrationSetEnum::Configuration))->warnings);
        self::assertSame($kept, array_values(array_filter($this->logger->calls, static fn (array $call): bool => $call['method'] === 'warning'))[0]['message'] ?? null, 'Logged too, for a scripted run');
        self::assertSame([], $this->updateRepository->updatesRun);

        $this->migrationRunner->warnings = [];
        $this->updateRepository->currentVersion = '26.11.0';
        self::assertSame([], $this->applier->applySet(MigrationSet::web(WebMigrationSetEnum::Configuration))->warnings, 'Nothing to record, nothing to warn of');
    }

    public function testAWebSetWaitsForTheLegacyScriptsButAModuleSetDoesNot(): void
    {
        $this->scriptFinder->availableUpdates = ['26.10.0'];

        self::assertEquals(new MigrationOutcome(['module:centreon-open-tickets\Version1']), $this->applier->applySet(MigrationSet::module('centreon-open-tickets')));

        $this->expectException(LegacyUpdatesPendingException::class);
        $this->applier->applySet(MigrationSet::web(WebMigrationSetEnum::Configuration));
    }

    public function testRevertsASetAsARollbackStep(): void
    {
        $this->migrationRunner->warnings['configuration'] = ['2 rows stay without host'];

        $reverted = $this->applier->revertSet(MigrationSet::web(WebMigrationSetEnum::Configuration), 'prev', ['configuration\Version1']);

        self::assertEquals(new MigrationOutcome(['configuration\Version1'], ['2 rows stay without host']), $reverted);
        self::assertSame(['rollback:configuration:prev'], $this->migrationRunner->journal->getArrayCopy());
        self::assertSame(['step', 'stepCompleted'], array_column($this->logger->calls, 'method'));
        self::assertStringEndsWith('1 migration(s) reverted', (string) ($this->logger->calls[1]['message'] ?? ''));
    }
}
