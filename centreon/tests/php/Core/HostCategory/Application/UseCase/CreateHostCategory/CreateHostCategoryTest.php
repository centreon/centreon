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

namespace Tests\Core\HostCategory\Application\UseCase\CreateHostCategory;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\HostCategory\Application\Repository\ReadHostCategoryRepositoryInterface;
use Core\HostCategory\Application\Repository\WriteHostCategoryRepositoryInterface;
use Core\HostCategory\Application\UseCase\CreateHostCategory\CreateHostCategory;
use Core\HostCategory\Application\UseCase\CreateHostCategory\CreateHostCategoryRequest;
use Core\HostCategory\Application\UseCase\CreateHostCategory\CreateHostCategoryResponse;
use Core\HostCategory\Domain\Model\HostCategory;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;

beforeEach(function () {
    $this->writeHostCategoryRepository = $this->createMock(WriteHostCategoryRepositoryInterface::class);
    $this->readHostCategoryRepository = $this->createMock(ReadHostCategoryRepositoryInterface::class);
    $this->presenterFormatter = $this->createMock(PresenterFormatterInterface::class);
    $this->user = $this->createMock(ContactInterface::class);
    $this->request = new CreateHostCategoryRequest();
    $this->request->name = 'hc-name';
    $this->request->alias = 'hc-alias';
    $this->request->comment = null;
    $this->presenter = new CreateHostCategoryPresenterStub($this->presenterFormatter);
    $this->useCase = new CreateHostCategory(
        $this->writeHostCategoryRepository,
        $this->readHostCategoryRepository,
        $this->user
    );
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
        ->toBe('You are not allowed to create host categories');
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
        ->toBe('Host category name already exists');
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
        ->method('create')
        ->willThrowException(new \Exception());

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->getResponseStatus()?->getMessage())
        ->toBe('Error while creating host category');
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
        ->method('create')
        ->willReturn(1);

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(CreateHostCategoryResponse::class)
        ->and($this->presenter->response->hostCategory)
        ->toBe([
            'id' => 1,
            'name' => $this->request->name,
            'alias' => $this->request->alias,
            'is_activated' => HostCategory::IS_ACTIVE,
            'comment' => null,
        ]);
});

