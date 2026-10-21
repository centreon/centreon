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

namespace Tests\App\Upgrade\Application\Query;

use App\Upgrade\Application\MigrationRunner;
use App\Upgrade\Application\MigrationSetStatus;
use App\Upgrade\Application\Query\GetMigrationStatusQuery;
use App\Upgrade\Application\Query\GetMigrationStatusQueryHandler;
use PHPUnit\Framework\TestCase;

final class GetMigrationStatusQueryHandlerTest extends TestCase
{
    public function testReturnsTheStatusOfTheRequestedSets(): void
    {
        $status = new MigrationSetStatus('configuration', ['Centreon\Migrations\Configuration\Version1'], []);
        $runner = $this->createMock(MigrationRunner::class);
        $runner->expects(self::once())->method('status')->with('configuration')->willReturn([$status]);

        self::assertSame([$status], (new GetMigrationStatusQueryHandler($runner))(new GetMigrationStatusQuery('configuration')));
    }
}
