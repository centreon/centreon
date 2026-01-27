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

namespace Tests\Core\ServiceGroup\Application\UseCase\GetServiceGroup;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Infrastructure\Common\Api\DefaultPresenter;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\ServiceGroup\Application\Exception\ServiceGroupException;
use Core\ServiceGroup\Application\Repository\ReadServiceGroupRepositoryInterface;
use Core\ServiceGroup\Application\UseCase\GetServiceGroup\GetServiceGroup;
use Core\ServiceGroup\Application\UseCase\GetServiceGroup\GetServiceGroupResponse;
use Core\ServiceGroup\Domain\Model\ServiceGroup;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Domain\Common\GeoCoords;

beforeEach(function (): void {
    $this->readServiceGroupRepository = $this->createMock(ReadServiceGroupRepositoryInterface::class);
    $this->readAccessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class);
    $this->presenterFormatter = $this->createMock(PresenterFormatterInterface::class);
    $this->user = $this->createMock(ContactInterface::class);
    $this->presenter = new DefaultPresenter($this->presenterFormatter);
    $this->useCase = new GetServiceGroup(
        $this->readServiceGroupRepository,
        $this->readAccessGroupRepository,
        $this->user
    );
    $this->serviceGroup = new ServiceGroup(
        1,
        'sg-name',
        'sg-alias',
        GeoCoords::fromString('-2,100'),
        "sg-comment",
        true,
    );
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

    ($this->useCase)($this->presenter, $this->serviceGroup->getId());

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ErrorResponse::class)
        ->and(value: $this->presenter->getResponseStatus()->getMessage())
        ->toBe(ServiceGroupException::errorWhileRetrieving()->getMessage());
});

it('should present a ForbiddenResponse when a user has insufficient rights', function (): void {
    $this->user
        ->expects($this->exactly(2))
        ->method('hasTopologyRole')
        ->willReturn(false);

    ($this->useCase)($this->presenter, $this->serviceGroup->getId());

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe(ServiceGroupException::accessNotAllowed()->getMessage());
});


it('should present a GetServiceGroupResponse with non-admin user', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(false);
    $this->readAccessGroupRepository
        ->expects($this->once())
        ->method('findByContact')
        ->willReturn([]);
    $this->readServiceGroupRepository
        ->expects($this->once())
        ->method('findOneByAccessGroups')
        ->willReturn($this->serviceGroup);

    ($this->useCase)($this->presenter, $this->serviceGroup->getId());

    $dto = $this->presenter->getPresentedData();
    expect($dto)
        ->toBeInstanceOf(GetServiceGroupResponse::class)
        ->and($dto->name)
        ->toBe($this->serviceGroup->getName())
        ->and($dto->alias)
        ->toBe($this->serviceGroup->getAlias())
        ->and($dto->isActivated)
        ->toBe($this->serviceGroup->isActivated())
        ->and($dto->geoCoords)
        ->toBe((string) $this->serviceGroup->getGeoCoords())
        ->and($dto->comment)
        ->toBe($this->serviceGroup->getComment());
});


it('should present a GetServiceGroupResponse with admin user', function (): void {
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

    ($this->useCase)($this->presenter, $this->serviceGroup->getId());

    $dto = $this->presenter->getPresentedData();
    expect($dto)
        ->toBeInstanceOf(GetServiceGroupResponse::class)
        ->and($dto->name)
        ->toBe($this->serviceGroup->getName())
        ->and($dto->alias)
        ->toBe($this->serviceGroup->getAlias())
        ->and($dto->isActivated)
        ->toBe($this->serviceGroup->isActivated())
        ->and($dto->geoCoords)
        ->toBe((string) $this->serviceGroup->getGeoCoords())
        ->and($dto->comment)
        ->toBe($this->serviceGroup->getComment());
});