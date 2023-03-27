<?php

/*
 * Copyright 2005 - 2022 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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

namespace Tests\Core\ServiceCategory\Application\UseCase\DeleteServiceCategory;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\ServiceCategory\Application\Exception\ServiceCategoryException;
use Core\ServiceCategory\Application\Repository\ReadServiceCategoryRepositoryInterface;
use Core\ServiceCategory\Application\Repository\WriteServiceCategoryRepositoryInterface;
use Core\ServiceCategory\Application\UseCase\DeleteServiceCategory\DeleteServiceCategory;
use Core\ServiceCategory\Domain\Model\ServiceCategory;
use Core\Infrastructure\Common\Api\DefaultPresenter;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

beforeEach(function () {
    $this->writeServiceCategoryRepository = $this->createMock(WriteServiceCategoryRepositoryInterface::class);
    $this->readServiceCategoryRepository = $this->createMock(ReadServiceCategoryRepositoryInterface::class);
    $this->accessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class);
    $this->presenterFormatter = $this->createMock(PresenterFormatterInterface::class);
    $this->presenter = new DefaultPresenter($this->presenterFormatter);
    $this->user = $this->createMock(ContactInterface::class);
    $this->serviceCategory = $this->createMock(ServiceCategory::class);
    $this->serviceCategoryId = 1;
});

it('should present an ErrorResponse when an exception is thrown', function () {
    $useCase = new DeleteServiceCategory(
        $this->writeServiceCategoryRepository,
        $this->readServiceCategoryRepository,
        $this->accessGroupRepository,
        $this->user
    );

    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);
    $this->readServiceCategoryRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(true);
    $this->writeServiceCategoryRepository
        ->expects($this->once())
        ->method('deleteById')
        ->willThrowException(new \Exception());

    $useCase($this->serviceCategoryId, $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe(ServiceCategoryException::deleteServiceCategory(new \Exception())->getMessage());
});

it('should present a ForbiddenResponse when a non-admin user has insufficient rights', function (): void {
    $useCase = new DeleteServiceCategory(
        $this->writeServiceCategoryRepository,
        $this->readServiceCategoryRepository,
        $this->accessGroupRepository,
        $this->user
    );

    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(false);
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(false);

    $useCase($this->serviceCategoryId, $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe(ServiceCategoryException::deleteNotAllowed()->getMessage());
});

it('should present a NotFoundResponse when the service category does not exist (with admin user)', function () {
    $useCase = new DeleteServiceCategory(
        $this->writeServiceCategoryRepository,
        $this->readServiceCategoryRepository,
        $this->accessGroupRepository,
        $this->user
    );

    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);
    $this->readServiceCategoryRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(false);

    $useCase($this->serviceCategoryId, $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(NotFoundResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe('Service category not found');
});

it('should present a NotFoundResponse when the service category does not exist (with non-admin user)', function () {
    $useCase = new DeleteServiceCategory(
        $this->writeServiceCategoryRepository,
        $this->readServiceCategoryRepository,
        $this->accessGroupRepository,
        $this->user
    );

    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(false);
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->readServiceCategoryRepository
        ->expects($this->once())
        ->method('existsByAccessGroups')
        ->willReturn(false);

    $useCase($this->serviceCategoryId, $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(NotFoundResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe('Service category not found');
});

it('should present a NoContentResponse on success (with admin user)', function () {
    $useCase = new DeleteServiceCategory(
        $this->writeServiceCategoryRepository,
        $this->readServiceCategoryRepository,
        $this->accessGroupRepository,
        $this->user
    );
    $serviceCategoryId = 1;

    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);
    $this->readServiceCategoryRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(true);
    $this->writeServiceCategoryRepository
        ->expects($this->once())
        ->method('deleteById');

    $useCase($serviceCategoryId, $this->presenter);

    expect($this->presenter->getResponseStatus())->toBeInstanceOf(NoContentResponse::class);
});

it('should present a NoContentResponse on success (with non-admin user)', function () {
    $useCase = new DeleteServiceCategory(
        $this->writeServiceCategoryRepository,
        $this->readServiceCategoryRepository,
        $this->accessGroupRepository,
        $this->user
    );

    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(false);
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->readServiceCategoryRepository
        ->expects($this->once())
        ->method('existsByAccessGroups')
        ->willReturn(true);
    $this->writeServiceCategoryRepository
        ->expects($this->once())
        ->method('deleteById');

    $useCase($this->serviceCategoryId, $this->presenter);

    expect($this->presenter->getResponseStatus())->toBeInstanceOf(NoContentResponse::class);
});
