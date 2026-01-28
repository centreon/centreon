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

namespace Tests\Core\ServiceCategory\Application\UseCase\GetServiceCategory;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Infrastructure\Common\Api\DefaultPresenter;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\ServiceCategory\Application\Exception\ServiceCategoryException;
use Core\ServiceCategory\Application\Repository\ReadServiceCategoryRepositoryInterface;
use Core\ServiceCategory\Application\UseCase\GetServiceCategory\GetServiceCategory;
use Core\ServiceCategory\Application\UseCase\GetServiceCategory\GetServiceCategoryResponse;
use Core\ServiceCategory\Domain\Model\ServiceCategory;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Domain\Common\GeoCoords;

beforeEach(function (): void {
    $this->readServiceCategoryRepository = $this->createMock(ReadServiceCategoryRepositoryInterface::class);
    $this->readAccessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class);
    $this->presenterFormatter = $this->createMock(PresenterFormatterInterface::class);
    $this->user = $this->createMock(ContactInterface::class);
    $this->presenter = new DefaultPresenter($this->presenterFormatter);
    $this->useCase = new GetServiceCategory(
        $this->readServiceCategoryRepository,
        $this->readAccessGroupRepository,
        $this->user
    );
    $this->serviceCategory = new ServiceCategory(
        1,
        'sg-name',
        'sg-alias',
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
    $this->readServiceCategoryRepository
        ->expects($this->once())
        ->method('findById')
        ->willThrowException(new \Exception());

    ($this->useCase)($this->presenter, $this->serviceCategory->getId());

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ErrorResponse::class)
        ->and(value: $this->presenter->getResponseStatus()->getMessage())
        ->toBe(ServiceCategoryException::errorWhileRetrieving()->getMessage());
});

it('should present a ForbiddenResponse when a user has insufficient rights', function (): void {
    $this->user
        ->expects($this->exactly(2))
        ->method('hasTopologyRole')
        ->willReturn(false);

    ($this->useCase)($this->presenter, $this->serviceCategory->getId());

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe(ServiceCategoryException::accessNotAllowed()->getMessage());
});


it('should present a GetServiceCategoryResponse with non-admin user', function (): void {
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
    $this->readServiceCategoryRepository
        ->expects($this->once())
        ->method('existsByAccessGroups')
        ->willReturn(true);
    $this->readServiceCategoryRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($this->serviceCategory);

    ($this->useCase)($this->presenter, $this->serviceCategory->getId());

    $dto = $this->presenter->getPresentedData();
    expect($dto)
        ->toBeInstanceOf(GetServiceCategoryResponse::class)
        ->and($dto->name)
        ->toBe($this->serviceCategory->getName())
        ->and($dto->alias)
        ->toBe($this->serviceCategory->getAlias())
        ->and($dto->isActivated)
        ->toBe($this->serviceCategory->isActivated());
});


it('should present a GetServiceCategoryResponse with admin user', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);
    $this->readServiceCategoryRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($this->serviceCategory);

    ($this->useCase)($this->presenter, $this->serviceCategory->getId());

    $dto = $this->presenter->getPresentedData();
    expect($dto)
        ->toBeInstanceOf(GetServiceCategoryResponse::class)
        ->and($dto->name)
        ->toBe($this->serviceCategory->getName())
        ->and($dto->alias)
        ->toBe($this->serviceCategory->getAlias())
        ->and($dto->isActivated)
        ->toBe($this->serviceCategory->isActivated());
});