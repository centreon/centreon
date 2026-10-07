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

namespace Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Command;

use App\MonitoringConfiguration\Domain\Aggregate\Command\Command;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandLine;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandName;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandTypeEnum;
use App\MonitoringConfiguration\Domain\Repository\CommandResourceCount;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Command\ListCommandResourceTransformer;
use PHPUnit\Framework\TestCase;

final class ListCommandResourceTransformerTest extends TestCase
{
    public function testItExposesTheLinkedResourceCount(): void
    {
        $resource = (new ListCommandResourceTransformer())->transform($this->command(), [
            'linkedResourceCount' => new CommandResourceCount(usedHosts: 1, usedServices: 2, usedHostTemplates: 3, usedServiceTemplates: 4),
        ]);

        self::assertSame(1, $resource->usedHostsCount);
        self::assertSame(2, $resource->usedServicesCount);
        self::assertSame(3, $resource->usedHostTemplatesCount);
        self::assertSame(4, $resource->usedServiceTemplatesCount);
    }

    public function testItRequiresTheLinkedResourceCount(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new ListCommandResourceTransformer())->transform($this->command());
    }

    private function command(): Command
    {
        return new Command(
            id: new CommandId(1),
            name: new CommandName('check_1'),
            type: CommandTypeEnum::Check,
            commandLine: new CommandLine('$USER1$/check_ping'),
            isShellEnabled: false,
            isActivated: true,
            isFromMonitoringConnector: false,
            connector: null,
            comment: null,
        );
    }
}
