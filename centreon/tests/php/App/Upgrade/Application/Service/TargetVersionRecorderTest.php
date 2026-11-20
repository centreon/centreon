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

use App\Upgrade\Application\Service\TargetVersionRecorder;
use App\Upgrade\Domain\Exception\LegacyUpdatesPendingException;
use App\Upgrade\Domain\Exception\PlatformVersionUnknownException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\App\Upgrade\Infrastructure\Double\FakeUpdateRepository;
use Tests\App\Upgrade\Infrastructure\Double\FakeUpdateScriptFinder;
use Tests\App\Upgrade\Infrastructure\Double\FakeUpgradeLogger;

final class TargetVersionRecorderTest extends TestCase
{
    private FakeUpdateRepository $updateRepository;

    private FakeUpdateScriptFinder $updateScriptFinder;

    private FakeUpgradeLogger $logger;

    private TargetVersionRecorder $recorder;

    protected function setUp(): void
    {
        $this->updateRepository = new FakeUpdateRepository();
        $this->updateScriptFinder = new FakeUpdateScriptFinder();
        $this->logger = new FakeUpgradeLogger();
        $this->recorder = new TargetVersionRecorder($this->updateRepository, $this->updateScriptFinder, '26.11.0', $this->logger);
    }

    /**
     * @return iterable<string, array{?string}>
     */
    public static function provideUnknownLegacyVersions(): iterable
    {
        yield 'no version row' => [null];

        yield 'a blank version' => ['  '];
    }

    #[DataProvider('provideUnknownLegacyVersions')]
    public function testTheLegacyVersionIsUnknownWithoutAVersion(?string $currentVersion): void
    {
        $this->updateRepository->currentVersion = $currentVersion;
        $this->updateScriptFinder->availableUpdates = ['26.10.0'];

        $this->expectException(PlatformVersionUnknownException::class);

        $this->recorder->legacyVersion();
    }

    public function testRefusesWhileLegacyUpdateScriptsArePending(): void
    {
        $this->updateScriptFinder->availableUpdates = ['26.04.0', '26.10.0'];

        try {
            $this->recorder->legacyVersion();
            self::fail('The legacy update scripts must run first');
        } catch (LegacyUpdatesPendingException $exception) {
            self::assertSame(['26.04.0', '26.10.0'], $exception->versions);
            self::assertSame('The update scripts of 26.04.0, 26.10.0 are pending: update the platform first, with the upgrade wizard or the API.', $exception->getMessage());
        }
    }

    public function testReturnsTheLegacyVersionOnceItsScriptsRan(): void
    {
        self::assertSame('24.10.0', $this->recorder->legacyVersion());
    }

    public function testResolvesThePlatformVersionUnlessALegacyScriptIsAhead(): void
    {
        self::assertSame('26.11.0', $this->recorder->resolve('26.09.1'));
        self::assertSame('26.11.1', $this->recorder->resolve('26.11.1'));
    }

    public function testRecordsThePlatformVersion(): void
    {
        self::assertNull($this->recorder->record('26.09.1'));

        self::assertSame(['26.11.0'], $this->updateRepository->updatesRun);
        self::assertSame(
            [['method' => 'info', 'version' => '26.11.0', 'message' => 'Recorded the platform version 26.11.0, previously 26.09.1']],
            $this->logger->calls,
        );
    }

    public function testNeverMovesTheVersionBackwards(): void
    {
        self::assertNull($this->recorder->record('26.11.1'));

        self::assertSame([], $this->updateRepository->updatesRun);
        self::assertSame([], $this->logger->calls);
    }

    public function testRecordsNothingOnceAnUpdateRemovedTheInstallDirectory(): void
    {
        // The update recorded the version and removed www/install, Update-next.php included.
        $this->updateRepository->installDirectoryPresent = false;

        self::assertSame('26.09.1', $this->recorder->resolve('26.09.1'), 'The update announces what it records');
        $warning = 'Kept the platform version 26.09.1: without the install directory, a pending Update-next.php cannot be detected.';
        self::assertSame($warning, $this->recorder->record('26.09.1'), 'The command shows the warning too');

        self::assertSame([], $this->updateRepository->updatesRun);
        self::assertSame(
            [['method' => 'warning', 'version' => '26.11.0', 'message' => 'Kept the platform version 26.09.1: without the install directory, a pending Update-next.php cannot be detected.']],
            $this->logger->calls,
        );
    }

    public function testWarnsOfNothingWithoutTheInstallDirectoryOnceTheVersionIsRecorded(): void
    {
        $this->updateRepository->installDirectoryPresent = false;

        self::assertNull($this->recorder->record('26.11.0'));

        self::assertSame([], $this->logger->calls);
    }

    public function testLeavesTheVersionToTheLegacyScriptsOfTheComingRelease(): void
    {
        // A development build: recording 26.11.0 would skip Update-26.11.0.php once Update-next.php is renamed to it.
        $this->updateScriptFinder->nextUpdatePending = true;

        self::assertNull($this->recorder->record('26.09.1'));

        self::assertSame([], $this->updateRepository->updatesRun);
    }
}
