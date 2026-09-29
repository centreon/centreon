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

namespace Tests\App\MonitoringConfiguration\Application\Command;

use App\MonitoringConfiguration\Application\Command\DuplicateHostServicesCommand;
use App\MonitoringConfiguration\Application\Command\DuplicateHostServicesCommandHandler;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use PHPUnit\Framework\TestCase;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostServiceDuplicator;

final class DuplicateHostServicesCommandHandlerTest extends TestCase
{
    public function testItDuplicatesTheServicesOfTheRequestedHost(): void
    {
        $duplicator = new FakeHostServiceDuplicator();

        new DuplicateHostServicesCommandHandler($duplicator)(
            new DuplicateHostServicesCommand(new HostId(5), new HostId(9)),
        );

        self::assertSame([['sourceHostId' => 5, 'newHostId' => 9]], $duplicator->duplicateCalls);
    }

    public function testItLetsADuplicationFailurePropagate(): void
    {
        $duplicator = new FakeHostServiceDuplicator();
        $duplicator->duplicateThrows = true;

        $this->expectException(\Throwable::class);

        new DuplicateHostServicesCommandHandler($duplicator)(
            new DuplicateHostServicesCommand(new HostId(5), new HostId(9)),
        );
    }
}
