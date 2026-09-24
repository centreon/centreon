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
use App\MonitoringConfiguration\Domain\Repository\CommandRepository;
use App\MonitoringConfiguration\Domain\Repository\CommandResourceCount;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Command\ListCommandResourceListTransformer;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Command\ListCommandResourceTransformer;
use PHPUnit\Framework\TestCase;

final class ListCommandResourceListTransformerTest extends TestCase
{
    public function testItCountsTheLinkedResourcesOnceForTheWholeList(): void
    {
        $commandRepository = $this->createMock(CommandRepository::class);
        $commandRepository->expects(self::once())
            ->method('countLinkedResources')
            ->willReturn([
                1 => new CommandResourceCount(usedHosts: 1, usedServices: 2, usedHostTemplates: 3, usedServiceTemplates: 4),
                2 => new CommandResourceCount(usedHosts: 0, usedServices: 0, usedHostTemplates: 0, usedServiceTemplates: 0),
            ]);

        $resources = (new ListCommandResourceListTransformer($commandRepository, new ListCommandResourceTransformer()))
            ->transform([$this->command(1), $this->command(2)]);

        self::assertCount(2, $resources);
        self::assertSame(1, $resources[0]->usedHostsCount);
        self::assertSame(2, $resources[0]->usedServicesCount);
        self::assertSame(3, $resources[0]->usedHostTemplatesCount);
        self::assertSame(4, $resources[0]->usedServiceTemplatesCount);
        self::assertSame(0, $resources[1]->usedHostsCount);
    }

    public function testItDoesNotQueryForAnEmptyList(): void
    {
        $commandRepository = $this->createMock(CommandRepository::class);
        $commandRepository->expects(self::never())->method('countLinkedResources');

        self::assertSame([], (new ListCommandResourceListTransformer($commandRepository, new ListCommandResourceTransformer()))->transform([]));
    }

    public function testTheItemTransformerRequiresTheLinkedResourceCount(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new ListCommandResourceTransformer())->transform($this->command(1));
    }

    private function command(int $id): Command
    {
        return new Command(
            id: new CommandId($id),
            name: new CommandName('check_' . $id),
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
