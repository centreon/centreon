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

namespace Tests\App\Upgrade\Domain\Exception;

use App\Upgrade\Domain\Exception\RollbackPlanChangedException;
use App\Upgrade\Domain\Model\MigrationSet;
use App\Upgrade\Domain\Model\WebMigrationSetEnum;
use PHPUnit\Framework\TestCase;

final class RollbackPlanChangedExceptionTest extends TestCase
{
    public function testTellsWhatTheRollbackWouldNowRevert(): void
    {
        $exception = new RollbackPlanChangedException(MigrationSet::web(WebMigrationSetEnum::Configuration), ['Version2', 'Version1']);

        self::assertSame(
            'The migrations of the "configuration" migration set changed since they were confirmed: it would now revert Version2, Version1. Nothing was reverted.',
            $exception->getMessage(),
        );
    }

    public function testTellsWhenTheRollbackWouldNowRevertNothing(): void
    {
        $exception = new RollbackPlanChangedException(MigrationSet::web(WebMigrationSetEnum::Configuration), []);

        self::assertSame(
            'The migrations of the "configuration" migration set changed since they were confirmed: it would now revert none. Nothing was reverted.',
            $exception->getMessage(),
        );
    }
}
