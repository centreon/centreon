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

use Centreon\Domain\Common\Assertion\AssertionException;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\AgentConfiguration\Application\Exception\AgentConfigurationException;
use Core\AgentConfiguration\Application\Repository\ReadAgentConfigurationRepositoryInterface;
use Core\AgentConfiguration\Application\Repository\WriteAgentConfigurationRepositoryInterface;
use Core\AgentConfiguration\Application\UseCase\AddAgentConfiguration\AddAgentConfiguration;
use Core\AgentConfiguration\Application\UseCase\AddAgentConfiguration\AddAgentConfigurationRequest;
use Core\AgentConfiguration\Application\UseCase\AddAgentConfiguration\AddAgentConfigurationResponse;
use Core\AgentConfiguration\Application\UseCase\AddAgentConfiguration\Validator;
use Core\AgentConfiguration\Domain\Model\AgentConfiguration;
use Core\AgentConfiguration\Domain\Model\ConfigurationParametersInterface;
use Core\AgentConfiguration\Domain\Model\NewAgentConfiguration;
use Core\AgentConfiguration\Domain\Model\Poller;
use Core\AgentConfiguration\Domain\Model\Type;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;

beforeEach(function (): void {
    $this->presenter = new AddAgentConfigurationPresenterStub();
    $this->useCase = new AddAgentConfiguration(
        readAcRepository: $this->readAgentConfigurationRepository = $this->createMock(ReadAgentConfigurationRepositoryInterface::class),
        writeAcRepository: $this->writeAgentConfigurationRepository = $this->createMock(WriteAgentConfigurationRepositoryInterface::class),
        validator: $this->validator = $this->createMock(Validator::class),
        dataStorageEngine: $this->dataStorageEngine = $this->createMock(DataStorageEngineInterface::class),
        user: $this->user = $this->createMock(ContactInterface::class),
    );

    $this->testedAddRequest = new AddAgentConfigurationRequest();
    $this->testedAddRequest->name = 'added-ac';
    $this->testedAddRequest->type = 'telegraf';
    $this->testedAddRequest->pollerIds = [1];
    $this->testedAddRequest->configuration = [
        'otel_server_address' => '10.10.10.10',
        'otel_server_port' => 453,
        'conf_server_port' => 454,
        'otel_public_certificate' => 'public_certif',
        'otel_ca_certificate' => 'ca_certif',
        'otel_private_key' => 'otel-key',
        'conf_certificate' => 'conf-certif',
        'conf_private_key' => 'conf-key',
    ];

    $this->poller = new Poller(1, 'poller-name');

    $this->testedNewAc = new NewAgentConfiguration(
        name: $this->testedAcName = 'ac-name',
        type: Type::TELEGRAF,
        configuration: $this->createMock(ConfigurationParametersInterface::class),
    );

    $this->testedAc = new AgentConfiguration(
        id: $this->testedAcId = 1,
        name: $this->testedAcName = 'ac-name',
        type: Type::TELEGRAF,
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

        ($this->useCase)($this->testedAddRequest, $this->presenter);

        expect($this->presenter->data)
            ->toBeInstanceOf(ForbiddenResponse::class)
            ->and($this->presenter->data->getMessage())
            ->toBe(AgentConfigurationException::accessNotAllowed()->getMessage());
    }
);

it(
    'should present an ErrorResponse when an AgentConfigurationException is thrown',
    function (): void {
        $this->user
            ->expects($this->once())
            ->method('hasTopologyRole')
            ->willReturn(true);

        $this->validator
            ->expects($this->once())
            ->method('validateRequestOrFail')
            ->willThrowException(AgentConfigurationException::nameAlreadyExists('invalid-name'));

        ($this->useCase)($this->testedAddRequest, $this->presenter);

        expect($this->presenter->data)
            ->toBeInstanceOf(ErrorResponse::class)
            ->and($this->presenter->data->getMessage())
            ->toBe(AgentConfigurationException::nameAlreadyExists('invalid-name')->getMessage());
    }
);

it(
    'should present an ErrorResponse when a generic exception is thrown',
    function (): void {
        $this->user
            ->expects($this->once())
            ->method('hasTopologyRole')
            ->willReturn(true);

        $this->validator
            ->expects($this->once())
            ->method('validateRequestOrFail');

        $this->writeAgentConfigurationRepository
            ->expects($this->once())
            ->method('add')
            ->willThrowException(new \Exception());

        ($this->useCase)($this->testedAddRequest, $this->presenter);

        expect($this->presenter->data)
            ->toBeInstanceOf(ErrorResponse::class)
            ->and($this->presenter->data->getMessage())
            ->toBe(AgentConfigurationException::addAc()->getMessage());
    }
);

it(
    'should present an InvalidArgumentResponse when a field value is not valid',
    function (): void {
        $this->testedAddRequest->name = '';

        $this->user
            ->expects($this->once())
            ->method('hasTopologyRole')
            ->willReturn(true);

        $this->validator
            ->expects($this->once())
            ->method('validateRequestOrFail');

        $expectedException = AssertionException::notEmptyString('NewAgentConfiguration::name');

        ($this->useCase)($this->testedAddRequest, $this->presenter);

        expect($this->presenter->data)
            ->toBeInstanceOf(InvalidArgumentResponse::class)
            ->and($this->presenter->data->getMessage())
            ->toBe($expectedException->getMessage());
    }
);

it(
    'should present an ErrorResponse if the newly created object cannot be retrieved',
    function (): void {
        $this->user
            ->expects($this->once())
            ->method('hasTopologyRole')
            ->willReturn(true);

        $this->validator
            ->expects($this->once())
            ->method('validateRequestOrFail');

        $this->writeAgentConfigurationRepository
            ->expects($this->once())
            ->method('add')
            ->willReturn($this->testedAcId);

        $this->readAgentConfigurationRepository
            ->expects($this->once())
            ->method('find')
            ->willReturn(null);

        ($this->useCase)($this->testedAddRequest, $this->presenter);

        expect($this->presenter->data)
            ->toBeInstanceOf(ErrorResponse::class)
            ->and($this->presenter->data->getMessage())
            ->toBe(AgentConfigurationException::errorWhileRetrievingObject()->getMessage());
    }
);

it(
    'should present an AddAgentConfigurationResponse when no error occurs',
    function (): void {
        $this->user
            ->expects($this->once())
            ->method('hasTopologyRole')
            ->willReturn(true);

        $this->validator
            ->expects($this->once())
            ->method('validateRequestOrFail');

        $this->writeAgentConfigurationRepository
            ->expects($this->once())
            ->method('add')
            ->willReturn($this->testedAcId);

        $this->readAgentConfigurationRepository
            ->expects($this->once())
            ->method('findPollersWithBrokerDirective')
            ->willReturn([$this->poller->id]);

        $this->readAgentConfigurationRepository
            ->expects($this->once())
            ->method('find')
            ->willReturn($this->testedAc);

        $this->readAgentConfigurationRepository
            ->expects($this->once())
            ->method('findPollersByAcId')
            ->willReturn([$this->poller]);

        ($this->useCase)($this->testedAddRequest, $this->presenter);

        /** @var AddAgentConfigurationResponse $agentConfiguration */
        $agentConfiguration = $this->presenter->data;

        expect($agentConfiguration)->toBeInstanceOf(AddAgentConfigurationResponse::class)
            ->and($agentConfiguration->id)->toBe($this->testedAcId)
            ->and($agentConfiguration->name)->toBe($this->testedAcName);
    }
);
