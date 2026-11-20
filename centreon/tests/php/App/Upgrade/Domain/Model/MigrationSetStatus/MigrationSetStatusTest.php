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

namespace Tests\App\Upgrade\Domain\Model\MigrationSetStatus;

use App\Upgrade\Domain\Model\MigrationSet;
use App\Upgrade\Domain\Model\MigrationSetStatus\MigrationSetStatus;
use App\Upgrade\Domain\Model\WebMigrationSetEnum;
use PHPUnit\Framework\TestCase;

final class MigrationSetStatusTest extends TestCase
{
    public function testARefusedSetKeepsTheMigrationsItRead(): void
    {
        $status = MigrationSetStatus::read(MigrationSet::web(WebMigrationSetEnum::Configuration), ['Version1'], ['Version2'], 'The update scripts of 26.12.0 are pending');

        self::assertNull($status->readError);
        self::assertSame(['Version1'], $status->executed);
        self::assertSame(['Version2'], $status->pending);
        self::assertSame('The update scripts of 26.12.0 are pending', $status->refusal);
    }

    public function testAnUnreadableSetListsNoMigration(): void
    {
        $status = MigrationSetStatus::unreadable(MigrationSet::web(WebMigrationSetEnum::Configuration), 'Version1 is not available anymore');

        self::assertSame('Version1 is not available anymore', $status->readError);
        self::assertSame([], $status->executed);
        self::assertSame([], $status->pending);
        self::assertNull($status->refusal);
    }
}
