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

    public function testRecordsThePlatformVersion(): void
    {
        $this->updateRepository->currentVersion = '26.09.1';

        $this->recorder->record();

        self::assertSame(['26.11.0'], $this->updateRepository->updatesRun);
    }

    public function testNeverMovesTheVersionBackwards(): void
    {
        $this->updateRepository->currentVersion = '26.11.1';

        $this->recorder->record();

        self::assertSame([], $this->updateRepository->updatesRun);
    }

    public function testLeavesTheVersionToTheLegacyScriptsOfTheComingRelease(): void
    {
        // A development build: recording 26.11.0 would skip Update-26.11.0.php once Update-next.php is renamed to it.
        $this->updateRepository->currentVersion = '26.09.1';
        $this->updateScriptFinder->nextUpdatePending = true;

        $this->recorder->record();

        self::assertSame([], $this->updateRepository->updatesRun);
    }
}
