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

namespace Tests\Core\HostCategory\Application\UseCase\AddHostCategory;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Broker\Application\Exception\BrokerException;
use Core\Broker\Application\Repository\ReadBrokerInputOutputRepositoryInterface;
use Core\Broker\Application\Repository\WriteBrokerInputOutputRepositoryInterface;
use Core\Broker\Application\UseCase\AddBrokerInputOutput\AddBrokerInputOutput;
use Core\Broker\Application\UseCase\AddBrokerInputOutput\AddBrokerInputOutputRequest;
use Core\Broker\Application\UseCase\AddBrokerInputOutput\AddBrokerInputOutputResponse;
use Core\Broker\Application\UseCase\AddBrokerInputOutput\BrokerInputOutputValidator;
use Core\Broker\Domain\Model\BrokerInputOutput;
use Core\Broker\Domain\Model\BrokerInputOutputField;
use Core\Broker\Domain\Model\Type;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Tests\Core\Broker\Infrastructure\API\AddBrokerInputOutput\AddBrokerInputOutputPresenterStub;

beforeEach(function (): void {
    $this->request = new AddBrokerInputOutputRequest();
    $this->request->brokerId = 1;
    $this->request->name = 'my-output-test';
    $this->request->type = 33;
    $this->request->parameters = [
        'path' => 'some/path/file',
    ];

    $this->type = new Type(33, 'lua');
    $this->output = new BrokerInputOutput(
        id: 1,
        tag: 'output',
        type: $this->type,
        name: $this->request->name,
        parameters: $this->request->parameters
    );
    $this->outputFields = [
        $this->field = new BrokerInputOutputField(
            1, 'path', 'text', null, null, true, false, null, []
        ),
    ];

    $this->presenter = new AddBrokerInputOutputPresenterStub(
        $this->presenterFormatter = $this->createMock(PresenterFormatterInterface::class)
    );

    $this->useCase = new AddBrokerInputOutput(
        $this->writeOutputRepository = $this->createMock(WriteBrokerInputOutputRepositoryInterface::class),
        $this->readOutputRepository = $this->createMock(ReadBrokerInputOutputRepositoryInterface::class),
        $this->user = $this->createMock(ContactInterface::class),
        $this->validator = $this->createMock(BrokerInputOutputValidator::class),
    );
});

it('should present a ForbiddenResponse when a user has insufficient rights', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(false);

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(BrokerException::editNotAllowed()->getMessage());
});

it('should present an ErrorResponse when an exception is thrown', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $this->validator
        ->expects($this->once())
        ->method('brokerIsValidOrFail')
        ->willThrowException(BrokerException::notFound($this->request->brokerId));

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(BrokerException::notFound($this->request->brokerId)->getMessage());
});

it('should present an ErrorResponse when a generic exception is thrown', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $this->validator
        ->expects($this->once())
        ->method('brokerIsValidOrFail');

    $this->readOutputRepository
        ->expects($this->once())
        ->method('findType')
        ->willThrowException(new \Exception());

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(BrokerException::addBrokerInputOutput()->getMessage());
});

it('should present an ErrorResponse if the newly created output cannot be retrieved', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $this->validator
        ->expects($this->once())
        ->method('brokerIsValidOrFail');

    $this->readOutputRepository
        ->expects($this->once())
        ->method('findType')
        ->willReturn($this->type);
    $this->readOutputRepository
        ->expects($this->once())
        ->method('findParametersByType')
        ->willReturn($this->outputFields);

    $this->validator
        ->expects($this->once())
        ->method('validateParameters');

    $this->writeOutputRepository
        ->expects($this->once())
        ->method('add')
        ->willReturn($this->output->getId());

    $this->readOutputRepository
        ->expects($this->once())
        ->method('findByIdAndBrokerId')
        ->willReturn(null);

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(BrokerException::inputOutputNotFound($this->request->brokerId, $this->output->getId())->getMessage());
});

it('should return created object on success', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $this->validator
        ->expects($this->once())
        ->method('brokerIsValidOrFail');

    $this->readOutputRepository
        ->expects($this->once())
        ->method('findType')
        ->willReturn($this->type);
    $this->readOutputRepository
        ->expects($this->once())
        ->method('findParametersByType')
        ->willReturn($this->outputFields);

    $this->validator
        ->expects($this->once())
        ->method('validateParameters');

    $this->writeOutputRepository
        ->expects($this->once())
        ->method('add')
        ->willReturn($this->output->getId());

    $this->readOutputRepository
        ->expects($this->once())
        ->method('findByIdAndBrokerId')
        ->willReturn($this->output);

    ($this->useCase)($this->request, $this->presenter);

    $response = $this->presenter->response;
    expect($this->presenter->response)->toBeInstanceOf(AddBrokerInputOutputResponse::class)
        ->and($response->name)
        ->toBe($this->output->getName())
        ->and($response->type->name)
        ->toBe(($this->output->getType())->name)
        ->and($response->parameters)
        ->toBe($this->output->getParameters());
});
