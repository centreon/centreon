<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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

namespace Tests\Core\Category\RealTime\Application\UseCase\FindServiceCategory;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Category\RealTime\Application\UseCase\FindServiceCategory\FindServiceCategory;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Tag\RealTime\Application\Repository\ReadTagRepositoryInterface;
use Core\Tag\RealTime\Domain\Model\Tag;
use Tests\Core\Category\RealTime\Application\UseCase\FindServiceCategory\FindServiceCategoryPresenterStub;

beforeEach(function () {
    $this->presenter = new FindServiceCategoryPresenterStub();

    $this->category = new Tag(1, 'service-category-name', Tag::SERVICE_CATEGORY_TYPE_ID);
    $this->repository = $this->createMock(ReadTagRepositoryInterface::class);
    $this->user = $this->createMock(ContactInterface::class);
    $this->readAccessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class);
    $this->tagRepository = $this->createMock(ReadTagRepositoryInterface::class);
});

it('Find all service categories as admin', function () {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);
    $this->tagRepository
        ->expects($this->once())
        ->method('findAllByTypeId')
        ->willReturn([$this->category]);

    $useCase = new FindServiceCategory($this->tagRepository, $this->user, $this->readAccessGroupRepository);

    $useCase($this->presenter);

    expect($this->presenter->response->tags)->toHaveCount(1);
    expect($this->presenter->response->tags[0]['id'])->toBe($this->category->getId());
    expect($this->presenter->response->tags[0]['name'])->toBe($this->category->getName());
});

it('Find all service categories as non-admin', function () {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(false);
    $this->readAccessGroupRepository
        ->expects($this->once())
        ->method('findByContact')
        ->willReturn([]);
    $this->tagRepository
        ->expects($this->once())
        ->method('findAllByTypeIdAndAccessGroups')
        ->willReturn([$this->category]);

    $useCase = new FindServiceCategory($this->tagRepository, $this->user, $this->readAccessGroupRepository);

    $useCase($this->presenter);

    expect($this->presenter->response->tags)->toHaveCount(1);
    expect($this->presenter->response->tags[0]['id'])->toBe($this->category->getId());
    expect($this->presenter->response->tags[0]['name'])->toBe($this->category->getName());
});

it('Find all service categories repository error', function () {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);
    $this->tagRepository->expects($this->once())
        ->method('findAllByTypeId')
        ->willThrowException(new \Exception());

    $useCase = new FindServiceCategory($this->tagRepository, $this->user, $this->readAccessGroupRepository);

    $useCase($this->presenter);

    expect($this->presenter->getResponseStatus())->toBeInstanceOf(ErrorResponse::class);
    expect($this->presenter->getResponseStatus()?->getMessage())->toBe(
        'An error occurred while retrieving service categories'
    );
});
