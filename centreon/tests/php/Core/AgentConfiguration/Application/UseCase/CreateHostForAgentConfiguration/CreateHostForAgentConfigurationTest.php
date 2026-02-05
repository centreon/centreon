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
use Core\AgentConfiguration\Domain\Model\ConfigurationParametersInterface;
use Core\AgentConfiguration\Domain\Model\ConnectionModeEnum;
use Core\AgentConfiguration\Domain\Model\Type;
use Core\Host\Application\Repository\ReadHostRepositoryInterface;
use Core\Host\Application\Repository\WriteHostRepositoryInterface;
use Core\HostTemplate\Application\Repository\ReadHostTemplateRepositoryInterface;
use Core\HostTemplate\Domain\Model\HostTemplate;
use Core\Service\Application\Repository\ReadServiceRepositoryInterface;
use Core\Service\Application\Repository\WriteServiceRepositoryInterface;
use Core\Service\Domain\Model\Service;
use Core\Service\Domain\Model\ServiceNamesByHost;
use Core\ServiceTemplate\Application\Repository\ReadServiceTemplateRepositoryInterface;
use Core\ServiceTemplate\Domain\Model\ServiceTemplate;

beforeEach(function (): void {
    $this->readAgentConfigurationRepository = $this->createMock(ReadAgentConfigurationRepositoryInterface::class);
    $this->readHostRepository = $this->createMock(ReadHostRepositoryInterface::class);
    $this->readHostTemplateRepository = $this->createMock(ReadHostTemplateRepositoryInterface::class);
    $this->writeHostRepository = $this->createMock(WriteHostRepositoryInterface::class);
    $this->readServiceRepository = $this->createMock(ReadServiceRepositoryInterface::class);
    $this->readServiceTemplateRepository = $this->createMock(ReadServiceTemplateRepositoryInterface::class);
    $this->writeServiceRepository = $this->createMock(WriteServiceRepositoryInterface::class);

    $this->useCase = new CreateHostForAgentConfiguration(
        agentConfigurationRepository: $this->readAgentConfigurationRepository,
        readHostRepository: $this->readHostRepository,
        readHostTemplateRepository: $this->readHostTemplateRepository,
        writeHostRepository: $this->writeHostRepository,
        readServiceRepository: $this->readServiceRepository,
        readServiceTemplateRepository: $this->readServiceTemplateRepository,
        writeServiceRepository: $this->writeServiceRepository,
    );

    $this->pollerId = 1;
    $this->hostName = 'test-host';
    $this->address = '192.168.1.100';
    $this->templateName = 'generic-host';

    $this->request = new CreateHostForAgentConfigurationRequest(
        pollerId: $this->pollerId,
        hostName: $this->hostName,
        address: $this->address,
        templateName: $this->templateName,
    );

    $this->configurationParameters = new CmaConfigurationParameters([
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
    ]);

    $this->agentConfiguration = new AgentConfiguration(
        id: 1,
        name: 'test-ac',
        type: Type::CMA,
        connectionMode: ConnectionModeEnum::SECURE,
        configuration: $this->configurationParameters,
    );
});

it(
    'should return FAILURE when pollerId is not a positive integer',
    function (): void {
        $invalidRequest = new CreateHostForAgentConfigurationRequest(
            pollerId: 0,
            hostName: $this->hostName,
            address: $this->address,
            templateName: $this->templateName,
        );

        expect(fn () => ($this->useCase)($invalidRequest))
            ->toThrow(
                \InvalidArgumentException::class,
                'The poller id must be a positive integer.'
            );
    }
);

it(
    'should return FAILURE when agent configuration is not found for the poller',
    function (): void {
        $this->readAgentConfigurationRepository
            ->expects($this->once())
            ->method('findByPollerId')
            ->with($this->pollerId)
            ->willReturn(null);

        $result = ($this->useCase)($this->request);

        expect($result)->toBe([
            'success' => false,
            'message' => 'Host creation process failed.',
            'details' => 'Reason: Agent configuration not found for poller 1.',
        ]);
    }
);

