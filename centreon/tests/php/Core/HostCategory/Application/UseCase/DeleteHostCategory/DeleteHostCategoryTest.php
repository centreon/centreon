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

namespace Tests\Core\HostCategory\Application\UseCase\DeleteHostCategory;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\HostCategory\Application\Repository\WriteHostCategoryRepositoryInterface;
use Core\HostCategory\Application\UseCase\DeleteHostCategory\DeleteHostCategory;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;

beforeEach(function () {
    $this->repository = $this->createMock(WriteHostCategoryRepositoryInterface::class);
    $this->presenterFormatter = $this->createMock(PresenterFormatterInterface::class);
    $this->contact = $this->createMock(ContactInterface::class);
});

it('should present an ErrorResponse when an exception is thrown', function () {
    $useCase = new DeleteHostCategory($this->repository, $this->contact);
    $hostCategoryId = 1;

    $this->contact
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);
    $this->repository
        ->expects($this->once())
        ->method('deleteById')
        ->willThrowException(new \Exception());

    $presenter = new DeleteHostCategoryPresenterStub($this->presenterFormatter);
    $useCase($hostCategoryId, $presenter);

    expect($presenter->getResponseStatus())->toBeInstanceOf(ErrorResponse::class)
        ->and($presenter->getResponseStatus()?->getMessage())
            ->toBe('Error while deleting host category #' . $hostCategoryId);
});

it('should present a NoContentResponse on success', function () {
    $useCase = new DeleteHostCategory($this->repository, $this->contact);
    $hostCategoryId = 1;

    $this->contact
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);
    $this->repository
        ->expects($this->once())
        ->method('deleteById');

    $presenter = new DeleteHostCategoryPresenterStub($this->presenterFormatter);

    $useCase($hostCategoryId, $presenter);

    expect($presenter->getResponseStatus())->toBeInstanceOf(NoContentResponse::class);
});
