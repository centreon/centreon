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

namespace Tests\Core\HostCategory\Application\UseCase\UpdateHostCategory;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Application\Common\UseCase\ConflictResponse;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\HostCategory\Application\Exception\HostCategoryException;
use Core\HostCategory\Application\Repository\ReadHostCategoryRepositoryInterface;
use Core\HostCategory\Application\Repository\WriteHostCategoryRepositoryInterface;
use Core\HostCategory\Application\UseCase\UpdateHostCategory\UpdateHostCategory;
use Core\HostCategory\Application\UseCase\UpdateHostCategory\UpdateHostCategoryRequest;
use Core\HostCategory\Domain\Model\HostCategory;
use Core\Infrastructure\Common\Api\DefaultPresenter;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Application\Common\UseCase\NotFoundResponse;

beforeEach(function (): void {
    $this->writeHostCategoryRepository = $this->createMock(WriteHostCategoryRepositoryInterface::class);
    $this->readHostCategoryRepository = $this->createMock(ReadHostCategoryRepositoryInterface::class);
    $this->accessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class);
    $this->presenterFormatter = $this->createMock(PresenterFormatterInterface::class);
    $this->user = $this->createMock(ContactInterface::class);
    $this->originalHostCategory = new HostCategory(1, 'hc-name', 'hc-alias');
    $this->request = new UpdateHostCategoryRequest();
    $this->request->name = 'hc-name-edit';
    $this->request->alias = 'hc-alias-edit';
    $this->presenter = new DefaultPresenter($this->presenterFormatter);
    $this->useCase = new UpdateHostCategory(
        $this->writeHostCategoryRepository,
        $this->readHostCategoryRepository,
        $this->accessGroupRepository,
        $this->user
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
    $this->readHostCategoryRepository
        ->expects($this->once())
        ->method('exists')
        ->willThrowException(new \Exception());

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe(HostCategoryException::UpdateHostCategory(new \Exception())->getMessage());
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
        ->toBe(HostCategoryException::writingActionsNotAllowed()->getMessage());
});

it('should present a NotFoundResponse when the host category does not exist (with admin user)', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);
    $this->readHostCategoryRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(false);

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(NotFoundResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe('Host category not found');
});

it('should present a NotFoundResponse when the host category does not exist (with non-admin user)', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(false);
    $this->readHostCategoryRepository
        ->expects($this->once())
        ->method('existsByAccessGroups')
        ->willReturn(false);

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(NotFoundResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe('Host category not found');
});

it('should present an ErrorResponse if the existing host category cannot be retrieved', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);
    $this->readHostCategoryRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(true);
    $this->readHostCategoryRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn(null);

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->getResponseStatus()?->getMessage())
        ->toBe(HostCategoryException::errorWhileRetrievingObject()->getMessage());
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
    $this->readHostCategoryRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(true);
    $this->readHostCategoryRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($this->originalHostCategory);
    $this->readHostCategoryRepository
        ->expects($this->once())
        ->method('existsByName')
        ->willReturn(true);

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->presenter->getResponseStatus()?->getMessage())
        ->toBe(HostCategoryException::hostNameAlreadyExists()->getMessage());
});

it('should present an InvalidArgumentResponse when an assertion fails', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);
    $this->readHostCategoryRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(true);
    $this->readHostCategoryRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($this->originalHostCategory);
    $this->readHostCategoryRepository
        ->expects($this->once())
        ->method('existsByName')
        ->willReturn(false);

    $this->request->alias = '';

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->getResponseStatus())->toBeInstanceOf(InvalidArgumentResponse::class);
});

it('should return created object on success', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);
    $this->readHostCategoryRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(true);
    $this->readHostCategoryRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($this->originalHostCategory);
    $this->readHostCategoryRepository
        ->expects($this->once())
        ->method('existsByName')
        ->willReturn(false);
    $this->writeHostCategoryRepository
        ->expects($this->once())
        ->method('update');

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->getResponseStatus())->toBeInstanceOf(NoContentResponse::class);
});

