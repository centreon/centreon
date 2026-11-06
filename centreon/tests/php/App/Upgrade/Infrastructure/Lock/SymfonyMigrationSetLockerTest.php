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

namespace Tests\App\Upgrade\Infrastructure\Lock;

use App\Upgrade\Domain\MigrationSet;
use App\Upgrade\Infrastructure\Lock\SymfonyMigrationSetLocker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

final class SymfonyMigrationSetLockerTest extends TestCase
{
    public function testASetCannotBeLockedTwiceButOtherSetsCan(): void
    {
        $factory = new LockFactory(new InMemoryStore());
        // Two processes, each with its own locker.
        $first = new SymfonyMigrationSetLocker($factory);
        $second = new SymfonyMigrationSetLocker($factory);

        self::assertTrue($first->lock(MigrationSet::module('centreon-open-tickets')));
        self::assertFalse($second->lock(MigrationSet::module('centreon-open-tickets')));
        self::assertTrue($second->lock(MigrationSet::module('centreon-dsm')));

        $first->unlock(MigrationSet::module('centreon-open-tickets'));
        self::assertTrue($second->lock(MigrationSet::module('centreon-open-tickets')));
    }

    public function testUnlockingASetThatWasNotLockedIsHarmless(): void
    {
        (new SymfonyMigrationSetLocker(new LockFactory(new InMemoryStore())))->unlock(MigrationSet::module('centreon-open-tickets'));

        $this->addToAssertionCount(1);
    }
}
