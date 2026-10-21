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

namespace Tests\App\Upgrade\Application;

use App\Upgrade\Application\TargetVersionRecorder;
use PHPUnit\Framework\TestCase;
use Tests\App\Upgrade\Infrastructure\Double\FakeUpdateRepository;
use Tests\App\Upgrade\Infrastructure\Double\FakeUpdateScriptFinder;

final class TargetVersionRecorderTest extends TestCase
{
    private FakeUpdateRepository $updateRepository;

    private FakeUpdateScriptFinder $updateScriptFinder;

    private TargetVersionRecorder $recorder;

    protected function setUp(): void
    {
        $this->updateRepository = new FakeUpdateRepository();
        $this->updateScriptFinder = new FakeUpdateScriptFinder();
        $this->recorder = new TargetVersionRecorder($this->updateRepository, $this->updateScriptFinder, '26.11.0');
    }

    public function testResolvesThePlatformVersionUnlessALegacyScriptIsAhead(): void
    {
        self::assertSame('26.11.0', $this->recorder->resolve('26.09.1'));
        self::assertSame('26.11.1', $this->recorder->resolve('26.11.1'));
    }

    public function testRecordsThePlatformVersion(): void
    {
        $this->recorder->record('26.09.1');

        self::assertSame(['26.11.0'], $this->updateRepository->updatesRun);
    }

    public function testNeverMovesTheVersionBackwards(): void
    {
        $this->recorder->record('26.11.1');

        self::assertSame([], $this->updateRepository->updatesRun);
    }

    public function testRecordsNothingOnceAnUpdateRemovedTheInstallDirectory(): void
    {
        // It recorded the version, and Update-next.php is gone with the directory: a later migrate must not skip it.
        $this->updateRepository->installDirectoryPresent = false;

        self::assertSame('26.09.1', $this->recorder->resolve('26.09.1'), 'The update announces what it records');
        $this->recorder->record('26.09.1');

        self::assertSame([], $this->updateRepository->updatesRun);
    }

    public function testLeavesTheVersionToTheLegacyScriptsOfTheComingRelease(): void
    {
        // A development build: recording 26.11.0 would skip Update-26.11.0.php once Update-next.php is renamed to it.
        $this->updateScriptFinder->nextUpdatePending = true;

        $this->recorder->record('26.09.1');

        self::assertSame([], $this->updateRepository->updatesRun);
    }
}
