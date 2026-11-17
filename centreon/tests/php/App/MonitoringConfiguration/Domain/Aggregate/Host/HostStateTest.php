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

namespace Tests\App\MonitoringConfiguration\Domain\Aggregate\Host;

use App\MonitoringConfiguration\Domain\Aggregate\Host\HostState;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use PHPUnit\Framework\TestCase;

final class HostStateTest extends TestCase
{
    public function testAnEnabledHostFlagsItsPoller(): void
    {
        self::assertSame(5, new HostState(true, new PollerId(5))->pollerToFlag()?->value);
    }

    public function testADisabledHostOrAHostWithoutPollerFlagsNone(): void
    {
        self::assertNull(new HostState(false, new PollerId(5))->pollerToFlag());
        self::assertNull(new HostState(true, null)->pollerToFlag());
    }
}
