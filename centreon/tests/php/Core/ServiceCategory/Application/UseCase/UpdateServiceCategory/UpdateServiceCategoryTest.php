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

namespace Tests\Core\ServiceCategory\Application\UseCase\UpdateServiceCategory;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Application\Common\UseCase\ConflictResponse;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Infrastructure\Common\Api\DefaultPresenter;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\ServiceCategory\Application\Exception\ServiceCategoryException;
use Core\ServiceCategory\Application\Repository\ReadServiceCategoryRepositoryInterface;
use Core\ServiceCategory\Application\Repository\WriteServiceCategoryRepositoryInterface;
use Core\ServiceCategory\Application\UseCase\UpdateServiceCategory\UpdateServiceCategory;
use Core\ServiceCategory\Application\UseCase\UpdateServiceCategory\UpdateServiceCategoryRequest;
use Core\ServiceCategory\Domain\Model\ServiceCategory;
use Core\Domain\Common\GeoCoords;

beforeEach(function (): void {
    $this->presenter = new DefaultPresenter(
        $this->presenterFormatter = $this->createMock(PresenterFormatterInterface::class)
    );
    $this->useCase = new UpdateServiceCategory(
        $this->writeServiceCategoryRepository = $this->createMock(WriteServiceCategoryRepositoryInterface::class),
        $this->readServiceCategoryRepository = $this->createMock(ReadServiceCategoryRepositoryInterface::class),
        $this->readAccessGroupRepositoryInterface = $this->createMock(ReadAccessGroupRepositoryInterface::class),
        $this->user = $this->createMock(ContactInterface::class)
    );

    $this->serviceCategory = new ServiceCategory(
        1,
        'sc-name',
        'sc-alias',
    );

    $this->request = new UpdateServiceCategoryRequest();
    $this->request->name = $this->serviceCategory->getName() . '-edited';
    $this->request->alias = $this->serviceCategory->getAlias() . '-edited';
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

    ($this->useCase)($this->request, $this->presenter, $this->serviceCategory->getId());

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe(ServiceCategoryException::errorWhileUpdating()->getMessage());
});

it('should present a ForbiddenResponse when a user has insufficient rights', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(false);

    ($this->useCase)($this->request, $this->presenter, $this->serviceCategory->getId());

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe(ServiceCategoryException::editNotAllowed()->getMessage());
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

    $this->readServiceCategoryRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($this->serviceCategory);

    $this->readServiceCategoryRepository
        ->expects($this->once())
        ->method('existsByName')
        ->willReturn(true);
        


    ($this->useCase)($this->request, $this->presenter, $this->serviceCategory->getId());

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->presenter->getResponseStatus()?->getMessage())
        ->toBe(ServiceCategoryException::nameAlreadyExists($this->request->name)->getMessage());
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

    $this->readServiceCategoryRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($this->serviceCategory);

    $this->readServiceCategoryRepository
        ->expects($this->once())
        ->method('existsByName')
        ->willReturn(false);

    $this->writeServiceCategoryRepository
        ->expects($this->once())
        ->method('update');

    ($this->useCase)($this->request, $this->presenter, $this->serviceCategory->getId());

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

    $this->readServiceCategoryRepository
        ->expects($this->once())
        ->method('existsByAccessGroups')
        ->willReturn(true);

    $this->readServiceCategoryRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($this->serviceCategory);

    $this->readServiceCategoryRepository
        ->expects($this->once())
        ->method('existsByName')
        ->willReturn(false);


    $this->writeServiceCategoryRepository
        ->expects($this->once())
        ->method('update');

    ($this->useCase)($this->request, $this->presenter, $this->serviceCategory->getId());

    expect($this->presenter->getResponseStatus())->toBeInstanceOf(NoContentResponse::class);
});