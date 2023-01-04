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

namespace Tests\Core\HostCategory\Application\UseCase\AddHostCategory;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Application\Common\UseCase\CreatedResponse;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\HostCategory\Application\Exception\HostCategoryException;
use Core\HostCategory\Application\Repository\ReadHostCategoryRepositoryInterface;
use Core\HostCategory\Application\Repository\WriteHostCategoryRepositoryInterface;
use Core\HostCategory\Application\UseCase\AddHostCategory\AddHostCategory;
use Core\HostCategory\Application\UseCase\AddHostCategory\AddHostCategoryRequest;
use Core\HostCategory\Domain\Model\HostCategory;
use Core\Infrastructure\Common\Api\DefaultPresenter;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;

beforeEach(function () {
    $this->writeHostCategoryRepository = $this->createMock(WriteHostCategoryRepositoryInterface::class);
    $this->readHostCategoryRepository = $this->createMock(ReadHostCategoryRepositoryInterface::class);
    $this->presenterFormatter = $this->createMock(PresenterFormatterInterface::class);
    $this->user = $this->createMock(ContactInterface::class);
    $this->request = new AddHostCategoryRequest();
    $this->request->name = 'hc-name';
    $this->request->alias = 'hc-alias';
    $this->request->comment = null;
    $this->presenter = new DefaultPresenter($this->presenterFormatter);
    $this->useCase = new AddHostCategory(
        $this->writeHostCategoryRepository,
        $this->readHostCategoryRepository,
        $this->user
    );
    $this->hostCategory = new HostCategory(1, $this->request->name, $this->request->alias);
});

it('should present an ForbiddenResponse when a user has unsufficient rights', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(false);

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe(HostCategoryException::addNotAllowed()->getMessage());
});

it('should present a InvalidArgumentResponse when name is already used', function () {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->readHostCategoryRepository
        ->expects($this->once())
        ->method('existsByName')
        ->willReturn(true);

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(InvalidArgumentResponse::class)
        ->and($this->presenter->getResponseStatus()?->getMessage())
        ->toBe(HostCategoryException::hostNameAlreadyExists()->getMessage());
});

it('should present an ErrorResponse when an exception is thrown', function () {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->readHostCategoryRepository
        ->expects($this->once())
        ->method('existsByName')
        ->willReturn(false);
    $this->writeHostCategoryRepository
        ->expects($this->once())
        ->method('add')
        ->willThrowException(new \Exception());

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->getResponseStatus()?->getMessage())
        ->toBe(HostCategoryException::addHostCategory(new \Exception())->getMessage());
});

it('should return created object on success', function () {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->readHostCategoryRepository
        ->expects($this->once())
        ->method('existsByName')
        ->willReturn(false);
    $this->writeHostCategoryRepository
        ->expects($this->once())
        ->method('add')
        ->willReturn(1);
    $this->readHostCategoryRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($this->hostCategory);


    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->getPresentedData())->toBeInstanceOf(CreatedResponse::class);
    expect($this->presenter->getPresentedData()->getResourceId())->toBe($this->hostCategory->getId());

    $payload = $this->presenter->getPresentedData()->getPayload();
    expect($payload->name)
        ->toBe($this->hostCategory->getName())
        ->and($payload->alias)
        ->toBe($this->hostCategory->getAlias())
        ->and($payload->isActivated)
        ->toBe($this->hostCategory->isActivated())
        ->and($payload->comment)
        ->toBe($this->hostCategory->getComment());
});

