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
use Core\Application\Common\UseCase\NoContentResponse;
use Core\HostCategory\Application\Repository\ReadHostCategoryRepositoryInterface;
use Core\HostCategory\Application\Repository\WriteHostCategoryRepositoryInterface;
use Core\HostCategory\Application\UseCase\CreateHostCategory\CreateHostCategory;
use Core\HostCategory\Application\UseCase\CreateHostCategory\CreateHostCategoryRequest;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;

beforeEach(function () {
    $this->writeHostCategoryRepository = $this->createMock(WriteHostCategoryRepositoryInterface::class);
    $this->readHostCategoryRepository = $this->createMock(ReadHostCategoryRepositoryInterface::class);
    $this->presenterFormatter = $this->createMock(PresenterFormatterInterface::class);
    $this->user = $this->createMock(ContactInterface::class);
    $this->request = new CreateHostCategoryRequest();
    $this->request->name = 'hc-name';
    $this->request->alias = 'hc-alias';
});

it('should present an ForbiddenResponse when a user has unsufficient rights', function (): void {
    $useCase = new CreateHostCategory($this->writeHostCategoryRepository,$this->readHostCategoryRepository, $this->user);
    $presenter = new CreateHostCategoryPresenterStub($this->presenterFormatter);

    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(false);

    $useCase($this->request, $presenter);

    expect($presenter->getResponseStatus())
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($presenter->getResponseStatus()->getMessage())
        ->toBe('You are not allowed to create host categories');
});

it('should present a InvalidArgumentResponse when name is already used', function () {
    $useCase = new CreateHostCategory($this->writeHostCategoryRepository,$this->readHostCategoryRepository, $this->user);
    $presenter = new CreateHostCategoryPresenterStub($this->presenterFormatter);

    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->readHostCategoryRepository
        ->expects($this->once())
        ->method('existsByName')
        ->willReturn(true);

    $useCase($this->request, $presenter);

    expect($presenter->getResponseStatus())
        ->toBeInstanceOf(InvalidArgumentResponse::class)
        ->and($presenter->getResponseStatus()?->getMessage())
        ->toBe('Host category name already exists');
});

it('should present an ErrorResponse when an exception is thrown', function () {
    $useCase = new CreateHostCategory($this->writeHostCategoryRepository,$this->readHostCategoryRepository, $this->user);
    $presenter = new CreateHostCategoryPresenterStub($this->presenterFormatter);

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

    $useCase($this->request, $presenter);

    expect($presenter->getResponseStatus())
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($presenter->getResponseStatus()?->getMessage())
        ->toBe('Error while creating host category');
});

it('should present a NoContentResponse on success', function () {
    $useCase = new CreateHostCategory($this->writeHostCategoryRepository,$this->readHostCategoryRepository, $this->user);
    $presenter = new CreateHostCategoryPresenterStub($this->presenterFormatter);

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
        ->method('create');

    $useCase($this->request, $presenter);

    expect($presenter->getResponseStatus())->toBeInstanceOf(NoContentResponse::class);
});