it(
    'should return FAILURE when host name is empty',
    function (): void {
        $this->readAgentConfigurationRepository
            ->expects($this->once())
            ->method('findByPollerId')
            ->with($this->pollerId)
            ->willReturn($this->agentConfiguration);

        $invalidRequest = new CreateHostForAgentConfigurationRequest(
            pollerId: $this->pollerId,
            hostName: '',
            address: $this->address,
            templateName: $this->templateName,
        );

        expect(fn () => ($this->useCase)($invalidRequest))
            ->toThrow(
                \InvalidArgumentException::class,
                'The host name must not be empty.'
            );
    }
);

it(
    'should return FAILURE when host name already exists',
    function (): void {
        $this->readAgentConfigurationRepository
            ->expects($this->once())
            ->method('findByPollerId')
            ->willReturn($this->agentConfiguration);

        $this->readHostRepository
            ->expects($this->once())
            ->method('existsByName')
            ->with($this->hostName)
            ->willReturn(true);

        $result = ($this->useCase)($this->request);

        expect($result)->toBe([
            'success' => false,
            'message' => 'Host creation process failed.',
            'details' => 'Reason: A host with the name "test-host" already exists.',
        ]);
    }
);

it(
    'should return FAILURE when address is empty',
    function (): void {
        $this->readAgentConfigurationRepository
            ->expects($this->once())
            ->method('findByPollerId')
            ->with($this->pollerId)
            ->willReturn($this->agentConfiguration);

        $this->readHostRepository
            ->expects($this->once())
            ->method('existsByName')
            ->with($this->hostName)
            ->willReturn(false);

        $invalidRequest = new CreateHostForAgentConfigurationRequest(
            pollerId: $this->pollerId,
            hostName: $this->hostName,
            address: '',
            templateName: $this->templateName,
        );

        expect(fn () => ($this->useCase)($invalidRequest))
            ->toThrow(
                \InvalidArgumentException::class,
                'The address must not be empty.'
            );
    }
);

it(
    'should return SUCCESS and skip host creation when create_host_auto is false',
    function (): void {
        $configData = $this->configurationParameters->getData();
        $configData['create_host_auto'] = false;
        $configWithAutoCreateDisabled = $this->createMock(ConfigurationParametersInterface::class);
        $configWithAutoCreateDisabled
            ->method('getData')
            ->willReturn($configData);

        $agentConfigWithAutoCreateDisabled = new AgentConfiguration(
            id: 1,
            name: 'test-ac',
            type: Type::CMA,
            connectionMode: ConnectionModeEnum::SECURE,
            configuration: $configWithAutoCreateDisabled,
        );

        $this->readAgentConfigurationRepository
            ->expects($this->once())
            ->method('findByPollerId')
            ->willReturn($agentConfigWithAutoCreateDisabled);

        $this->readHostRepository
            ->expects($this->once())
            ->method('existsByName')
            ->willReturn(false);

        $result = ($this->useCase)($this->request);

        expect($result)->toBe([
            'success' => true,
            'message' => 'Host creation process skipped.',
            'details' => 'Reason: Agent configuration is not set to create host automatically.',
        ]);
    }
);

