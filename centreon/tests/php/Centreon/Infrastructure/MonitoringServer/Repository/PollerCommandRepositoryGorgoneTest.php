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

namespace Tests\Centreon\Infrastructure\MonitoringServer\Repository;

use Centreon\Domain\Engine\EngineException;
use Centreon\Domain\Gorgone\Command\LegacyCmdCommand;
use Centreon\Domain\Gorgone\Interfaces\CommandInterface;
use Centreon\Domain\Gorgone\Interfaces\CommandRepositoryInterface;
use Centreon\Infrastructure\MonitoringServer\Repository\PollerCommandRepositoryGorgone;
use PHPUnit\Framework\TestCase;

class PollerCommandRepositoryGorgoneTest extends TestCase
{
    /** @var CommandInterface[] */
    private array $sentCommands = [];

    private CommandRepositoryInterface $commandRepository;

    private PollerCommandRepositoryGorgone $repository;

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
        $this->repository = new PollerCommandRepositoryGorgone($this->commandRepository);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function provideCommands(): array
    {
        return [
            'send config files' => ['sendConfigFiles', 'SENDCFGFILE'],
            'reload engine' => ['reloadEngine', 'RELOAD'],
            'restart engine' => ['restartEngine', 'RESTART'],
            'reload broker' => ['reloadBroker', 'RELOADBROKER'],
            'sync trap configuration' => ['syncTrapConfiguration', 'SYNCTRAP'],
            'reload trapd' => ['reloadTrapd', 'RELOADCENTREONTRAPD'],
            'restart trapd' => ['restartTrapd', 'RESTARTCENTREONTRAPD'],
        ];
    }

    /**
     * @dataProvider provideCommands
     */
    public function testSendsTheLegacycmdCommandToTheCentralEndpoint(string $method, string $expectedCommand): void
    {
        $this->repository->{$method}(7);

        $this->assertCount(1, $this->sentCommands);
        $command = $this->sentCommands[0];
        $this->assertInstanceOf(LegacyCmdCommand::class, $command);
        // Central legacycmd endpoint (no nodes/{id}/ prefix); target poller carried in the body.
        $this->assertSame('centreon/legacycmd/command', $command->getUriRequest());
        $this->assertSame(CommandInterface::METHOD_POST, $command->getMethod());
        $this->assertSame(
            [['command' => $expectedCommand, 'target' => 7]],
            json_decode((string) $command->getBodyRequest(), true)
        );
    }

    public function testWrapsGorgoneFailuresInAnEngineException(): void
    {
        $commandRepository = $this->createMock(CommandRepositoryInterface::class);
        $commandRepository->method('send')->willThrowException(new \Exception('connection refused'));
        $repository = new PollerCommandRepositoryGorgone($commandRepository);

        $this->expectException(EngineException::class);
        $this->expectExceptionMessageMatches('/"RELOAD" command for poller 7 through Gorgone/');

        $repository->reloadEngine(7);
    }
}
