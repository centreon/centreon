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

namespace Tests\Core\AdditionalConnectorConfiguration\Application\UseCase\FindAccs;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\AdditionalConnectorConfiguration\Application\Exception\AccException;
use Core\AdditionalConnectorConfiguration\Application\Repository\ReadAccRepositoryInterface;
use Core\AdditionalConnectorConfiguration\Application\UseCase\FindAccs\FindAccs;
use Core\AdditionalConnectorConfiguration\Application\UseCase\FindAccs\FindAccsResponse;
use Core\AdditionalConnectorConfiguration\Domain\Model\Acc;
use Core\AdditionalConnectorConfiguration\Domain\Model\AccParametersInterface;
use Core\AdditionalConnectorConfiguration\Domain\Model\Poller;
use Core\AdditionalConnectorConfiguration\Domain\Model\Type;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Contact\Application\Repository\ReadContactRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

beforeEach(function (): void {
    $this->useCase = new FindAccs(
        $this->requestParameters = $this->createMock(RequestParametersInterface::class),
        $this->readAccRepository = $this->createMock(ReadAccRepositoryInterface::class),
        $this->readAccessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class),
        $this->readContactRepository = $this->createMock(ReadContactRepositoryInterface::class),
        $this->user = $this->createMock(ContactInterface::class),
    );
    $this->presenter = new FindAccsPresenterStub();

    $this->acc = new Acc(
        id: $this->accId = 1,
        name: 'acc-name',
        type: Type::VMWARE_V6,
        createdBy: $this->createdBy = 2,
        updatedBy: $this->createdBy,
        createdAt: $this->createdAt = new \DateTimeImmutable('2023-05-09T12:00:00+00:00'),
        updatedAt: $this->createdAt,
        parameters: $this->createMock(AccParametersInterface::class),
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
        ->toBe(AccException::accessNotAllowed()->getMessage());
});

it('should present an ErrorResponse when an exception is thrown', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);
    $this->readAccRepository
        ->expects($this->once())
        ->method('findByRequestParameters')
        ->willThrowException(new \Exception());

    ($this->useCase)($this->presenter);

    expect($this->presenter->data)
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->data->getMessage())
        ->toBe(AccException::findAccs()->getMessage());
});

it('should present a FindAccsResponse on success', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);
   $this->readAccRepository
       ->expects($this->once())
       ->method('findByRequestParameters')
       ->willReturn([$this->acc]);
    $this->readContactRepository
        ->expects($this->once())
        ->method('findNamesByIds')
        ->willReturn([$this->createdBy => ['id' => $this->createdBy, 'name' => 'username']]);

    ($this->useCase)($this->presenter);

    $result = $this->presenter->data;
    expect($result)
        ->toBeInstanceOf(FindAccsResponse::class)
        ->and($result->accs[0]->name)->toBe($this->acc->getName())
        ->and($result->accs[0]->type)->toBe($this->acc->getType());
});