it(
    'should create host and services successfully when all conditions are met',
    function (): void {
        $hostId = 10;
        $serviceId1 = 100;
        $serviceId2 = 101;
        $templateId = 5;

        $configData = $this->configurationParameters->getData();
        $configData['create_host_auto'] = true;
        $configWithAutoCreateEnabled = $this->createMock(ConfigurationParametersInterface::class);
        $configWithAutoCreateEnabled
            ->method('getData')
            ->willReturn($configData);

        $agentConfigWithAutoCreateEnabled = new AgentConfiguration(
            id: 1,
            name: 'test-ac',
            type: Type::CMA,
            connectionMode: ConnectionModeEnum::SECURE,
            configuration: $configWithAutoCreateEnabled,
        );

        $hostTemplate = new HostTemplate(
            id: $templateId,
            name: $this->templateName,
            alias: 'Generic Host Template',
        );

        $serviceTemplate1 = new ServiceTemplate(
            id: 50,
            name: 'service-template-1',
            alias: 'Service 1',
        );

        $serviceTemplate2 = new ServiceTemplate(
            id: 51,
            name: 'service-template-2',
            alias: 'Service 2',
        );

        $service1 = new Service(
            id: $serviceId1,
            name: 'Service 1',
            hostId: $hostId,
        );

        $service2 = new Service(
            id: $serviceId2,
            name: 'Service 2',
            hostId: $hostId,
        );

        $this->readAgentConfigurationRepository
            ->expects($this->once())
            ->method('findByPollerId')
            ->willReturn($agentConfigWithAutoCreateEnabled);

        $this->readHostRepository
            ->expects($this->once())
            ->method('existsByName')
            ->with($this->hostName)
            ->willReturn(false);

        $this->writeHostRepository
            ->expects($this->once())
            ->method('add')
            ->willReturn($hostId);

        $this->readHostTemplateRepository
            ->expects($this->once())
            ->method('findByName')
            ->with($this->templateName)
            ->willReturn($hostTemplate);

        $this->writeHostRepository
            ->expects($this->once())
            ->method('addParent')
            ->with($hostId, $templateId, 1);

        $this->readHostRepository
            ->expects($this->once())
            ->method('findParents')
            ->with($hostId)
            ->willReturn([['parent_id' => $templateId]]);

        $this->readServiceTemplateRepository
            ->expects($this->once())
            ->method('findByHostId')
            ->with($templateId)
            ->willReturn([$serviceTemplate1, $serviceTemplate2]);

        $serviceNames = new ServiceNamesByHost($hostId, []);
        $this->readServiceRepository
            ->expects($this->exactly(2))
            ->method('findServiceNamesByHost')
            ->with($hostId)
            ->willReturn($serviceNames);

        $this->writeServiceRepository
            ->expects($this->exactly(2))
            ->method('add')
            ->willReturnOnConsecutiveCalls($serviceId1, $serviceId2);

        $this->readServiceRepository
            ->expects($this->exactly(2))
            ->method('findById')
            ->willReturnOnConsecutiveCalls($service1, $service2);

        $result = ($this->useCase)($this->request);

        expect($result)->toBe([
            'success' => true,
            'message' => 'Host creation process completed successfully.',
            'details' => 'Host ID: 10, Service IDs: [100, 101]',
        ]);
    }
);

it(
    'should create host without template when template is not found',
    function (): void {
        $hostId = 10;

        $configData = $this->configurationParameters->getData();
        $configData['create_host_auto'] = true;
        $configWithAutoCreateEnabled = $this->createMock(ConfigurationParametersInterface::class);
        $configWithAutoCreateEnabled
            ->method('getData')
            ->willReturn($configData);

        $agentConfigWithAutoCreateEnabled = new AgentConfiguration(
            id: 1,
            name: 'test-ac',
            type: Type::CMA,
            connectionMode: ConnectionModeEnum::SECURE,
            configuration: $configWithAutoCreateEnabled,
        );

        $this->readAgentConfigurationRepository
            ->expects($this->once())
            ->method('findByPollerId')
            ->willReturn($agentConfigWithAutoCreateEnabled);

        $this->readHostRepository
            ->expects($this->once())
            ->method('existsByName')
            ->willReturn(false);

        $this->writeHostRepository
            ->expects($this->once())
            ->method('add')
            ->willReturn($hostId);

        $this->readHostTemplateRepository
            ->expects($this->once())
            ->method('findByName')
            ->with($this->templateName)
            ->willReturn(null);

        $this->writeHostRepository
            ->expects($this->never())
            ->method('addParent');

        $this->readHostRepository
            ->expects($this->once())
            ->method('findParents')
            ->with($hostId)
            ->willReturn([]);

        $result = ($this->useCase)($this->request);

        expect($result)->toBe([
            'success' => true,
            'message' => 'Host creation process completed successfully.',
            'details' => 'Host ID: 10, Service IDs: []',
        ]);
    }
);

