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
use App\MonitoringConfiguration\Domain\Exception\ServiceDuplicationFailedException;
use App\MonitoringConfiguration\Infrastructure\Legacy\LegacyServiceCloner;
use PHPUnit\Framework\TestCase;

final class LegacyServiceClonerTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SESSION['centreon'], $GLOBALS['pearDB'], $GLOBALS['centreon']);
    }

    public function testItDoesNothingForAnEmptyServiceList(): void
    {
        unset($_SESSION['centreon']);
        $pearDbBefore = $GLOBALS['pearDB'] ?? null;
        $centreonBefore = $GLOBALS['centreon'] ?? null;

        // An empty list returns before any session is required or any legacy file is loaded.
        (new LegacyServiceCloner())->cloneServices([], new HostId(9));

        self::assertSame($pearDbBefore, $GLOBALS['pearDB'] ?? null);
        self::assertSame($centreonBefore, $GLOBALS['centreon'] ?? null);
    }

    public function testItFailsWithAnExpectedVerdictWhenNoLegacySessionIsAvailable(): void
    {
        unset($_SESSION['centreon']);

        try {
            (new LegacyServiceCloner())->cloneServices([5], new HostId(9));
            self::fail('expected a ServiceDuplicationFailedException');
        } catch (ServiceDuplicationFailedException $exception) {
            // A missing session is a structural, expected verdict (logged at info, not an error).
            self::assertTrue($exception->expected);
        }
    }

    public function testItFailsWithAnUnexpectedVerdictWhenTheLegacyFunctionsCannotBeLoaded(): void
    {
        // A valid session is present, but the legacy service functions are not loadable in this context
        // (the www file is outside the test autoload and _CENTREON_PATH_ is undefined).
        if (function_exists('multipleServiceInDB') || defined('_CENTREON_PATH_')) {
            self::markTestSkipped('legacy service functions are reachable in this environment');
        }
        $_SESSION['centreon'] = (new \ReflectionClass(\Centreon::class))->newInstanceWithoutConstructor();

        try {
            (new LegacyServiceCloner())->cloneServices([5], new HostId(9));
            self::fail('expected a ServiceDuplicationFailedException');
        } catch (ServiceDuplicationFailedException $exception) {
            // An unavailable legacy function is a genuine failure an operator must act on.
            self::assertFalse($exception->expected);
        }
    }
}
