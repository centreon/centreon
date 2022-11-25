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
use Core\Application\Common\UseCase\NoContentResponse;
use Core\HostCategory\Application\Repository\WriteHostCategoryRepositoryInterface;
use Core\HostCategory\Application\UseCase\CreateHostCategory\CreateHostCategory;
use Core\HostCategory\Application\UseCase\CreateHostCategory\CreateHostCategoryRequest;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

beforeEach(function () {
    $this->hostCategoryRepository = $this->createMock(WriteHostCategoryRepositoryInterface::class);
    $this->acccessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class);
    $this->presenterFormatter = $this->createMock(PresenterFormatterInterface::class);
    $this->user = $this->createMock(ContactInterface::class);
    $this->request = new CreateHostCategoryRequest();
    $this->request->name = 'hc-name';
    $this->request->alias = 'hc-alias';
});

it('should present an ErrorResponse when an exception is thrown', function () {
    $useCase = new CreateHostCategory($this->hostCategoryRepository,$this->acccessGroupRepository, $this->user);
    $presenter = new CreateHostCategoryPresenterStub($this->presenterFormatter);

    // $this->contact
    //     ->expects($this->once())
    //     ->method('isAdmin')
    //     ->willReturn(true);
    $this->hostCategoryRepository
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
    $useCase = new CreateHostCategory($this->hostCategoryRepository,$this->acccessGroupRepository, $this->user);
    $presenter = new CreateHostCategoryPresenterStub($this->presenterFormatter);

    // $this->contact
    //     ->expects($this->once())
    //     ->method('isAdmin')
    //     ->willReturn(true);
    $this->hostCategoryRepository
        ->expects($this->once())
        ->method('create');

    $useCase($this->request, $presenter);

    expect($presenter->getResponseStatus())->toBeInstanceOf(NoContentResponse::class);
});

