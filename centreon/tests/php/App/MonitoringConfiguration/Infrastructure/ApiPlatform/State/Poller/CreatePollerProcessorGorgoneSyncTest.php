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
use App\MonitoringConfiguration\Domain\Aggregate\GlobalMacro\GlobalMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\BrokerInformation;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\CentralAddress;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\ConnectorConfiguration;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\EngineInformation;
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
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeGorgoneNodesSynchronizer;

/**
 * The Central only accepts a PullWSS poller once Gorgone re-read its node list, so a creation
 * must always trigger the sync — and only after the poller has been persisted.
 */
final class CreatePollerProcessorGorgoneSyncTest extends TestCase
{
    private FakeGorgoneNodesSynchronizer $synchronizer;

    /** @var int calls counted while the create-poller command was being handled */
    private int $synchronizeCallsDuringCommand = 0;

    protected function setUp(): void
    {
        $this->synchronizer = new FakeGorgoneNodesSynchronizer();
    }

    public function testItSynchronizesGorgoneNodesOncePerCreation(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('error');

        $this->buildProcessor($logger)->process($this->buildInput(), new Post());

        self::assertSame(1, $this->synchronizer->synchronizeCalls);
    }

    /**
     * Gorgone reads the poller list on its own database connection: a sync sent while the
     * create-poller transaction is still open would find nothing to register.
     */
    public function testItSynchronizesOnlyOnceThePollerIsPersisted(): void
    {
        $this->buildProcessor($this->createMock(LoggerInterface::class))
            ->process($this->buildInput(), new Post());

        self::assertSame(0, $this->synchronizeCallsDuringCommand);
        self::assertSame(1, $this->synchronizer->synchronizeCalls);
    }

    public function testItCreatesThePollerEvenWhenGorgoneIsUnreachable(): void
    {
        $this->synchronizer->throwable = new \RuntimeException('Error when connecting to the Gorgone server');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('error')
            ->with(
                'Failed to trigger Gorgone nodes sync',
                self::callback(static function (array $context): bool {
                    self::assertSame(42, $context['poller_id']);
                    self::assertIsArray($context['exception']);
                    self::assertArrayHasKey('exceptions', $context['exception']);

                    return true;
                })
            );

        $resource = $this->buildProcessor($logger)->process($this->buildInput(), new Post());

        self::assertSame('TestPoller', $resource->name);
    }

    private function buildProcessor(LoggerInterface $logger): CreatePollerProcessor
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
            brokerInformation: new BrokerInformation(),
            connectorConfiguration: new ConnectorConfiguration(),
            trapConfiguration: new TrapConfiguration(),
            pollerCommands: new Collection([], PollerCommand::class),
            centralAddress: new CentralAddress('192.168.1.254'),
        );

        $commandBus = $this->createMock(CommandBus::class);
        $commandBus->method('execute')
            ->willReturnCallback(function () use ($poller): Poller {
                $this->synchronizeCallsDuringCommand = $this->synchronizer->synchronizeCalls;

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
            gorgoneNodesSynchronizer: $this->synchronizer,
            logger: $logger,
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
