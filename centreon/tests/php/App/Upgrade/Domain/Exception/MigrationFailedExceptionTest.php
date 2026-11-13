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

use App\Upgrade\Domain\Exception\MigrationFailedException;
use App\Upgrade\Domain\Model\MigrationSet;
use App\Upgrade\Domain\Model\WebMigrationSetEnum;
use PHPUnit\Framework\TestCase;

final class MigrationFailedExceptionTest extends TestCase
{
    public function testTellsTheFailureAndWhatCompletedBeforeIt(): void
    {
        $cause = new \RuntimeException('Duplicate key.');
        $exception = MigrationFailedException::failed(MigrationSet::web(WebMigrationSetEnum::Configuration), 'Version2', $cause, ['Version1'], ['2 rows stay without host']);

        self::assertSame(
            'The "Version2" migration of the "configuration" migration set failed: Duplicate key. Completed before it in this set: Version1. Warnings: 2 rows stay without host.',
            $exception->getMessage(),
        );
        self::assertSame($cause, $exception->getPrevious());
        self::assertSame('Version2', $exception->version);
    }

    public function testTellsASkippedMigrationWithItsReason(): void
    {
        $exception = MigrationFailedException::skipped(MigrationSet::module('centreon-open-tickets'), 'Version1', 'Already done');

        self::assertSame(
            'The "centreon-open-tickets" module migration set skipped Version1 ("Already done"): a migration must run or fail, never skip.',
            $exception->getMessage(),
        );
    }

    public function testTellsASkippedMigrationWithoutReason(): void
    {
        $exception = MigrationFailedException::skipped(MigrationSet::web(WebMigrationSetEnum::Realtime), 'Version1', null);

        self::assertSame('The "realtime" migration set skipped Version1: a migration must run or fail, never skip.', $exception->getMessage());
        self::assertNull($exception->getPrevious());
    }
}
