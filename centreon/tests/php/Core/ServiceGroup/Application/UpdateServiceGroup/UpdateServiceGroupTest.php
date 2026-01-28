<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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

namespace Tests\Core\ServiceGroup\Application\UseCase\UpdateServiceGroup;

use Centreon\Domain\Common\Assertion\AssertionException;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Application\Common\UseCase\ConflictResponse;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Infrastructure\Common\Api\DefaultPresenter;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\ServiceGroup\Application\Exception\ServiceGroupException;
use Core\ServiceGroup\Application\Repository\ReadServiceGroupRepositoryInterface;
use Core\ServiceGroup\Application\Repository\WriteServiceGroupRepositoryInterface;
use Core\ServiceGroup\Application\UseCase\UpdateServiceGroup\UpdateServiceGroup;
use Core\ServiceGroup\Application\UseCase\UpdateServiceGroup\UpdateServiceGroupRequest;
use Core\ServiceGroup\Domain\Model\ServiceGroup;
use Core\Domain\Common\GeoCoords;

beforeEach(function (): void {
    $this->presenter = new DefaultPresenter(
        $this->presenterFormatter = $this->createMock(PresenterFormatterInterface::class)
    );
    $this->useCase = new UpdateServiceGroup(
        $this->writeServiceGroupRepository = $this->createMock(WriteServiceGroupRepositoryInterface::class),
        $this->readServiceGroupRepository = $this->createMock(ReadServiceGroupRepositoryInterface::class),
        $this->readAccessGroupRepositoryInterface = $this->createMock(ReadAccessGroupRepositoryInterface::class),
        $this->user = $this->createMock(ContactInterface::class)
    );

    $this->serviceGroup = new ServiceGroup(
        1,
        'sg-name',
        'sg-alias',
        GeoCoords::fromString('-2,100'),
        "sg-comment",
        true
    );

    $this->request = new UpdateServiceGroupRequest();
    $this->request->name = $this->serviceGroup->getName() . '-edited';
    $this->request->alias = $this->serviceGroup->getAlias() . '-edited';
    $this->request->comment = $this->serviceGroup->getComment() . '-edited';
    $this->request->geoCoords = '-3,100';
});

it('should present an ErrorResponse when a generic exception is thrown', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->readServiceGroupRepository
        ->expects($this->once())
        ->method('findOne')
        ->willThrowException(new \Exception());

    ($this->useCase)($this->request, $this->presenter, $this->serviceGroup->getId());

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe(ServiceGroupException::errorWhileUpdating()->getMessage());
});

it('should present a ForbiddenResponse when a user has insufficient rights', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(false);

    ($this->useCase)($this->request, $this->presenter, $this->serviceGroup->getId());

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe(ServiceGroupException::editNotAllowed()->getMessage());
});

it('should present a ConflictResponse when name is already used', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->readServiceGroupRepository
        ->expects($this->once())
        ->method('findOne')
        ->willReturn($this->serviceGroup);

    $this->readServiceGroupRepository
        ->expects($this->once())
        ->method('nameAlreadyExists')
        ->willReturn(true);
        


    ($this->useCase)($this->request, $this->presenter, $this->serviceGroup->getId());

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->presenter->getResponseStatus()?->getMessage())
        ->toBe(ServiceGroupException::nameAlreadyExists($this->request->name)->getMessage());
});



it('should return void on success when admin user', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->readServiceGroupRepository
        ->expects($this->once())
        ->method('findOne')
        ->willReturn($this->serviceGroup);

    $this->readServiceGroupRepository
        ->expects($this->once())
        ->method('nameAlreadyExists')
        ->willReturn(false);

    $this->writeServiceGroupRepository
        ->expects($this->once())
        ->method('update');

    ($this->useCase)($this->request, $this->presenter, $this->serviceGroup->getId());

    expect($this->presenter->getResponseStatus())->toBeInstanceOf(NoContentResponse::class);
});

it('should return void on success when no-admin user', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(false);

    $this->readServiceGroupRepository
        ->expects($this->once())
        ->method('findOneByAccessGroups')
        ->willReturn($this->serviceGroup);

    $this->readServiceGroupRepository
        ->expects($this->once())
        ->method('nameAlreadyExists')
        ->willReturn(false);


    $this->writeServiceGroupRepository
        ->expects($this->once())
        ->method('update');

    ($this->useCase)($this->request, $this->presenter, $this->serviceGroup->getId());

    expect($this->presenter->getResponseStatus())->toBeInstanceOf(NoContentResponse::class);
});
