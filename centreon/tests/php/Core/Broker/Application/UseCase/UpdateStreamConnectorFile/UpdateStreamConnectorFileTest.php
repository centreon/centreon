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
use Core\Broker\Application\Repository\WriteBrokerRepositoryInterface;
use Core\Broker\Application\UseCase\UpdateStreamConnectorFile\UpdateStreamConnectorFile;
use Core\Broker\Application\UseCase\UpdateStreamConnectorFile\UpdateStreamConnectorFileRequest;
use Core\Broker\Application\UseCase\UpdateStreamConnectorFile\UpdateStreamConnectorFileResponse;
use Core\Broker\Domain\Model\BrokerInputOutput;
use Core\Broker\Domain\Model\BrokerInputOutputField;
use Core\Broker\Domain\Model\Type;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Tests\Core\Broker\Infrastructure\API\UpdateStreamConnectorFile\UpdateStreamConnectorFilePresenterStub;

beforeEach(function (): void {
    $this->request = new UpdateStreamConnectorFileRequest();
    $this->request->brokerId = 1;
    $this->request->outputId = 2;
    $this->request->fileContent = '{"test": "hello world"}';

    $this->type = new Type(33, 'lua');
    $this->output = new BrokerInputOutput(
        id: 1,
        tag: 'output',
        type: $this->type,
        name: 'my-output-test',
        parameters: ['path' => 'some/fake/path.json'],
    );
    $this->outputFields = [
        $this->field = new BrokerInputOutputField(
            1, 'path', 'text', null, null, true, false, null, []
        ),
    ];

    $this->presenter = new UpdateStreamConnectorFilePresenterStub(
        $this->presenterFormatter = $this->createMock(PresenterFormatterInterface::class)
    );

    $this->useCase = new UpdateStreamConnectorFile(
        $this->writeOutputRepository = $this->createMock(WriteBrokerInputOutputRepositoryInterface::class),
        $this->readOutputRepository = $this->createMock(ReadBrokerInputOutputRepositoryInterface::class),
        $this->fileRepository = $this->createMock(WriteBrokerRepositoryInterface::class),
        $this->user = $this->createMock(ContactInterface::class),
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

it('should present an ErrorResponse when the output is invalid', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $this->readOutputRepository
        ->expects($this->once())
        ->method('findByIdAndBrokerId')
        ->willReturn(null);

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(BrokerException::inputOutputNotFound($this->request->brokerId, $this->request->outputId)->getMessage());
});

it('should present an ErrorResponse if the file content is invalid', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $this->readOutputRepository
        ->expects($this->once())
        ->method('findByIdAndBrokerId')
        ->willReturn($this->output);

    $this->request->fileContent = '';

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->response?->getMessage())
        ->toBe(BrokerException::invalidJsonContent()->getMessage());
});

it('should present an ErrorResponse when a generic exception is thrown', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $this->readOutputRepository
        ->expects($this->once())
        ->method('findByIdAndBrokerId')
        ->willReturn($this->output);

    $this->fileRepository
        ->expects($this->once())
        ->method('create');

    $this->fileRepository
        ->expects($this->once())
        ->method('delete');

    $this->readOutputRepository
        ->expects($this->once())
        ->method('findParametersByType')
        ->willThrowException(new \Exception());

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(BrokerException::updateBrokerInputOutput()->getMessage());
});

it('should return created file path on success', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $this->readOutputRepository
        ->expects($this->once())
        ->method('findByIdAndBrokerId')
        ->willReturn($this->output);

    $this->fileRepository
        ->expects($this->once())
        ->method('create');

    $this->fileRepository
        ->expects($this->once())
        ->method('delete');

    $this->readOutputRepository
        ->expects($this->once())
        ->method('findParametersByType')
        ->willReturn($this->outputFields);

    $this->writeOutputRepository
        ->expects($this->once())
        ->method('update');

    ($this->useCase)($this->request, $this->presenter);

    $response = $this->presenter->response;
    expect($this->presenter->response)->toBeInstanceOf(UpdateStreamConnectorFileResponse::class)
        ->and($response->path)
        ->toBeString();
});
