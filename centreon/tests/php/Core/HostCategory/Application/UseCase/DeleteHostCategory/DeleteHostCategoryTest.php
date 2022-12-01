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
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\HostCategory\Application\Repository\ReadHostCategoryRepositoryInterface;
use Core\HostCategory\Application\Repository\WriteHostCategoryRepositoryInterface;
use Core\HostCategory\Application\UseCase\DeleteHostCategory\DeleteHostCategory;
use Core\HostCategory\Domain\Model\HostCategory;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

beforeEach(function () {
    $this->writeHostCategoryRepository = $this->createMock(WriteHostCategoryRepositoryInterface::class);
    $this->readHostCategoryRepository = $this->createMock(ReadHostCategoryRepositoryInterface::class);
    $this->accessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class);
    $this->presenterFormatter = $this->createMock(PresenterFormatterInterface::class);
    $this->user = $this->createMock(ContactInterface::class);
    $this->hostCategory = $this->createMock(HostCategory::class);
    $this->hostCategoryId = 1;
});

it('should present an ErrorResponse when an exception is thrown', function () {
    $useCase = new DeleteHostCategory(
        $this->writeHostCategoryRepository,
        $this->readHostCategoryRepository,
        $this->accessGroupRepository,
        $this->user
    );

    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);
    $this->readHostCategoryRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($this->hostCategory);
    $this->writeHostCategoryRepository
        ->expects($this->once())
        ->method('deleteById')
        ->willThrowException(new \Exception());

    $presenter = new DeleteHostCategoryPresenterStub($this->presenterFormatter);
    $useCase($this->hostCategoryId, $presenter);

    expect($presenter->getResponseStatus())
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($presenter->getResponseStatus()->getMessage())
        ->toBe('Error while deleting host category #' . $this->hostCategoryId);
});

it('should present an ForbiddenResponse when a non-admin user has unsufficient rights', function (): void {
    $useCase = new DeleteHostCategory(
        $this->writeHostCategoryRepository,
        $this->readHostCategoryRepository,
        $this->accessGroupRepository,
        $this->user
    );
    $presenter = new DeleteHostCategoryPresenterStub($this->presenterFormatter);

    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(false);
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(false);

    $useCase($this->hostCategoryId, $presenter);

    expect($presenter->getResponseStatus())
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($presenter->getResponseStatus()->getMessage())
        ->toBe('You are not allowed to access host categories');
});

it('should present a NotFoundResponse when the host category does not exist (with admin user)', function () {
    $useCase = new DeleteHostCategory(
        $this->writeHostCategoryRepository,
        $this->readHostCategoryRepository,
        $this->accessGroupRepository,
        $this->user
    );
    $presenter = new DeleteHostCategoryPresenterStub($this->presenterFormatter);

    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);
    $this->readHostCategoryRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn(null);

    $useCase($this->hostCategoryId, $presenter);

    expect($presenter->getResponseStatus())
        ->toBeInstanceOf(NotFoundResponse::class)
        ->and($presenter->getResponseStatus()->getMessage())
        ->toBe('Host category with id #' . $this->hostCategoryId . ' not found');
});

it('should present a NotFoundResponse when the host category does not exist (with non-admin user)', function () {
    $useCase = new DeleteHostCategory(
        $this->writeHostCategoryRepository,
        $this->readHostCategoryRepository,
        $this->accessGroupRepository,
        $this->user
    );
    $presenter = new DeleteHostCategoryPresenterStub($this->presenterFormatter);

    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(false);
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->readHostCategoryRepository
        ->expects($this->once())
        ->method('findByIdAndAccessGroups')
        ->willReturn(null);

    $useCase($this->hostCategoryId, $presenter);

    expect($presenter->getResponseStatus())
        ->toBeInstanceOf(NotFoundResponse::class)
        ->and($presenter->getResponseStatus()->getMessage())
        ->toBe('Host category with id #' . $this->hostCategoryId . ' not found');
});

it('should present a NoContentResponse on success (with admin user)', function () {
    $useCase = new DeleteHostCategory(
        $this->writeHostCategoryRepository,
        $this->readHostCategoryRepository,
        $this->accessGroupRepository,
        $this->user
    );
    $hostCategoryId = 1;

    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);
    $this->readHostCategoryRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($this->hostCategory);
    $this->writeHostCategoryRepository
        ->expects($this->once())
        ->method('deleteById');

    $presenter = new DeleteHostCategoryPresenterStub($this->presenterFormatter);

    $useCase($hostCategoryId, $presenter);

    expect($presenter->getResponseStatus())->toBeInstanceOf(NoContentResponse::class);
});

it('should present a NoContentResponse on success (with non-admin user)', function () {
    $useCase = new DeleteHostCategory(
        $this->writeHostCategoryRepository,
        $this->readHostCategoryRepository,
        $this->accessGroupRepository,
        $this->user
    );
    $presenter = new DeleteHostCategoryPresenterStub($this->presenterFormatter);

    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(false);
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->readHostCategoryRepository
        ->expects($this->once())
        ->method('findByIdAndAccessGroups')
        ->willReturn($this->hostCategory);
    $this->writeHostCategoryRepository
        ->expects($this->once())
        ->method('deleteById');

    $useCase($this->hostCategoryId, $presenter);

    expect($presenter->getResponseStatus())->toBeInstanceOf(NoContentResponse::class);
});
