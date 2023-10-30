<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

namespace Tests\Core\ServiceCategory\Application\UseCase\AddServiceCategory;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Application\Common\UseCase\ConflictResponse;
use Core\Application\Common\UseCase\CreatedResponse;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\ServiceCategory\Application\Exception\ServiceCategoryException;
use Core\ServiceCategory\Application\Repository\ReadServiceCategoryRepositoryInterface;
use Core\ServiceCategory\Application\Repository\WriteServiceCategoryRepositoryInterface;
use Core\ServiceCategory\Application\UseCase\AddServiceCategory\AddServiceCategory;
use Core\ServiceCategory\Application\UseCase\AddServiceCategory\AddServiceCategoryRequest;
use Core\ServiceCategory\Domain\Model\ServiceCategory;
use Core\Infrastructure\Common\Api\DefaultPresenter;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;

beforeEach(function () {
    $this->writeServiceCategoryRepository = $this->createMock(WriteServiceCategoryRepositoryInterface::class);
    $this->readServiceCategoryRepository = $this->createMock(ReadServiceCategoryRepositoryInterface::class);
    $this->presenterFormatter = $this->createMock(PresenterFormatterInterface::class);
    $this->user = $this->createMock(ContactInterface::class);
    $this->request = new AddServiceCategoryRequest();
    $this->request->name = 'sc-name';
    $this->request->alias = 'sc-alias';
    $this->presenter = new DefaultPresenter($this->presenterFormatter);
    $this->useCase = new AddServiceCategory(
        $this->writeServiceCategoryRepository,
        $this->readServiceCategoryRepository,
        $this->user
    );
    $this->serviceCategory = new ServiceCategory(1, $this->request->name, $this->request->alias);
});

it('should present an ErrorResponse when a generic exception is thrown', function () {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->readServiceCategoryRepository
        ->expects($this->once())
        ->method('existsByName')
        ->willThrowException(new \Exception());

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe(ServiceCategoryException::addServiceCategory(new \Exception())->getMessage());
});

it('should present a ForbiddenResponse when a user has insufficient rights', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(false);

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe(ServiceCategoryException::addNotAllowed()->getMessage());
});

it('should present an InvalidArgumentResponse when name is already used', function () {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->readServiceCategoryRepository
        ->expects($this->once())
        ->method('existsByName')
        ->willReturn(true);

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->presenter->getResponseStatus()?->getMessage())
        ->toBe(ServiceCategoryException::serviceNameAlreadyExists()->getMessage());
});

it('should present an ErrorResponse when an exception is thrown', function () {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->readServiceCategoryRepository
        ->expects($this->once())
        ->method('existsByName')
        ->willReturn(false);
    $this->writeServiceCategoryRepository
        ->expects($this->once())
        ->method('add')
        ->willThrowException(new \Exception());

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->getResponseStatus()?->getMessage())
        ->toBe(ServiceCategoryException::addServiceCategory(new \Exception())->getMessage());
});

it('should present an ErrorResponse if the newly created service category cannot be retrieved', function () {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->readServiceCategoryRepository
        ->expects($this->once())
        ->method('existsByName')
        ->willReturn(false);
    $this->writeServiceCategoryRepository
        ->expects($this->once())
        ->method('add')
        ->willReturn(1);
    $this->readServiceCategoryRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn(null);

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->getResponseStatus()?->getMessage())
        ->toBe(ServiceCategoryException::errorWhileRetrievingJustCreated()->getMessage());
});

it('should return created object on success', function () {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->readServiceCategoryRepository
        ->expects($this->once())
        ->method('existsByName')
        ->willReturn(false);
    $this->writeServiceCategoryRepository
        ->expects($this->once())
        ->method('add')
        ->willReturn(1);
    $this->readServiceCategoryRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($this->serviceCategory);


    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->getPresentedData())->toBeInstanceOf(CreatedResponse::class);
    expect($this->presenter->getPresentedData()->getResourceId())->toBe($this->serviceCategory->getId());

    $payload = $this->presenter->getPresentedData()->getPayload();
    expect($payload->name)
        ->toBe($this->serviceCategory->getName())
        ->and($payload->alias)
        ->toBe($this->serviceCategory->getAlias())
        ->and($payload->isActivated)
        ->toBe($this->serviceCategory->isActivated());
});

