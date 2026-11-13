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

use App\Upgrade\Domain\Model\MigrationSet;
use App\Upgrade\Domain\Service\MigrationSetLockerInterface;

final class FakeMigrationSetLocker implements MigrationSetLockerInterface
{
    /** @var list<string> names of the sets already being migrated elsewhere */
    public array $lockedSets = [];

    /** @var list<string> */
    public array $acquired = [];

    /** @var list<string> */
    public array $released = [];

    public ?\Throwable $lockFailure = null;

    public ?\Throwable $unlockFailure = null;

    public function lock(MigrationSet $set): bool
    {
        if ($this->lockFailure instanceof \Throwable) {
            throw $this->lockFailure;
        }

        if (in_array($set->name, $this->lockedSets, true)) {
            return false;
        }

        $this->acquired[] = $set->name;

        return true;
    }

    public function unlock(MigrationSet $set): void
    {
        $this->released[] = $set->name;
        if ($this->unlockFailure instanceof \Throwable) {
            throw $this->unlockFailure;
        }
    }
}
