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
use App\Security\Domain\Aggregate\UserId;
use PHPUnit\Framework\TestCase;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostServiceDuplicator;
use Tests\App\Security\Infrastructure\Double\FakeResourceAccessRepository;

final class DuplicateHostServicesCommandHandlerTest extends TestCase
{
    public function testItDuplicatesTheServicesThenScopesThemInTheAcl(): void
    {
        $duplicator = new FakeHostServiceDuplicator();
        $resourceAccessRepository = new FakeResourceAccessRepository();

        new DuplicateHostServicesCommandHandler($duplicator, $resourceAccessRepository)(
            new DuplicateHostServicesCommand(sourceHostId: new HostId(5), newHostId: new HostId(9), duplicatedBy: new UserId(42)),
        );

        self::assertSame([['sourceHostId' => 5, 'newHostId' => 9, 'duplicatedBy' => 42]], $duplicator->duplicateCalls);
        // Once the copy has its services, their ACL rows are scoped for the source's groups.
        self::assertSame([['sourceHostId' => 5, 'newHostId' => 9]], $resourceAccessRepository->duplicatedHostServiceAccess);
    }

    public function testItDoesNotScopeTheServiceAclWhenDuplicationFails(): void
    {
        $duplicator = new FakeHostServiceDuplicator();
        $duplicator->duplicateThrows = true;

        $resourceAccessRepository = new FakeResourceAccessRepository();

        try {
            new DuplicateHostServicesCommandHandler($duplicator, $resourceAccessRepository)(
                new DuplicateHostServicesCommand(sourceHostId: new HostId(5), newHostId: new HostId(9), duplicatedBy: new UserId(42)),
            );
            self::fail('expected the duplication failure to propagate');
        } catch (\Throwable) {
            // expected
        }

        // The failure propagates before any service exists, so nothing is scoped.
        self::assertSame([], $resourceAccessRepository->duplicatedHostServiceAccess);
    }
}
