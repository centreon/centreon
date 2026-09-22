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

namespace Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Host;

use ApiPlatform\Metadata\Post;
use App\MonitoringConfiguration\Domain\Aggregate\Host\DataProcessing;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Host;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostAddress;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostName;
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupName;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerName;
use App\MonitoringConfiguration\Domain\Repository\CommandRepository;
use App\MonitoringConfiguration\Domain\Repository\HostGroupRepository;
use App\MonitoringConfiguration\Domain\Repository\PollerRepository;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Dto\CreateHostInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\DataProcessingOutput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostResource;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\State\Host\CreateHostProcessor;
use App\Security\Domain\Aggregate\Credential;
use App\Security\Domain\Aggregate\CredentialIdentifier;
use App\Security\Domain\Aggregate\UserId;
use App\Security\Infrastructure\Security\CredentialUser;
use App\Shared\Application\Command\CommandBus;
use App\Shared\Domain\Aggregate\TriStateEnum;
use App\Shared\Domain\Collection;
use App\Shared\Infrastructure\TransformerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Covers the platform-dependent shaping of the data_processing output, which the HTTP suite cannot
 * exercise because IS_CLOUD_PLATFORM is fixed for the whole kernel and cannot be flipped per test.
 */
final class CreateHostProcessorDataProcessingCloudTest extends TestCase
{
    public function testItOmitsTheOnPremiseOnlyMembersOnACloudPlatform(): void
    {
        $output = $this->processDataProcessingOutput(isCloudPlatform: true);

        // The members available on every platform survive.
        self::assertSame(TriStateEnum::True, $output->checkFreshness);
        self::assertSame(120, $output->freshnessThreshold);
        self::assertSame(TriStateEnum::UseDefault, $output->eventHandlerEnabled);
        self::assertNull($output->eventHandler);

        // The on-premise-only members are dropped from the contract on Cloud.
        self::assertNull($output->acknowledgmentTimeout);
        self::assertNull($output->flapDetectionEnabled);
        self::assertNull($output->lowFlapThreshold);
        self::assertNull($output->highFlapThreshold);
        self::assertSame([], $output->eventHandlerArgs);
    }

    public function testItKeepsTheOnPremiseOnlyMembersOnAnOnPremisePlatform(): void
    {
        $output = $this->processDataProcessingOutput(isCloudPlatform: false);

        self::assertSame(15, $output->acknowledgmentTimeout);
        self::assertSame(TriStateEnum::False, $output->flapDetectionEnabled);
        self::assertSame(10, $output->lowFlapThreshold);
        self::assertSame(60, $output->highFlapThreshold);
        self::assertSame(['warn', 'crit'], $output->eventHandlerArgs);
    }

    private function processDataProcessingOutput(bool $isCloudPlatform): DataProcessingOutput
    {
        $host = new Host(
            id: new HostId(1),
            name: new HostName('server-dp'),
            alias: null,
            address: new HostAddress('10.0.0.1'),
            activated: true,
            pollerId: new PollerId(1),
            templateIds: new Collection([], HostTemplateId::class),
            hostGroupIds: new Collection([], HostGroupId::class),
            dataProcessing: new DataProcessing(
                checkFreshness: TriStateEnum::True,
                flapDetectionEnabled: TriStateEnum::False,
                eventHandlerEnabled: TriStateEnum::UseDefault,
                acknowledgmentTimeout: 15,
                freshnessThreshold: 120,
                lowFlapThreshold: 10,
                highFlapThreshold: 60,
                eventHandlerArgs: ['warn', 'crit'],
            ),
        );

        $commandBus = $this->createMock(CommandBus::class);
        $commandBus->method('execute')->willReturn($host);

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn(new CredentialUser(new Credential(
            new CredentialIdentifier('admin'),
            new UserId(1),
            active: true,
        )));

        $transformer = $this->createMock(TransformerInterface::class);
        $transformer->method('transform')->willReturn(
            new HostResource(id: 1, name: 'server-dp', alias: null, address: '10.0.0.1', activated: true)
        );

        $pollerRepository = $this->createMock(PollerRepository::class);
        $pollerRepository->method('findNamesByIds')->willReturn(new Collection([], PollerName::class));

        $hostGroupRepository = $this->createMock(HostGroupRepository::class);
        $hostGroupRepository->method('findNamesByIds')->willReturn(new Collection([], HostGroupName::class));

        $processor = new CreateHostProcessor(
            commandBus: $commandBus,
            transformer: $transformer,
            security: $security,
            pollerRepository: $pollerRepository,
            hostGroupRepository: $hostGroupRepository,
            commandRepository: $this->createMock(CommandRepository::class),
            isCloudPlatform: $isCloudPlatform,
        );

        $resource = $processor->process($this->buildInput(), new Post());

        return $resource->dataProcessing;
    }

    private function buildInput(): CreateHostInput
    {
        return new CreateHostInput(
            name: 'server-dp',
            address: '10.0.0.1',
            pollerId: 1,
        );
    }
}
