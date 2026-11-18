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

namespace Tests\App\Upgrade\Infrastructure\Double;

use App\Upgrade\Domain\Repository\UpdateRepository;

class FakeUpdateRepository implements UpdateRepository
{
    public ?string $currentVersion = '24.10.0';

    public bool $postUpdateCalled = false;

    public bool $installDirectoryPresent = true;

    /** @var string[] versions whose version row was recorded */
    public array $updatesRun = [];

    /** @var list<string> ordered post-update calls (backup / remove) */
    public array $calls = [];

    public function findCurrentVersion(): ?string
    {
        return $this->currentVersion;
    }

    public function runMonitoringSql(string $version): void
    {
    }

    public function runScript(string $version): void
    {
    }

    public function runConfigurationSql(string $version): void
    {
    }

    public function runPostScript(string $version): void
    {
    }

    public function updateVersionInformation(string $version): void
    {
        $this->updatesRun[] = $version;
    }

    public function installDirectoryExists(): bool
    {
        return $this->installDirectoryPresent;
    }

    public function backupInstallDirectory(string $currentVersion): void
    {
        $this->calls[] = 'backup';
    }

    public function removeInstallDirectory(): void
    {
        $this->calls[] = 'remove';
        $this->postUpdateCalled = true;
    }
}
