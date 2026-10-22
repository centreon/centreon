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

namespace Tests\Core\AgentConfiguration\Application\UseCase\CreateHostForAgentConfiguration;

use Core\AgentConfiguration\Application\Repository\ReadAgentConfigurationRepositoryInterface;
use Core\AgentConfiguration\Application\UseCase\CreateHostForAgentConfiguration\CreateHostForAgentConfiguration;
use Core\AgentConfiguration\Application\UseCase\CreateHostForAgentConfiguration\CreateHostForAgentConfigurationRequest;
use Core\AgentConfiguration\Domain\Model\AgentConfiguration;
use Core\AgentConfiguration\Domain\Model\ConfigurationParameters\CmaConfigurationParameters;
use Core\AgentConfiguration\Domain\Model\ConnectionModeEnum;
use Core\AgentConfiguration\Domain\Model\Type;
use Core\Host\Application\Repository\ReadHostRepositoryInterface;
use Core\Host\Application\Repository\WriteHostRepositoryInterface;
use Core\Host\Domain\Model\NewHost;
use Core\HostTemplate\Application\Repository\ReadHostTemplateRepositoryInterface;
use Core\HostTemplate\Domain\Model\HostTemplate;
use Core\MonitoringServer\Application\Repository\ReadMonitoringServerRepositoryInterface;
use Core\Service\Application\Repository\ReadServiceRepositoryInterface;
use Core\Service\Application\Repository\WriteServiceRepositoryInterface;
use Core\ServiceTemplate\Application\Repository\ReadServiceTemplateRepositoryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class CreateHostForAgentConfigurationPollerResolutionTest extends TestCase
{
    private const BROKER_INSTANCE_UID = 366141068393054539;
    private const MONITORING_SERVER_ID = 2;
    private const HOST_ID = 10;
    private const HOST_TEMPLATE_ID = 5;
    private const HOST_NAME = 'test-host';
    private const HOST_TEMPLATE_NAME = 'generic-host';

    private ReadAgentConfigurationRepositoryInterface&MockObject $readAgentConfigurationRepository;

    private ReadHostRepositoryInterface&MockObject $readHostRepository;

    private ReadHostTemplateRepositoryInterface&MockObject $readHostTemplateRepository;

    private WriteHostRepositoryInterface&MockObject $writeHostRepository;

    private ReadServiceRepositoryInterface&MockObject $readServiceRepository;

    private ReadServiceTemplateRepositoryInterface&MockObject $readServiceTemplateRepository;

    private WriteServiceRepositoryInterface&MockObject $writeServiceRepository;

    private ReadMonitoringServerRepositoryInterface&MockObject $readMonitoringServerRepository;

    private CreateHostForAgentConfiguration $useCase;

    protected function setUp(): void
    {
        $this->readAgentConfigurationRepository = $this->createMock(ReadAgentConfigurationRepositoryInterface::class);
        $this->readHostRepository = $this->createMock(ReadHostRepositoryInterface::class);
        $this->readHostTemplateRepository = $this->createMock(ReadHostTemplateRepositoryInterface::class);
        $this->writeHostRepository = $this->createMock(WriteHostRepositoryInterface::class);
        $this->readServiceRepository = $this->createMock(ReadServiceRepositoryInterface::class);
        $this->readServiceTemplateRepository = $this->createMock(ReadServiceTemplateRepositoryInterface::class);
        $this->writeServiceRepository = $this->createMock(WriteServiceRepositoryInterface::class);
        $this->readMonitoringServerRepository = $this->createMock(ReadMonitoringServerRepositoryInterface::class);

        $this->useCase = new CreateHostForAgentConfiguration(
            agentConfigurationRepository: $this->readAgentConfigurationRepository,
            readHostRepository: $this->readHostRepository,
            readHostTemplateRepository: $this->readHostTemplateRepository,
            writeHostRepository: $this->writeHostRepository,
            readServiceRepository: $this->readServiceRepository,
            readServiceTemplateRepository: $this->readServiceTemplateRepository,
            writeServiceRepository: $this->writeServiceRepository,
            readMonitoringServerRepository: $this->readMonitoringServerRepository,
        );
    }

    protected function tearDown(): void
    {
        foreach (['prod', 'test', 'dev'] as $env) {
            $file = _CENTREON_LOG_ . $env . '.web.log';
            if (file_exists($file)) {
                @unlink($file);
            }
        }
    }

    public function testCreatesHostOnPollerResolvedFromBrokerInstanceUid(): void
    {
        $this->readMonitoringServerRepository
            ->expects($this->once())
            ->method('findIdByInstanceId')
            ->with(self::BROKER_INSTANCE_UID)
            ->willReturn(self::MONITORING_SERVER_ID);

        $this->expectHostCreatedOnMonitoringServer(self::MONITORING_SERVER_ID);

        $result = ($this->useCase)($this->createRequest(self::BROKER_INSTANCE_UID));

        $this->assertTrue($result['success']);
        $this->assertSame('Host creation process completed successfully.', $result['message']);
    }

    public function testStillAcceptsConfigurationIdAsInstanceId(): void
    {
        $this->readMonitoringServerRepository
            ->expects($this->once())
            ->method('findIdByInstanceId')
            ->with(self::MONITORING_SERVER_ID)
            ->willReturn(self::MONITORING_SERVER_ID);

        $this->expectHostCreatedOnMonitoringServer(self::MONITORING_SERVER_ID);

        $result = ($this->useCase)($this->createRequest(self::MONITORING_SERVER_ID));

        $this->assertTrue($result['success']);
        $this->assertSame('Host creation process completed successfully.', $result['message']);
    }

    public function testReturnsFailureWhenNoMonitoringServerMatchesInstanceId(): void
    {
        $this->readMonitoringServerRepository
            ->expects($this->once())
            ->method('findIdByInstanceId')
            ->with(self::BROKER_INSTANCE_UID)
            ->willReturn(null);

        $this->readAgentConfigurationRepository
            ->expects($this->never())
            ->method('findByPollerId');

        $this->writeHostRepository
            ->expects($this->never())
            ->method('add');

        $result = ($this->useCase)($this->createRequest(self::BROKER_INSTANCE_UID));

        $this->assertSame(
            [
                'success' => false,
                'message' => 'Host creation process failed.',
                'details' => 'Reason: Agent configuration not found for poller 366141068393054539.',
            ],
            $result
        );
    }

    private function createRequest(int $pollerId): CreateHostForAgentConfigurationRequest
    {
        return new CreateHostForAgentConfigurationRequest(
            pollerId: $pollerId,
            hostName: self::HOST_NAME,
            address: '192.168.1.100',
            templateName: self::HOST_TEMPLATE_NAME,
        );
    }

    private function createAgentConfiguration(): AgentConfiguration
    {
        return new AgentConfiguration(
            id: 1,
            name: 'test-ac',
            type: Type::CMA,
            connectionMode: ConnectionModeEnum::SECURE,
            configuration: new CmaConfigurationParameters([
                'agent_initiated' => true,
                'poller_initiated' => true,
                'otel_public_certificate' => 'cert',
                'otel_ca_certificate' => 'ca',
                'otel_private_key' => 'key',
                'port' => AgentConfiguration::DEFAULT_PORT,
                'hosts' => [],
                'tokens' => [
                    [
                        'name' => 'test-token',
                        'creator_id' => 1,
                    ],
                ],
                'create_host_auto' => true,
            ]),
        );
    }

    private function expectHostCreatedOnMonitoringServer(int $monitoringServerId): void
    {
        $this->readAgentConfigurationRepository
            ->expects($this->once())
            ->method('findByPollerId')
            ->with($monitoringServerId)
            ->willReturn($this->createAgentConfiguration());

        $this->readHostRepository
            ->expects($this->once())
            ->method('existsByName')
            ->with(self::HOST_NAME)
            ->willReturn(false);

        $this->writeHostRepository
            ->expects($this->once())
            ->method('add')
            ->with($this->callback(
                fn (NewHost $newHost): bool => $newHost->getMonitoringServerId() === $monitoringServerId
            ))
            ->willReturn(self::HOST_ID);

        $this->readHostTemplateRepository
            ->expects($this->once())
            ->method('findByName')
            ->with(self::HOST_TEMPLATE_NAME)
            ->willReturn(new HostTemplate(
                id: self::HOST_TEMPLATE_ID,
                name: self::HOST_TEMPLATE_NAME,
                alias: 'Generic Host Template',
            ));

        $this->readHostRepository
            ->expects($this->once())
            ->method('findParents')
            ->with(self::HOST_ID)
            ->willReturn([]);
    }
}
