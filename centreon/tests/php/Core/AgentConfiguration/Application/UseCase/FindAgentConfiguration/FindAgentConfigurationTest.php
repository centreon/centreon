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

namespace Tests\Core\AgentConfiguration\Application\UseCase\FindAgentConfiguration;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\AgentConfiguration\Domain\Model\Type;
use Core\AgentConfiguration\Application\Exception\AgentConfigurationException;
use Core\AgentConfiguration\Application\Repository\ReadAgentConfigurationRepositoryInterface;
use Core\AgentConfiguration\Application\UseCase\FindAgentConfiguration\AgentConfigurationDto;
use Core\AgentConfiguration\Application\UseCase\FindAgentConfiguration\FindAgentConfiguration;
use Core\AgentConfiguration\Application\UseCase\FindAgentConfiguration\FindAgentConfigurationRequest;
use Core\AgentConfiguration\Application\UseCase\FindAgentConfiguration\FindAgentConfigurationResponse;
use Core\AgentConfiguration\Application\UseCase\FindAgentConfiguration\PollerDto;
use Core\AgentConfiguration\Domain\Model\AgentConfiguration;
use Core\AgentConfiguration\Domain\Model\ConfigurationParameters\TelegrafConfigurationParameters;
use Core\AgentConfiguration\Domain\Model\Poller;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\MonitoringServer\Application\Repository\ReadMonitoringServerRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;
use Respect\Validation\Rules\No;

beforeEach(function (): void {
    $this->presenter = new FindAgentConfigurationPresenterStub();
    $this->useCase = new FindAgentConfiguration(
        user: $this->user = $this->createMock(ContactInterface::class),
        readRepository: $this->readRepository = $this->createMock(ReadAgentConfigurationRepositoryInterface::class),
        readMonitoringServerRepository: $this->readMonitoringServerRepository = $this->createMock(ReadMonitoringServerRepositoryInterface::class),
        readAccessGroupRepository: $this->readAccessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class),
    );
});

it('should present a Forbidden Response when user does not have topology role', function () {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(false);

    $request = new FindAgentConfigurationRequest();
    $request->agentConfigurationId = 1;

    ($this->useCase)($request, $this->presenter);

    expect($this->presenter->data)
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->presenter->data->getMessage())
        ->toBe(AgentConfigurationException::accessNotAllowed()->getMessage());
});

it('should present a Not Found Response when Agent Configuration does not exist', function () {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $this->readRepository
        ->expects($this->once())
        ->method('find')
        ->willReturn(null);

    $request = new FindAgentConfigurationRequest();
    $request->agentConfigurationId = 1;

    ($this->useCase)($request, $this->presenter);

    expect($this->presenter->data)
        ->toBeInstanceOf(NotFoundResponse::class)
        ->and($this->presenter->data->getMessage())
        ->toBe('Agent Configuration not found');
});

it('should present a Not Found Response when AC got pollers not accessible by the user', function () {
    $configuration = new TelegrafConfigurationParameters([
        'otel_server_address' => '10.10.10.10',
        'otel_server_port' => 453,
        'otel_public_certificate' => 'public_certif',
        'otel_ca_certificate' => 'ca_certif',
        'otel_private_key' => 'otel-key',
        'conf_server_port' => 454,
        'conf_certificate' => 'conf-certif',
        'conf_private_key' => 'conf-key'
    ]);

    $pollers = [
        new Poller(2, 'pollerOne'),
        new Poller(2, 'pollerTwo')
    ];

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
        ->method('find')
        ->willReturn(new AgentConfiguration(1, 'acOne', Type::TELEGRAF, $configuration));

    $this->readRepository
        ->expects($this->once())
        ->method('findPollersByAcId')
        ->willReturn($pollers);

    $this->readMonitoringServerRepository
        ->expects($this->once())
        ->method('existByAccessGroups')
        ->willReturn([1]);

    $request = new FindAgentConfigurationRequest();
    $request->agentConfigurationId = 1;

    ($this->useCase)($request, $this->presenter);

    expect($this->presenter->data)
        ->toBeInstanceOf(NotFoundResponse::class)
        ->and($this->presenter->data->getMessage())
        ->toBe('Agent Configuration not found');
});

it('should present an Error Response when an unexpected error occurs', function () {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $this->readRepository
        ->expects($this->once())
        ->method('find')
        ->willThrowException(new \Exception());

    $request = new FindAgentConfigurationRequest();
    $request->agentConfigurationId = 1;

    ($this->useCase)($request, $this->presenter);

    expect($this->presenter->data)
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->data->getMessage())
        ->toBe(AgentConfigurationException::errorWhileRetrievingObject()->getMessage());
});

it('should present a FindConfigurationResponse when everything is ok', function () {
    $configuration = new TelegrafConfigurationParameters([
        'otel_server_address' => '10.10.10.10',
        'otel_server_port' => 453,
        'otel_public_certificate' => 'public_certif',
        'otel_ca_certificate' => 'ca_certif',
        'otel_private_key' => 'otel-key',
        'conf_server_port' => 454,
        'conf_certificate' => 'conf-certif',
        'conf_private_key' => 'conf-key'
    ]);

    $pollers = [
        new Poller(1, 'pollerOne'),
        new Poller(2, 'pollerTwo')
    ];

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
        ->method('find')
        ->willReturn(new AgentConfiguration(1, 'acOne', Type::TELEGRAF, $configuration));

    $this->readRepository
        ->expects($this->once())
        ->method('findPollersByAcId')
        ->willReturn($pollers);

    $request = new FindAgentConfigurationRequest();
    $request->agentConfigurationId = 1;

    ($this->useCase)($request, $this->presenter);

    $pollerDtoOne = new PollerDto();
    $pollerDtoOne->id = 1;
    $pollerDtoOne->name = 'pollerOne';
    $pollerDtoTwo = new PollerDto();
    $pollerDtoTwo->id = 2;
    $pollerDtoTwo->name = 'pollerTwo';

    $response = $this->presenter->data;
    expect($response)
        ->toBeInstanceOf(FindAgentConfigurationResponse::class)
        ->and($response->id)->toBe(1)
        ->and($response->name)->toBe('acOne')
        ->and($response->type)->toBe(Type::TELEGRAF)
        ->and($response->configuration)->toBe([
            'otel_server_address' => '10.10.10.10',
            'otel_server_port' => 453,
            'otel_public_certificate' => 'public_certif',
            'otel_ca_certificate' => 'ca_certif',
            'otel_private_key' => 'otel-key',
            'conf_server_port' => 454,
            'conf_certificate' => 'conf-certif',
            'conf_private_key' => 'conf-key'
        ])
        ->and($response->pollers[0]->id)->toBe(1)
        ->and($response->pollers[0]->name)->toBe('pollerOne')
        ->and($response->pollers[1]->id)->toBe(2)
        ->and($response->pollers[1]->name)->toBe('pollerTwo');
});
