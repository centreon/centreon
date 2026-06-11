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

namespace Tests\Centreon\Infrastructure\Engine;

use Centreon\Domain\Engine\EngineException;
use Centreon\Domain\Gorgone\Command\EngineCommand;
use Centreon\Domain\Gorgone\Interfaces\CommandInterface;
use Centreon\Domain\Gorgone\Interfaces\CommandRepositoryInterface;
use Centreon\Infrastructure\Engine\EngineRepositoryGorgone;
use PHPUnit\Framework\TestCase;

class EngineRepositoryGorgoneTest extends TestCase
{
    /** @var CommandInterface[] Commands captured by the mocked Gorgone command repository */
    private array $sentCommands = [];

    private CommandRepositoryInterface $commandRepository;

    private EngineRepositoryGorgone $repository;

    protected function setUp(): void
    {
        $this->sentCommands = [];
        $this->commandRepository = $this->createMock(CommandRepositoryInterface::class);
        $this->commandRepository->method('send')->willReturnCallback(
            function (CommandInterface $command): string {
                $this->sentCommands[] = $command;

                return 'token';
            }
        );
        $this->repository = new EngineRepositoryGorgone($this->commandRepository);
    }

    public function testSendExternalCommandTargetsThePollerAndStripsTheHeader(): void
    {
        $this->repository->sendExternalCommand(
            'EXTERNALCMD:3:[1718096400] ACKNOWLEDGE_HOST_PROBLEM;srv-01;2;1;0;admin;ack'
        );

        $this->assertCount(1, $this->sentCommands);
        $command = $this->sentCommands[0];
        $this->assertInstanceOf(EngineCommand::class, $command);
        $this->assertSame('nodes/3/centreon/engine/command', $command->getUriRequest());
        $this->assertSame(CommandInterface::METHOD_POST, $command->getMethod());
        $this->assertSame(
            ['commands' => ['[1718096400] ACKNOWLEDGE_HOST_PROBLEM;srv-01;2;1;0;admin;ack']],
            json_decode((string) $command->getBodyRequest(), true)
        );
    }

    public function testSendExternalCommandsGroupsCommandsOfTheSamePollerInASingleRequest(): void
    {
        $this->repository->sendExternalCommands([
            'EXTERNALCMD:3:[1] SCHEDULE_HOST_DOWNTIME;srv-01;1;2;1;0;3600;admin;dt',
            'EXTERNALCMD:3:[1] SCHEDULE_HOST_SVC_DOWNTIME;srv-01;1;2;1;0;3600;admin;dt',
        ]);

        $this->assertCount(1, $this->sentCommands);
        $this->assertSame(
            [
                '[1] SCHEDULE_HOST_DOWNTIME;srv-01;1;2;1;0;3600;admin;dt',
                '[1] SCHEDULE_HOST_SVC_DOWNTIME;srv-01;1;2;1;0;3600;admin;dt',
            ],
            json_decode((string) $this->sentCommands[0]->getBodyRequest(), true)['commands']
        );
    }

    public function testSendExternalCommandsSendsOneRequestPerPoller(): void
    {
        $this->repository->sendExternalCommands([
            'EXTERNALCMD:3:[1] SCHEDULE_FORCED_HOST_CHECK;srv-01;1',
            'EXTERNALCMD:7:[1] SCHEDULE_FORCED_HOST_CHECK;srv-02;1',
        ]);

        $this->assertCount(2, $this->sentCommands);
        $this->assertSame('nodes/3/centreon/engine/command', $this->sentCommands[0]->getUriRequest());
        $this->assertSame('nodes/7/centreon/engine/command', $this->sentCommands[1]->getUriRequest());
    }

    public function testSendExternalCommandThrowsWhenTheHeaderCannotBeParsed(): void
    {
        $this->expectException(EngineException::class);

        $this->repository->sendExternalCommand('[1718096400] ACKNOWLEDGE_HOST_PROBLEM;srv-01;2;1;0;admin;ack');
    }

    public function testSendExternalCommandWrapsGorgoneFailuresInAnEngineException(): void
    {
        $commandRepository = $this->createMock(CommandRepositoryInterface::class);
        $commandRepository->method('send')->willThrowException(new \Exception('connection refused'));
        $repository = new EngineRepositoryGorgone($commandRepository);

        $this->expectException(EngineException::class);
        $this->expectExceptionMessageMatches('/poller 3 through Gorgone/');

        $repository->sendExternalCommand('EXTERNALCMD:3:[1] SCHEDULE_FORCED_HOST_CHECK;srv-01;1');
    }
}
