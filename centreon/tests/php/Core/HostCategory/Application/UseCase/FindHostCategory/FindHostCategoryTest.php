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

namespace Tests\Core\HostCategory\Application\UseCase\FindHostCategory;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\HostCategory\Application\Exception\HostCategoryException;
use Core\HostCategory\Application\Repository\ReadHostCategoryRepositoryInterface;
use Core\HostCategory\Application\UseCase\FindHostCategory\FindHostCategory;
use Core\HostCategory\Application\UseCase\FindHostCategory\FindHostCategoryResponse;
use Core\HostCategory\Domain\Model\HostCategory;
use Core\Infrastructure\Common\Api\DefaultPresenter;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Exception;

beforeEach(function (): void {
    $this->readHostCategoryRepository = $this->createMock(ReadHostCategoryRepositoryInterface::class);
    $this->accessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class);
    $this->presenterFormatter = $this->createMock(PresenterFormatterInterface::class);
    $this->user = $this->createMock(ContactInterface::class);
    $this->usecase = new FindHostCategory(
        $this->readHostCategoryRepository,
        $this->accessGroupRepository,
        $this->user
    );
    $this->presenter = new DefaultPresenter($this->presenterFormatter);
    $this->hostCategoryName = 'hc-name';
    $this->hostCategoryAlias = 'hc-alias';
    $this->hostCategoryComment = 'blablabla';
    $this->hostCategory = new HostCategory(1, $this->hostCategoryName, $this->hostCategoryAlias);
    $this->hostCategory->setComment($this->hostCategoryComment);
    $this->responseArray = [
        'id' => 1,
        'name' => $this->hostCategoryName,
        'alias' => $this->hostCategoryAlias,
        'is_activated' => true,
        'comment' => $this->hostCategoryComment,
    ];
});

it('should present an ErrorResponse when an exception is thrown', function (): void {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);
    $this->readHostCategoryRepository
        ->expects($this->once())
        ->method('exists')
        ->willThrowException(new \Exception());

    ($this->usecase)($this->hostCategory->getId(), $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe(HostCategoryException::findHostCategory(new Exception(), $this->hostCategory->getId())->getMessage());
});

it('should present a ForbiddenResponse when a non-admin user has insufficient rights', function (): void {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(false);
    $this->user
        ->expects($this->atMost(2))
        ->method('hasTopologyRole')
        ->willReturnMap(
            [
                [Contact::ROLE_CONFIGURATION_HOSTS_CATEGORIES_READ, false],
                [Contact::ROLE_CONFIGURATION_HOSTS_CATEGORIES_READ_WRITE, false],
            ]
        );

    ($this->usecase)($this->hostCategory->getId(), $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->presenter->getResponseStatus()?->getMessage())
        ->toBe(HostCategoryException::accessNotAllowed()->getMessage());
});

it('should present a NotFoundResponse when the host category does not exist (with admin user)', function (): void {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);
    $this->readHostCategoryRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(false);

    ($this->usecase)($this->hostCategory->getId(), $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(NotFoundResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe('Host category not found');
});

it('should present a NotFoundResponse when the host category does not exist (with non-admin user)', function (): void {
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
        ->method('existsByAccessGroups')
        ->willReturn(false);

    ($this->usecase)($this->hostCategory->getId(), $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(NotFoundResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe('Host category not found');
});

it('should present a FindHostCategoryResponse when a non-admin user has read-only rights', function (): void {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(false);
    $this->user
        ->expects($this->atMost(2))
        ->method('hasTopologyRole')
        ->willReturnMap(
            [
                [Contact::ROLE_CONFIGURATION_HOSTS_CATEGORIES_READ, true],
                [Contact::ROLE_CONFIGURATION_HOSTS_CATEGORIES_READ_WRITE, false],
            ]
        );
    $this->readHostCategoryRepository
        ->expects($this->once())
        ->method('existsByAccessGroups')
        ->willReturn(true);
    $this->readHostCategoryRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($this->hostCategory);

    ($this->usecase)($this->hostCategory->getId(), $this->presenter);

    $response = $this->presenter->getPresentedData();
    expect($response)
        ->toBeInstanceOf(FindHostCategoryResponse::class)
        ->and($response->id)
        ->toBe($this->responseArray['id'])
        ->and($response->name)
        ->toBe($this->responseArray['name'])
        ->and($response->alias)
        ->toBe($this->responseArray['alias'])
        ->and($response->isActivated)
        ->toBe($this->responseArray['is_activated'])
        ->and($response->comment)
        ->toBe($this->responseArray['comment']);
    });

it('should present a FindHostCategoryResponse when a non-admin user has read/write rights', function (): void {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(false);
    $this->user
        ->expects($this->atMost(2))
        ->method('hasTopologyRole')
        ->willReturnMap(
            [
                [Contact::ROLE_CONFIGURATION_HOSTS_CATEGORIES_READ, false],
                [Contact::ROLE_CONFIGURATION_HOSTS_CATEGORIES_READ_WRITE, true],
            ]
        );
    $this->readHostCategoryRepository
        ->expects($this->once())
        ->method('existsByAccessGroups')
        ->willReturn(true);
    $this->readHostCategoryRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($this->hostCategory);

    ($this->usecase)($this->hostCategory->getId(), $this->presenter);

    $response = $this->presenter->getPresentedData();
    expect($response)
        ->toBeInstanceOf(FindHostCategoryResponse::class)
        ->and($response->id)
        ->toBe($this->responseArray['id'])
        ->and($response->name)
        ->toBe($this->responseArray['name'])
        ->and($response->alias)
        ->toBe($this->responseArray['alias'])
        ->and($response->isActivated)
        ->toBe($this->responseArray['is_activated'])
        ->and($response->comment)
        ->toBe($this->responseArray['comment']);
});


it('should present a FindHostCategoryResponse with admin user', function (): void {
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
        ->willReturn($this->hostCategory);

    ($this->usecase)($this->hostCategory->getId(), $this->presenter);

    $response = $this->presenter->getPresentedData();
    expect($response)
        ->toBeInstanceOf(FindHostCategoryResponse::class)
        ->and($response->id)
        ->toBe($this->responseArray['id'])
        ->and($response->name)
        ->toBe($this->responseArray['name'])
        ->and($response->alias)
        ->toBe($this->responseArray['alias'])
        ->and($response->isActivated)
        ->toBe($this->responseArray['is_activated'])
        ->and($response->comment)
        ->toBe($this->responseArray['comment']);
});
