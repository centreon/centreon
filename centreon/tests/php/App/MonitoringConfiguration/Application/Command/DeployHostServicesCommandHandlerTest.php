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

use App\MonitoringConfiguration\Application\Command\DeployHostServicesCommand;
use App\MonitoringConfiguration\Application\Command\DeployHostServicesCommandHandler;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\Security\Domain\Aggregate\UserId;
use PHPUnit\Framework\TestCase;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeServiceDeployer;

final class DeployHostServicesCommandHandlerTest extends TestCase
{
    public function testItDeploysTheServicesOfTheRequestedHost(): void
    {
        $serviceDeployer = new FakeServiceDeployer();

        new DeployHostServicesCommandHandler($serviceDeployer)(
            new DeployHostServicesCommand(new HostId(12), new UserId(7)),
        );

        self::assertSame([['hostId' => 12, 'requestedBy' => 7]], $serviceDeployer->deployCalls);
    }

    public function testItLetsADeploymentFailurePropagate(): void
    {
        $serviceDeployer = new FakeServiceDeployer();
        $serviceDeployer->deployThrows = true;

        $this->expectException(\Throwable::class);

        new DeployHostServicesCommandHandler($serviceDeployer)(
            new DeployHostServicesCommand(new HostId(12), new UserId(7)),
        );
    }
}
