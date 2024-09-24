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

namespace Tests\Core\AgentConfiguration\Application\UseCase\UpdateAgentConfiguration;

use Centreon\Domain\Common\Assertion\AssertionException;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\AgentConfiguration\Application\Exception\AgentConfigurationException;
use Core\AgentConfiguration\Application\Factory\AgentConfigurationFactory;
use Core\AgentConfiguration\Application\Repository\ReadAgentConfigurationRepositoryInterface;
use Core\AgentConfiguration\Application\Repository\WriteAgentConfigurationRepositoryInterface;
use Core\AgentConfiguration\Application\UseCase\UpdateAgentConfiguration\UpdateAgentConfiguration;
use Core\AgentConfiguration\Application\UseCase\UpdateAgentConfiguration\UpdateAgentConfigurationRequest;
use Core\AgentConfiguration\Application\UseCase\UpdateAgentConfiguration\Validator;
use Core\AgentConfiguration\Domain\Model\Acc;
use Core\AgentConfiguration\Domain\Model\AgentConfiguration;
use Core\AgentConfiguration\Domain\Model\ConfigurationParametersInterface;
use Core\AgentConfiguration\Domain\Model\Poller;
use Core\AgentConfiguration\Domain\Model\Type;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Common\Infrastructure\FeatureFlags;
use Core\Infrastructure\Common\Api\DefaultPresenter;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\MonitoringServer\Application\Repository\ReadMonitoringServerRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

beforeEach(function (): void {
    $this->presenterFormatter = $this->createMock(PresenterFormatterInterface::class);
    $this->presenter = new DefaultPresenter($this->presenterFormatter);

    $this->useCase = new UpdateAgentConfiguration(
        readAcRepository: $this->readAgentConfigurationRepository = $this->createMock(ReadAgentConfigurationRepositoryInterface::class),
        writeAcRepository: $this->writeAgentConfigurationRepository = $this->createMock(WriteAgentConfigurationRepositoryInterface::class),
        validator: $this->validator = $this->createMock(Validator::class),
        dataStorageEngine: $this->dataStorageEngine = $this->createMock(DataStorageEngineInterface::class),
        user: $this->user = $this->createMock(ContactInterface::class),
    );

    $this->request = new UpdateAgentConfigurationRequest();
    $this->request->name = 'ac edited';
    $this->request->type = 'telegraf';
    $this->request->pollerIds = [1];
    $this->request->configuration = [
        "otel_server_address" => "10.10.10.10",
  		"otel_server_port" => 453,
  		"conf_server_port" => 454,
  		"otel_public_certificate" => "public_certif",
  		"otel_ca_certificate" => "ca_certif",
  		"otel_private_key" => "otel-key",
  		"conf_certificate" => "conf-certif",
  		"conf_private_key" => "conf-key"
    ];

    $this->poller = new Poller(1, 'poller-name');

    $this->agentConfiguration = new AgentConfiguration(
        id: $this->agentConfigurationId = 1,
        name: $this->agentConfigurationName = 'ac-name',
        type: $this->agentConfigurationType = Type::TELEGRAF,
        configuration: $this->createMock(ConfigurationParametersInterface::class),
    );
});

it(
    'should present a ForbiddenResponse when the user does not have the correct role',
    function (): void {
        $this->user
            ->expects($this->once())
            ->method('hasTopologyRole')
            ->willReturn(false);

        ($this->useCase)($this->request, $this->presenter);

        expect($this->presenter->getResponseStatus())
            ->toBeInstanceOf(ForbiddenResponse::class)
            ->and($this->presenter->getResponseStatus()->getMessage())
            ->toBe(AgentConfigurationException::accessNotAllowed()->getMessage());
    }
);

it(
    'should present an ErrorResponse when a Exception is thrown',
    function (): void {
        $this->user
            ->expects($this->once())
            ->method('hasTopologyRole')
            ->willReturn(true);

        $this->readAgentConfigurationRepository
            ->expects($this->once())
            ->method('find')
            ->willThrowException(new \Exception());

        ($this->useCase)($this->request, $this->presenter);

        expect($this->presenter->getResponseStatus())
            ->toBeInstanceOf(ErrorResponse::class)
            ->and($this->presenter->getResponseStatus()->getMessage())
            ->toBe(AgentConfigurationException::updateAc()->getMessage());
    }
);

it(
    'should present an ErrorResponse when a generic exception is thrown',
    function (): void {
        $this->user
            ->expects($this->once())
            ->method('hasTopologyRole')
            ->willReturn(true);

        $this->readAgentConfigurationRepository
            ->expects($this->once())
            ->method('find')
            ->willReturn($this->agentConfiguration);

        $this->user
            ->expects($this->once())
            ->method('isAdmin')
            ->willReturn(true);

        $this->validator
            ->expects($this->once())
            ->method('validateRequestOrFail');

        $this->writeAgentConfigurationRepository
            ->expects($this->once())
            ->method('update')
            ->willThrowException(new \Exception());

        ($this->useCase)($this->request, $this->presenter);

        expect($this->presenter->getResponseStatus())
            ->toBeInstanceOf(ErrorResponse::class)
            ->and($this->presenter->getResponseStatus()->getMessage())
            ->toBe(AgentConfigurationException::updateAc()->getMessage());
    }
);

it(
    'should present a InvalidArgumentResponse when a field value is not valid',
    function (): void {
        $this->user
            ->expects($this->once())
            ->method('hasTopologyRole')
            ->willReturn(true);

        $this->readAgentConfigurationRepository
            ->expects($this->once())
            ->method('find')
            ->willReturn($this->agentConfiguration);

        $this->user
            ->expects($this->once())
            ->method('isAdmin')
            ->willReturn(true);

        $this->validator
            ->expects($this->once())
            ->method('validateRequestOrFail');

        $this->request->name = '';
        $expectedException = AssertionException::notEmptyString('AgentConfiguration::name');

        $this->factory
            ->expects($this->once())
            ->method('updateAcc')
            ->willThrowException($expectedException);

        ($this->useCase)($this->request, $this->presenter);

        expect($this->presenter->getResponseStatus())
            ->toBeInstanceOf(InvalidArgumentResponse::class)
            ->and($this->presenter->getResponseStatus()->getMessage())
            ->toBe($expectedException->getMessage());
    }
);

it(
    'should present a UpdateAgentConfigurationResponse when no error occurs',
    function (): void {
        $this->user
            ->expects($this->once())
            ->method('hasTopologyRole')
            ->willReturn(true);

        $this->readAgentConfigurationRepository
            ->expects($this->once())
            ->method('find')
            ->willReturn($this->agentConfiguration);

        $this->user
            ->expects($this->any())
            ->method('getId')
            ->willReturn($this->agentConfigurationCreatedBy);

        $this->validator
            ->expects($this->once())
            ->method('validateRequestOrFail');

        $this->writeAgentConfigurationRepository
            ->expects($this->once())
            ->method('update');

        ($this->useCase)($this->request, $this->presenter);

        expect($this->presenter->getResponseStatus())->toBeInstanceOf(NoContentResponse::class);
    }
);
