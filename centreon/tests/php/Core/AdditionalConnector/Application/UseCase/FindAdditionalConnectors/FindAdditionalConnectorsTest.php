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

namespace Tests\Core\AdditionalConnector\Application\UseCase\FindAdditionalConnectors;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\AdditionalConnector\Application\Exception\AdditionalConnectorException;
use Core\AdditionalConnector\Application\Repository\ReadAdditionalConnectorRepositoryInterface;
use Core\AdditionalConnector\Application\UseCase\FindAdditionalConnectors\FindAdditionalConnectors;
use Core\AdditionalConnector\Application\UseCase\FindAdditionalConnectors\FindAdditionalConnectorsResponse;
use Core\AdditionalConnector\Domain\Model\AdditionalConnector;
use Core\AdditionalConnector\Domain\Model\Poller;
use Core\AdditionalConnector\Domain\Model\Type;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Contact\Application\Repository\ReadContactRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

beforeEach(function (): void {
    $this->useCase = new FindAdditionalConnectors(
        $this->requestParameters = $this->createMock(RequestParametersInterface::class),
        $this->readAdditionalConnectorRepository = $this->createMock(ReadAdditionalConnectorRepositoryInterface::class),
        $this->readAccessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class),
        $this->readContactRepository = $this->createMock(ReadContactRepositoryInterface::class),
        $this->user = $this->createMock(ContactInterface::class),
    );
    $this->presenter = new FindAdditionalConnectorsPresenterStub();

    $this->additionalConnector = new AdditionalConnector(
        id: $this->accId = 1,
        name: 'additionalconnector-name',
        type: Type::VMWARE_V6,
        createdBy: $this->createdBy = 2,
        updatedBy: $this->createdBy,
        createdAt: $this->createdAt = new \DateTimeImmutable('2023-05-09T12:00:00+00:00'),
        updatedAt: $this->createdAt,
        parameters: ['param' => 'value'],
    );

    $this->poller = new Poller(1, 'my-poller');
});

it('should present a ForbiddenResponse when a user has insufficient rights', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(false);

    ($this->useCase)($this->presenter);

    expect($this->presenter->data)
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->presenter->data->getMessage())
        ->toBe(AdditionalConnectorException::readNotAllowed()->getMessage());
});

it('should present a ErrorResponse when an exception is thrown', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);
    $this->readAdditionalConnectorRepository
        ->expects($this->once())
        ->method('findByRequestParameters')
        ->willThrowException(new \Exception());

    ($this->useCase)($this->presenter);

    expect($this->presenter->data)
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->data->getMessage())
        ->toBe(AdditionalConnectorException::findAdditionalConnectors()->getMessage());
});

it('should present a FindAdditionalConnectorsResponse on success', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);
   $this->readAdditionalConnectorRepository
       ->expects($this->once())
       ->method('findByRequestParameters')
       ->willReturn([$this->additionalConnector]);
    $this->readContactRepository
        ->expects($this->once())
        ->method('findNamesByIds')
        ->willReturn([$this->createdBy => ['id' => $this->createdBy, 'name' => 'username']]);

    ($this->useCase)($this->presenter);

    $result = $this->presenter->data;
    expect($result)
        ->toBeInstanceOf(FindAdditionalConnectorsResponse::class)
        ->and($result->additionalConnectors[0]->name)->toBe($this->additionalConnector->getName())
        ->and($result->additionalConnectors[0]->type)->toBe($this->additionalConnector->getType());
});
