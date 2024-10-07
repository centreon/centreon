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

namespace Tests\Core\AgentConfiguration\Application\UseCase\FindAgentConfigurations;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\AgentConfiguration\Domain\Model\Type;
use Core\AgentConfiguration\Application\Exception\AgentConfigurationException;
use Core\AgentConfiguration\Application\Repository\ReadAgentConfigurationRepositoryInterface;
use Core\AgentConfiguration\Application\UseCase\FindAgentConfigurations\AgentConfigurationDto;
use Core\AgentConfiguration\Application\UseCase\FindAgentConfigurations\FindAgentConfigurations;
use Core\AgentConfiguration\Application\UseCase\FindAgentConfigurations\FindAgentConfigurationsResponse;
use Core\AgentConfiguration\Application\UseCase\FindAgentConfigurations\PollerDto;
use Core\AgentConfiguration\Domain\Model\AgentConfiguration;
use Core\AgentConfiguration\Domain\Model\ConfigurationParameters\TelegrafConfigurationParameters;
use Core\AgentConfiguration\Domain\Model\Poller;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

beforeEach(function (): void {
    $this->presenter = new FindAgentConfigurationsPresenterStub();
    $this->useCase = new FindAgentConfigurations(
        user: $this->user = $this->createMock(ContactInterface::class),
        readRepository: $this->readRepository = $this->createMock(ReadAgentConfigurationRepositoryInterface::class),
        requestParameters: $this->requestParameters = $this->createMock(RequestParametersInterface::class),
        readAccessGroupRepository: $this->readAccessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class),
    );
});

it('should present a Forbidden Response when user does not have topology role', function () {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(false);

    ($this->useCase)($this->presenter);

    expect($this->presenter->data)
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->presenter->data->getMessage())
        ->toBe(AgentConfigurationException::accessNotAllowed()->getMessage());
});

it('should retrieve agent configurations without calculating ACL for an admin', function () {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->readRepository
        ->expects($this->once())
        ->method('findAllByRequestParameters');

    ($this->useCase)($this->presenter);
});

it('should retrieve agent configurations without calculating ACL for a non admin', function () {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(false);

    $this->readRepository
        ->expects($this->once())
        ->method('findAllByRequestParametersAndAccessGroups');

    ($this->useCase)($this->presenter);
});

it('should present an ErrorResponse when a generic exception is thrown', function () {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(false);

    $this->readRepository
        ->expects($this->once())
        ->method('findAllByRequestParametersAndAccessGroups')
        ->willThrowException(new \Exception());

    ($this->useCase)($this->presenter);

    expect($this->presenter->data)
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->data->getMessage())
        ->toBe(AgentConfigurationException::errorWhileRetrievingObjects()->getMessage());
});

it('should present a FindAgentConfigurationsResponse when no errors occurred', function () {
    $acOne = new AgentConfiguration(
        id: 1,
        name: 'acOne',
        type: Type::TELEGRAF,
        configuration: new TelegrafConfigurationParameters([
            'otel_server_address' => '10.10.10.10',
            'otel_server_port' => 453,
            'otel_public_certificate' => 'public_certif',
            'otel_ca_certificate' => 'ca_certif',
            'otel_private_key' => 'otel-key',
            'conf_server_port' => 454,
            'conf_certificate' => 'conf-certif',
            'conf_private_key' => 'conf-key'
        ])
    );

    $acTwo = new AgentConfiguration(
        id: 2,
        name: 'acTwo',
        type: Type::TELEGRAF,
        configuration: new TelegrafConfigurationParameters([
            'otel_server_address' => '10.10.10.11',
            'otel_server_port' => 453,
            'otel_public_certificate' => 'public_certif',
            'otel_ca_certificate' => 'ca_certif',
            'otel_private_key' => 'otel-key',
            'conf_server_port' => 454,
            'conf_certificate' => 'conf-certif',
            'conf_private_key' => 'conf-key'
        ])
    );

    $pollerOne = new Poller(1, 'poller_1');
    $pollerTwo = new Poller(2, 'poller_2');

    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(false);

    $this->readRepository
        ->expects($this->once())
        ->method('findAllByRequestParametersAndAccessGroups')
        ->willReturn([$acOne, $acTwo]);

    $this->readAccessGroupRepository
        ->expects($this->once())
        ->method('findByContact')
        ->willReturn([new AccessGroup(1, 'customer_non_admin_acl', 'not an admin')]);

    $this->readRepository
        ->expects($this->any())
        ->method('findPollersByAcId')
        ->willReturn([$pollerOne, $pollerTwo]);

    ($this->useCase)($this->presenter);

    expect($this->presenter->data)
        ->toBeInstanceOf(FindAgentConfigurationsResponse::class)
        ->and($this->presenter->data->agentConfigurations)
        ->toBeArray()
        ->and($this->presenter->data->agentConfigurations[0])
        ->toBeInstanceOf(AgentConfigurationDto::class)
        ->and($this->presenter->data->agentConfigurations[0]->id)
        ->toBe($acOne->getId())
        ->and($this->presenter->data->agentConfigurations[0]->name)
        ->toBe($acOne->getName())
        ->and($this->presenter->data->agentConfigurations[0]->type)
        ->toBe($acOne->getType())
        ->and($this->presenter->data->agentConfigurations[0]->pollers)
        ->toBeArray()
        ->and($this->presenter->data->agentConfigurations[0]->pollers[0])
        ->toBeInstanceOf(PollerDto::class)
        ->and($this->presenter->data->agentConfigurations[0]->pollers[0]->id)
        ->toBe($pollerOne->getId())
        ->and($this->presenter->data->agentConfigurations[0]->pollers[0]->name)
        ->toBe($pollerOne->getName())
        ->and($this->presenter->data->agentConfigurations[0]->pollers[1])
        ->toBeInstanceOf(PollerDto::class)
        ->and($this->presenter->data->agentConfigurations[0]->pollers[1]->id)
        ->toBe($pollerTwo->getId())
        ->and($this->presenter->data->agentConfigurations[0]->pollers[1]->name)
        ->toBe($pollerTwo->getName())
        ->and($this->presenter->data->agentConfigurations[1])
        ->toBeInstanceOf(AgentConfigurationDto::class)
        ->and($this->presenter->data->agentConfigurations[1]->id)
        ->toBe($acTwo->getId())
        ->and($this->presenter->data->agentConfigurations[1]->name)
        ->toBe($acTwo->getName())
        ->and($this->presenter->data->agentConfigurations[1]->type)
        ->toBe($acTwo->getType())
        ->and($this->presenter->data->agentConfigurations[1]->pollers)
        ->toBeArray()
        ->and($this->presenter->data->agentConfigurations[1]->pollers[0])
        ->toBeInstanceOf(PollerDto::class)
        ->and($this->presenter->data->agentConfigurations[1]->pollers[0]->id)
        ->toBe($pollerOne->getId())
        ->and($this->presenter->data->agentConfigurations[1]->pollers[0]->name)
        ->toBe($pollerOne->getName())
        ->and($this->presenter->data->agentConfigurations[1]->pollers[1])
        ->toBeInstanceOf(PollerDto::class)
        ->and($this->presenter->data->agentConfigurations[1]->pollers[1]->id)
        ->toBe($pollerTwo->getId())
        ->and($this->presenter->data->agentConfigurations[1]->pollers[1]->name)
        ->toBe($pollerTwo->getName());
});
