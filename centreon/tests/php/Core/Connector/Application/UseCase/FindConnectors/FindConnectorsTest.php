<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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

namespace Tests\Core\Connector\Application\UseCase\FindConnectors;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Centreon\Infrastructure\RequestParameters\RequestParametersTranslatorException;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Command\Application\Repository\ReadCommandRepositoryInterface;
use Core\Command\Domain\Model\Command;
use Core\Command\Domain\Model\CommandType;
use Core\Connector\Application\Exception\ConnectorException;
use Core\Connector\Application\Repository\ReadConnectorRepositoryInterface;
use Core\Connector\Application\UseCase\FindConnectors\FindConnectors;
use Core\Connector\Application\UseCase\FindConnectors\FindConnectorsResponse;
use Core\Connector\Domain\Model\Connector;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Tests\Core\Connector\Infrastructure\API\FindConnectors\FindConnectorsPresenterStub;

beforeEach(closure: function (): void {
    $this->presenter = new FindConnectorsPresenterStub($this->createMock(PresenterFormatterInterface::class));

    $this->useCase = new FindConnectors(
        $this->createMock(RequestParametersInterface::class),
        $this->readConnectorRepository = $this->createMock(ReadConnectorRepositoryInterface::class),
        $this->readCommandRepository = $this->createMock(ReadCommandRepositoryInterface::class),
        $this->contact = $this->createMock(ContactInterface::class),
    );
});

it('should present a ForbiddenResponse when the user has insufficient rights', function (): void {
    $this->contact
        ->expects($this->atMost(2))
        ->method('hasTopologyRole')
        ->willReturn(false);

    ($this->useCase)($this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(ConnectorException::accessNotAllowed()->getMessage());
});

it(
    'should present an ErrorResponse when an exception of type RequestParametersTranslatorException is thrown',
    function (): void {
        $this->contact
            ->expects($this->atMost(5))
            ->method('hasTopologyRole')
            ->willReturn(true);

        $exception = new RequestParametersTranslatorException('Error');

        $this->readConnectorRepository
            ->expects($this->once())
            ->method('findByRequestParametersAndCommandTypes')
            ->willThrowException($exception);

        ($this->useCase)($this->presenter);

        expect($this->presenter->response)
            ->toBeInstanceOf(ErrorResponse::class)
            ->and($this->presenter->response->getMessage())
            ->toBe($exception->getMessage());
    }
);

it(
    'should present an ErrorResponse when an exception of type Exception is thrown',
    function (): void {
        $this->contact
            ->expects($this->atMost(5))
            ->method('hasTopologyRole')
            ->willReturn(true);

        $exception = new \Exception('Error');

        $this->readConnectorRepository
            ->expects($this->once())
            ->method('findByRequestParametersAndCommandTypes')
            ->willThrowException($exception);

        ($this->useCase)($this->presenter);

        expect($this->presenter->response)
            ->toBeInstanceOf(ErrorResponse::class)
            ->and($this->presenter->response->getMessage())
            ->toBe(ConnectorException::errorWhileSearching($exception)->getMessage());
    }
);

it(
    'should present a FindConnectorsResponse when everything has gone well',
    function (): void {
        $this->contact
            ->expects($this->atMost(5))
            ->method('hasTopologyRole')
            ->willReturn(true);

        $connector = new Connector(
            id: 1,
            name: 'fake',
            commandLine: 'line',
            description: 'some-description',
            isActivated: true,
            commandIds: [12]
        );

        $this->readConnectorRepository
            ->expects($this->once())
            ->method('findByRequestParametersAndCommandTypes')
            ->willReturn([$connector]);

        $command = new Command(
            id: 12,
            name: 'fake',
            commandLine: 'line',
            type: CommandType::Check
        );

        $this->readCommandRepository
            ->expects($this->once())
            ->method('findByIds')
            ->willReturn([$command->getId() => $command]);

        ($this->useCase)($this->presenter);

        $connectorsResponse = $this->presenter->response;
        expect($connectorsResponse)->toBeInstanceOf(FindConnectorsResponse::class);
        expect($connectorsResponse->connectors[0]->id)->toBe($connector->getId());
        expect($connectorsResponse->connectors[0]->name)->toBe($connector->getName());
        expect($connectorsResponse->connectors[0]->commandLine)->toBe($command->getCommandLine());
        expect($connectorsResponse->connectors[0]->description)->toBe($connector->getDescription());
        expect($connectorsResponse->connectors[0]->isActivated)->toBe($connector->isActivated());
        expect($connectorsResponse->connectors[0]->commands[0]['id'])->toBe($command->getId());
        expect($connectorsResponse->connectors[0]->commands[0]['name'])->toBe($command->getName());
        expect($connectorsResponse->connectors[0]->commands[0]['type'])->toBe($command->getType());
    }
);
