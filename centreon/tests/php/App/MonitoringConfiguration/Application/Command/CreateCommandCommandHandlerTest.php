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

use App\MonitoringConfiguration\Application\Command\CreateCommandCommand;
use App\MonitoringConfiguration\Application\Command\CreateCommandCommandHandler;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandComment;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandLine;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandName;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandTypeEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Connector\Connector;
use App\MonitoringConfiguration\Domain\Aggregate\Connector\ConnectorCommandLine;
use App\MonitoringConfiguration\Domain\Aggregate\Connector\ConnectorDescription;
use App\MonitoringConfiguration\Domain\Aggregate\Connector\ConnectorId;
use App\MonitoringConfiguration\Domain\Aggregate\Connector\ConnectorName;
use App\MonitoringConfiguration\Domain\Event\CommandCreated;
use App\MonitoringConfiguration\Domain\Exception\CommandAlreadyExistsException;
use PHPUnit\Framework\TestCase;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeCommandRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeConnectorRepository;
use Tests\App\Shared\Double\EventBusSpy;

final class CreateCommandCommandHandlerTest extends TestCase
{
    public function testCreateCommand(): void
    {
        $repository = new FakeCommandRepository();
        $connectorRepository = new FakeConnectorRepository();
        $eventBus = new EventBusSpy();
        $type = CommandTypeEnum::fromName('Check');
        $handler = new CreateCommandCommandHandler($repository, $connectorRepository, $eventBus);
        $connector = new Connector(
            id: new ConnectorId(1),
            name: new ConnectorName('Perl Connector'),
            commandLine: new ConnectorCommandLine('/usr/bin/perl'),
            description: new ConnectorDescription('description of the connector'),
            isActivated: true,
        );
        $connectorRepository->add($connector);
        $command = new CreateCommandCommand(
            name: new CommandName('NAME'),
            type: $type,
            commandLine: new CommandLine('dosomething $ARG1$'),
            isShellEnabled: true,
            connectorId: new ConnectorId($connector->id()->value),
            creatorId: 1,
            comment: new CommandComment('comment of the command'),
        );
        $handler($command);

        self::assertNotNull($repository->findOneByName(new CommandName('NAME')));
    }

    public function testCannotCreateSameCommand(): void
    {
        $repository = new FakeCommandRepository();
        $connectorRepository = new FakeConnectorRepository();
        $eventBus = new EventBusSpy();
        $type = CommandTypeEnum::fromName('Check');
        $handler = new CreateCommandCommandHandler($repository, $connectorRepository, $eventBus);
        $connector = new Connector(
            id: new ConnectorId(1),
            name: new ConnectorName('Perl Connector'),
            commandLine: new ConnectorCommandLine('/usr/bin/perl'),
            description: new ConnectorDescription('description of the connector'),
            isActivated: true,
        );
        $connectorRepository->add($connector);
        $command = new CreateCommandCommand(
            name: new CommandName('NAME'),
            type: $type,
            commandLine: new CommandLine('dosomething $ARG1$'),
            isShellEnabled: true,
            connectorId: new ConnectorId($connector->id()->value),
            creatorId: 1,
            comment: new CommandComment('comment of the command'),
        );
        $handler($command);

        $this->expectException(CommandAlreadyExistsException::class);

        $handler($command);
    }

    public function testDispatchCreatedEvent(): void
    {
        $repository = new FakeCommandRepository();
        $connectorRepository = new FakeConnectorRepository();
        $eventBus = new EventBusSpy();
        $type = CommandTypeEnum::fromName('Check');
        $handler = new CreateCommandCommandHandler($repository, $connectorRepository, $eventBus);
        $connector = new Connector(
            id: new ConnectorId(1),
            name: new ConnectorName('Perl Connector'),
            commandLine: new ConnectorCommandLine('/usr/bin/perl'),
            description: new ConnectorDescription('description of the connector'),
            isActivated: true,
        );
        $connectorRepository->add($connector);
        $command = new CreateCommandCommand(
            name: new CommandName('NAME'),
            type: $type,
            commandLine: new CommandLine('dosomething $ARG1$'),
            isShellEnabled: true,
            connectorId: new ConnectorId($connector->id()->value),
            creatorId: 1,
            comment: new CommandComment('comment of the command'),
        );
        $handler($command);

        self::assertTrue($eventBus->shouldHaveDispatched(CommandCreated::class));
    }
}
