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

namespace Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Poller;

use ApiPlatform\Metadata\Post;
use App\MonitoringConfiguration\Application\Command\CreatePollerCommand;
use App\MonitoringConfiguration\Domain\Aggregate\GlobalMacro\GlobalMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\BrokerConfiguration;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\ConnectorConfiguration;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\EngineInformation;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\GorgoneCommunicationTypeEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\GorgoneConfiguration;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\Poller;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerAddress;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerCommand;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerName;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerTypeEnum;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerUid;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\TrapConfiguration;
use App\MonitoringConfiguration\Domain\Model\PollerToken;
use App\MonitoringConfiguration\Domain\Repository\PollerRepository;
use App\MonitoringConfiguration\Domain\Repository\PollerTokenRepository;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Dto\CreatePollerInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Poller\CreatePollerProcessor;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Poller\ResourcePollerTransformer;
use App\Security\Domain\Aggregate\Credential;
use App\Security\Domain\Aggregate\CredentialIdentifier;
use App\Security\Domain\Aggregate\UserId;
use App\Security\Infrastructure\Security\CredentialUser;
use App\Shared\Application\Command\CommandBus;
use App\Shared\Domain\Collection;
use App\Shared\Domain\Repository\EngineSecretsRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

final class CreatePollerProcessorCommunicationTypeTest extends TestCase
{
    public function testCloudPlatformUsesPullWss(): void
    {
        $capturedCommand = null;
        $processor = $this->buildProcessor(isCloudPlatform: true, capturedCommand: $capturedCommand);

        $processor->process(
            $this->buildInput(),
            new Post(),
        );

        self::assertInstanceOf(CreatePollerCommand::class, $capturedCommand);
        self::assertSame(GorgoneCommunicationTypeEnum::PullWss, $capturedCommand->gorgoneCommunicationType);
    }

    public function testOnPremPlatformUsesZmq(): void
    {
        $capturedCommand = null;
        $processor = $this->buildProcessor(isCloudPlatform: false, capturedCommand: $capturedCommand);

        $processor->process(
            $this->buildInput(),
            new Post(),
        );

        self::assertInstanceOf(CreatePollerCommand::class, $capturedCommand);
        self::assertSame(GorgoneCommunicationTypeEnum::ZMQ, $capturedCommand->gorgoneCommunicationType);
    }

    public function testCentralAddressIsPassedToCommand(): void
    {
        $capturedCommand = null;
        $processor = $this->buildProcessor(isCloudPlatform: false, capturedCommand: $capturedCommand);

        $processor->process(
            $this->buildInput(),
            new Post(),
        );

        self::assertInstanceOf(CreatePollerCommand::class, $capturedCommand);
        self::assertSame('192.168.1.254', $capturedCommand->centralAddress->value);
    }

    private function buildProcessor(bool $isCloudPlatform, ?object &$capturedCommand): CreatePollerProcessor
    {
        $poller = new Poller(
            id: new PollerId(42),
            name: new PollerName('TestPoller'),
            address: new PollerAddress('192.168.1.1'),
            isCentral: false,
            isDefault: false,
            isActivated: true,
            pollerType: PollerTypeEnum::VM,
            uid: new PollerUid(123456789012345),
            globalMacros: new Collection([], GlobalMacro::class),
            gorgoneConfiguration: new GorgoneConfiguration(),
            engineInformation: new EngineInformation(),
            brokerConfiguration: new BrokerConfiguration(),
            connectorConfiguration: new ConnectorConfiguration(),
            trapConfiguration: new TrapConfiguration(),
            pollerCommands: new Collection([], PollerCommand::class),
        );

        $commandBus = $this->createMock(CommandBus::class);
        $commandBus->method('execute')
            ->willReturnCallback(static function (object $command) use (&$capturedCommand, $poller): Poller {
                $capturedCommand = $command;

                return $poller;
            });

        $security = $this->createMock(Security::class);
        $security->method('getUser')
            ->willReturn(new CredentialUser(new Credential(
                new CredentialIdentifier('admin'),
                new UserId(1),
                active: true,
            )));

        $pollerTokenRepository = $this->createMock(PollerTokenRepository::class);
        $pollerTokenRepository->method('getValidPollerTokenByName')
            ->willReturn(new PollerToken(
                name: 'test-token',
                value: 'token-value',
                creationDate: new \DateTimeImmutable(),
                expirationDate: null,
                isRevoked: false,
            ));

        $engineSecretsRepository = $this->createMock(EngineSecretsRepository::class);
        $engineSecretsRepository->method('getAppSecret')->willReturn('app-secret');
        $engineSecretsRepository->method('getSalt')->willReturn('salt');

        $pollerRepository = $this->createMock(PollerRepository::class);
        $pollerRepository->method('findOneByName')->willReturn(null);

        return new CreatePollerProcessor(
            commandBus: $commandBus,
            transformer: new ResourcePollerTransformer(),
            security: $security,
            pollerRepository: $pollerRepository,
            pollerTokenRepository: $pollerTokenRepository,
            engineSecretsRepository: $engineSecretsRepository,
            isCloudPlatform: $isCloudPlatform,
        );
    }

    private function buildInput(): CreatePollerInput
    {
        return new CreatePollerInput(
            name: 'TestPoller',
            pollerType: 'vm',
            address: '192.168.1.1',
            pollerTokenName: 'test-token',
            centralAddress: '192.168.1.254',
        );
    }
}
