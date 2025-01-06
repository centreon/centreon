<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

namespace Tests\Core\AgentConfiguration\Application\UseCase\UpdateAgentConfiguration\Validation;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\AgentConfiguration\Application\Exception\AgentConfigurationException;
use Core\AgentConfiguration\Application\Repository\ReadAgentConfigurationRepositoryInterface;
use Core\AgentConfiguration\Application\UseCase\UpdateAgentConfiguration\UpdateAgentConfigurationRequest;
use Core\AgentConfiguration\Application\UseCase\UpdateAgentConfiguration\Validator;
use Core\AgentConfiguration\Domain\Model\AgentConfiguration;
use Core\AgentConfiguration\Domain\Model\ConfigurationParametersInterface;
use Core\AgentConfiguration\Domain\Model\Poller;
use Core\AgentConfiguration\Domain\Model\Type;
use Core\MonitoringServer\Application\Repository\ReadMonitoringServerRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

beforeEach(function (): void {
    $this->validator = new Validator(
        readAcRepository: $this->readAcRepository = $this->createMock(ReadAgentConfigurationRepositoryInterface::class),
        user: $this->user = $this->createMock(ContactInterface::class),
        readAccessGroupRepository: $this->readAccessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class),
        readMonitoringServerRepository: $this->readMonitoringServerRepository = $this->createMock(ReadMonitoringServerRepositoryInterface::class),
        parametersValidators: new \ArrayIterator([]),
    );

    $this->agentConfiguration = new AgentConfiguration(
        id: 1,
        name: 'my-ac',
        type: Type::TELEGRAF,
        configuration: $this->createMock(ConfigurationParametersInterface::class)
    );

    $this->request = new UpdateAgentConfigurationRequest();
    $this->request->name = 'my-AC';
    $this->request->type = 'telegraf';
    $this->request->pollerIds = [1];
    $this->request->configuration = [];

    $this->poller = new Poller(1, 'poller-name');
    $this->pollerBis = new Poller(2, 'poller-name-bis');
});

it('should throw an exception when the name is invalid', function (): void {
    $this->readAcRepository
        ->expects($this->once())
        ->method('existsByName')
        ->willReturn(true);

    $this->validator->validateNameOrFail($this->request, $this->agentConfiguration);
})->throws(AgentConfigurationException::nameAlreadyExists('my-AC')->getMessage());

it('should throw an exception when the poller list is empty', function (): void {
    $this->request->pollerIds = [];
    $this->validator->validatePollersOrFail($this->request, $this->agentConfiguration);
})->throws(AgentConfigurationException::arrayCanNotBeEmpty('pollerIds')->getMessage());

it('should throw an exception when the type is changed', function (): void {
    $this->request->type = 'centreon-agent';
    $this->validator->validateTypeOrFail($this->request, $this->agentConfiguration);
})->throws(AgentConfigurationException::typeChangeNotAllowed()->getMessage());

it('should throw an exception when a poller ID does not exist', function (): void {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->readMonitoringServerRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(false);

    $this->validator->validatePollersOrFail($this->request, $this->agentConfiguration);
})->throws(AgentConfigurationException::idsDoNotExist('pollerIds', [1])->getMessage());

it('should throw an exception when the object is already associated to one of the pollers', function (): void {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->readMonitoringServerRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(true);

    $this->readAcRepository
        ->expects($this->any())
        ->method('findPollersByAcId')
        ->willReturn([$this->pollerBis]);

    $this->readAcRepository
        ->expects($this->atMost(2))
        ->method('findPollersByType')
        ->willReturnMap(
            [
                [Type::TELEGRAF, [$this->poller]],
                [Type::CMA, []],
            ]
        );

    $this->validator->validatePollersOrFail($this->request, $this->agentConfiguration);
})->throws(AgentConfigurationException::alreadyAssociatedPollers([1])->getMessage());
