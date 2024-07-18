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

namespace Tests\Core\HostCategory\Application\UseCase\FindRealTimeHostCategories;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\HostCategory\Application\Repository\ReadHostCategoryRepositoryInterface;
use Core\HostCategory\Application\Repository\ReadRealTimeHostCategoryRepositoryInterface;
use Core\HostCategory\Application\UseCase\FindRealTimeHostCategories\FindRealTimeHostCategories;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;
use Core\Tag\RealTime\Domain\Model\Tag;

beforeEach(function (): void {
    $this->useCase = new FindRealTimeHostCategories(
        $this->user = $this->createMock(ContactInterface::class),
        $this->repository = $this->createMock(ReadRealTimeHostCategoryRepositoryInterface::class),
        $this->configurationRepository = $this->createMock(ReadHostCategoryRepositoryInterface::class),
        $this->requestParameters = $this->createMock(RequestParametersInterface::class),
        $this->accessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class)
    );
    $this->presenter = new FindRealTimeHostCategoriesPresenterStub($this->createMock(PresenterFormatterInterface::class));
    $this->accessGroup = new AccessGroup(1, 'AG-name', 'AG-alias');
    $this->category = new Tag(1, 'host-category-name', Tag::SERVICE_CATEGORY_TYPE_ID);
});

it('should find all categories as admin', function (): void {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->repository->expects($this->once())
        ->method('findAll')
        ->willReturn([$this->category]);

    ($this->useCase)($this->presenter);

    expect($this->presenter->response->tags)->toHaveCount(1);
    expect($this->presenter->response->tags[0]['id'])->toBe($this->category->getId());
    expect($this->presenter->response->tags[0]['name'])->toBe($this->category->getName());
});

it('should find all categories as user with no filters on categories', function (): void {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(false);

    $this->accessGroupRepository
        ->expects($this->once())
        ->method('findByContact')
        ->willReturn([$this->accessGroup]);

    $this->configurationRepository
        ->expects($this->once())
        ->method('hasAclFilterOnHostCategories')
        ->willReturn(false);

    $this->repository->expects($this->once())
        ->method('findAll')
        ->willReturn([$this->category]);

    ($this->useCase)($this->presenter);

    expect($this->presenter->response->tags)->toHaveCount(1);
    expect($this->presenter->response->tags[0]['id'])->toBe($this->category->getId());
    expect($this->presenter->response->tags[0]['name'])->toBe($this->category->getName());
});

it('should find all categories as user with filters on categories', function (): void {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(false);

    $this->accessGroupRepository
        ->expects($this->once())
        ->method('findByContact')
        ->willReturn([$this->accessGroup]);

    $this->configurationRepository
        ->expects($this->once())
        ->method('hasAclFilterOnHostCategories')
        ->willReturn(true);

    $this->repository->expects($this->once())
        ->method('findAllByAccessGroupIds')
        ->willReturn([$this->category]);

    ($this->useCase)($this->presenter);

    expect($this->presenter->response->tags)->toHaveCount(1);
    expect($this->presenter->response->tags[0]['id'])->toBe($this->category->getId());
    expect($this->presenter->response->tags[0]['name'])->toBe($this->category->getName());
});
