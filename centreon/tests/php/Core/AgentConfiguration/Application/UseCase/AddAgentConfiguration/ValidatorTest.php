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

namespace Tests\Core\AgentConfiguration\Application\UseCase\AddAgentConfiguration;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\AgentConfiguration\Application\Exception\AgentConfigurationException;
use Core\AgentConfiguration\Application\Repository\ReadAgentConfigurationRepositoryInterface;
use Core\AgentConfiguration\Application\UseCase\AddAgentConfiguration\AddAgentConfigurationRequest;
use Core\AgentConfiguration\Application\UseCase\AddAgentConfiguration\Validator;
use Core\AgentConfiguration\Domain\Model\Poller;
use Core\AgentConfiguration\Domain\Model\Type;
use Core\MonitoringServer\Application\Repository\ReadMonitoringServerRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use ValueError;

beforeEach(function (): void {
    $this->validator = new Validator(
        readAcRepository: $this->readAgentConfigurationRepository = $this->createMock(ReadAgentConfigurationRepositoryInterface::class),
        user: $this->user = $this->createMock(ContactInterface::class),
        readAccessGroupRepository: $this->readAccessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class),
        readMonitoringServerRepository: $this->readMonitoringServerRepository = $this->createMock(ReadMonitoringServerRepositoryInterface::class),
        parametersValidators: new \ArrayIterator([]),
    );

    $this->request = new AddAgentConfigurationRequest();
    $this->request->name = 'my-AC';
    $this->request->type = 'telegraf';
    $this->request->pollerIds = [1];
    $this->request->configuration = [];

    $this->poller = new Poller(1, 'poller-name');
});

it('should throw an exception when the name is invalid', function (): void {
    $this->readAgentConfigurationRepository
        ->expects($this->once())
        ->method('existsByName')
        ->willReturn(true);

    $this->validator->validateNameOrFail($this->request);
})->throws(AgentConfigurationException::nameAlreadyExists('my-AC')->getMessage());

it('should throw an exception when the pollers list is empty', function (): void {
    $this->request->pollerIds = [];
    $this->validator->validatePollersOrFail($this->request);
})->throws(AgentConfigurationException::arrayCanNotBeEmpty('pollerIds')->getMessage());

it('should throw an exception when a poller ID does not exist', function (): void {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->readMonitoringServerRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(false);

    $this->validator->validatePollersOrFail($this->request);
})->throws(AgentConfigurationException::idsDoNotExist('pollerIds', [1])->getMessage());

it('should throw an exception when the type is not valid', function (): void {
    $this->request->type = '';
    $this->validator->validateTypeOrFail($this->request);
})->throws((new ValueError('"" is not a valid backing value for enum "Core\AgentConfiguration\Domain\Model\Type"'))->getMessage());

it('should throw an exception when the object is already associated to one of the pollers', function (): void {
     $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->readMonitoringServerRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(true);

    $this->readAgentConfigurationRepository
        ->expects($this->atMost(2))
        ->method('findPollersByType')
        ->willReturnMap(
            [
                [Type::TELEGRAF, [$this->poller]],
                [Type::CENTREON_AGENT, []]
            ]
        );

    $this->validator->validatePollersOrFail($this->request);
})->throws(AgentConfigurationException::alreadyAssociatedPollers([1])->getMessage());
