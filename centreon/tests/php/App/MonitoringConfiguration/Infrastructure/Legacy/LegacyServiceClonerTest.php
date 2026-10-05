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

namespace Tests\App\MonitoringConfiguration\Infrastructure\Legacy;

use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Infrastructure\Legacy\LegacyServiceCloner;
use PHPUnit\Framework\TestCase;

/**
 * Only the empty-list short-circuit is unit-testable: the real clone opens the legacy connections
 * ($pearDB/$pearDBO via CentreonDBInstance) and rebuilds a legacy session, so it is covered end to end
 * on the CDE, and through {@see LegacyHostServiceDuplicatorWrapperTest} for the re-link decision.
 */
final class LegacyServiceClonerTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SESSION['centreon'], $GLOBALS['pearDB'], $GLOBALS['pearDBO'], $GLOBALS['centreon']);
    }

    public function testItDoesNothingForAnEmptyServiceList(): void
    {
        $pearDbBefore = $GLOBALS['pearDB'] ?? null;
        $centreonBefore = $GLOBALS['centreon'] ?? null;

        // An empty list returns before any connection is opened, session rebuilt or legacy file loaded.
        (new LegacyServiceCloner())->cloneServices([], new HostId(9), 42);

        self::assertSame($pearDbBefore, $GLOBALS['pearDB'] ?? null);
        self::assertSame($centreonBefore, $GLOBALS['centreon'] ?? null);
    }
}
