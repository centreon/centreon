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

use App\Upgrade\Domain\Exception\ClosingStepsFailedException;
use PHPUnit\Framework\TestCase;

final class ClosingStepsFailedExceptionTest extends TestCase
{
    public function testNamesEachFailedStepAndChainsTheFirstFailure(): void
    {
        $moduleFailure = new \RuntimeException('Some updates failed.');

        $exception = new ClosingStepsFailedException([
            'modules_update' => $moduleFailure,
            'cache_clear' => new \RuntimeException('cache:clear failed'),
        ]);

        self::assertSame('The closing steps of the update failed. modules_update: Some updates failed; cache_clear: cache:clear failed.', $exception->getMessage());
        self::assertSame($moduleFailure, $exception->getPrevious());
    }
}