it(
    'should not create services when host has no parents',
    function (): void {
        $hostId = 10;
        $templateId = 5;

        $configData = $this->configurationParameters->getData();
        $configData['create_host_auto'] = true;
        $configWithAutoCreateEnabled = $this->createMock(ConfigurationParametersInterface::class);
        $configWithAutoCreateEnabled
            ->method('getData')
            ->willReturn($configData);

        $agentConfigWithAutoCreateEnabled = new AgentConfiguration(
            id: 1,
            name: 'test-ac',
            type: Type::CMA,
            connectionMode: ConnectionModeEnum::SECURE,
            configuration: $configWithAutoCreateEnabled,
        );

        $hostTemplate = new HostTemplate(
            id: $templateId,
            name: $this->templateName,
            alias: 'Generic Host Template',
        );

        $this->readAgentConfigurationRepository
            ->expects($this->once())
            ->method('findByPollerId')
            ->willReturn($agentConfigWithAutoCreateEnabled);

        $this->readHostRepository
            ->expects($this->once())
            ->method('existsByName')
            ->willReturn(false);

        $this->writeHostRepository
            ->expects($this->once())
            ->method('add')
            ->willReturn($hostId);

        $this->readHostTemplateRepository
            ->expects($this->once())
            ->method('findByName')
            ->willReturn($hostTemplate);

        $this->writeHostRepository
            ->expects($this->once())
            ->method('addParent');

        $this->readHostRepository
            ->expects($this->once())
            ->method('findParents')
            ->with($hostId)
            ->willReturn([]);

        $this->readServiceTemplateRepository
            ->expects($this->never())
            ->method('findByHostId');

        $this->writeServiceRepository
            ->expects($this->never())
            ->method('add');

        $result = ($this->useCase)($this->request);

        expect($result)->toBe([
            'success' => true,
            'message' => 'Host creation process completed successfully.',
            'details' => 'Host ID: 10, Service IDs: []',
        ]);
    }
);

it(
    'should not create duplicate services when service already exists on host',
    function (): void {
        $hostId = 10;
        $templateId = 5;
        $serviceId = 100;

        $configData = $this->configurationParameters->getData();
        $configData['create_host_auto'] = true;
        $configWithAutoCreateEnabled = $this->createMock(ConfigurationParametersInterface::class);
        $configWithAutoCreateEnabled
            ->method('getData')
            ->willReturn($configData);

        $agentConfigWithAutoCreateEnabled = new AgentConfiguration(
            id: 1,
            name: 'test-ac',
            type: Type::CMA,
            connectionMode: ConnectionModeEnum::SECURE,
            configuration: $configWithAutoCreateEnabled,
        );

        $hostTemplate = new HostTemplate(
            id: $templateId,
            name: $this->templateName,
            alias: 'Generic Host Template',
        );

        $serviceTemplate = new ServiceTemplate(
            id: 50,
            name: 'service-template',
            alias: 'Existing Service',
        );

        $this->readAgentConfigurationRepository
            ->expects($this->once())
            ->method('findByPollerId')
            ->willReturn($agentConfigWithAutoCreateEnabled);

        $this->readHostRepository
            ->expects($this->once())
            ->method('existsByName')
            ->willReturn(false);

        $this->writeHostRepository
            ->expects($this->once())
            ->method('add')
            ->willReturn($hostId);

        $this->readHostTemplateRepository
            ->expects($this->once())
            ->method('findByName')
            ->willReturn($hostTemplate);

        $this->writeHostRepository
            ->expects($this->once())
            ->method('addParent');

        $this->readHostRepository
            ->expects($this->once())
            ->method('findParents')
            ->willReturn([['parent_id' => $templateId]]);

        $this->readServiceTemplateRepository
            ->expects($this->once())
            ->method('findByHostId')
            ->willReturn([$serviceTemplate]);

        $serviceNames = new ServiceNamesByHost($hostId, ['Existing Service']);
        $this->readServiceRepository
            ->expects($this->once())
            ->method('findServiceNamesByHost')
            ->willReturn($serviceNames);

        $this->writeServiceRepository
            ->expects($this->never())
            ->method('add');

        $result = ($this->useCase)($this->request);

        expect($result)->toBe([
            'success' => true,
            'message' => 'Host creation process completed successfully.',
            'details' => 'Host ID: 10, Service IDs: []',
        ]);
    }
);

it(
    'should return FAILURE when an unexpected exception occurs',
    function (): void {
        $this->readAgentConfigurationRepository
            ->expects($this->once())
            ->method('findByPollerId')
            ->willThrowException(new \Exception('Database error'));

        expect(fn () => ($this->useCase)($this->request))
            ->toThrow(\Exception::class, 'Database error');
    }
);
