<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * https://www.apache.org/licenses/LICENSE-2.0
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

namespace Tests\Core\Resources\Application\UseCase\FindResources;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Monitoring\ResourceFilter;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Resources\Application\Exception\ResourceException;
use Core\Resources\Application\Repository\ReadResourceRepositoryInterface;
use Core\Resources\Application\UseCase\FindResources\FindResources;
use Core\Resources\Application\UseCase\FindResources\FindResourcesResponse;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

beforeEach(function (): void {
    $this->presenter = new FindResourcesPresenterStub();
    $this->useCase = new FindResources(
        $this->repository = $this->createMock(ReadResourceRepositoryInterface::class),
        $this->contact = $this->createMock(ContactInterface::class),
        $this->createMock(RequestParametersInterface::class),
        $this->accessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class),
        new \ArrayObject([])
    );
});

it(
    'should send an ErrorResponse if something bad happen',
    function (): void {
        $this->contact->expects($this->once())->method('isAdmin')->willThrowException(new \Exception());

        ($this->useCase)($this->presenter, new ResourceFilter());

        expect($this->presenter->data)
            ->toBeInstanceOf(ErrorResponse::class)
            ->and($this->presenter->data->getMessage())
            ->toBe(ResourceException::errorWhileSearching()->getMessage());
    }
);

it(
    'should retrieve a valid FindResourcesResponse querying the repository with an admin contact',
    function (): void {
        $this->contact->expects($this->once())->method('isAdmin')->willReturn(true);
        $this->repository->expects($this->once())->method('findResources')->willReturn([]);
        $this->accessGroupRepository->expects($this->never())->method('findByContact')->willReturn([]);

        ($this->useCase)($this->presenter, new ResourceFilter());

        expect($this->presenter->data)->toBeInstanceOf(FindResourcesResponse::class);
    }
);

it(
    'should retrieve a valid FindResourcesResponse querying the repository with a normal contact',
    function (): void {
        $this->contact->expects($this->once())->method('isAdmin')->willReturn(false);
        $this->repository->expects($this->once())->method('findResourcesByAccessGroupIds')->willReturn([]);
        $this->accessGroupRepository->expects($this->once())->method('findByContact')->willReturn([]);

        ($this->useCase)($this->presenter, new ResourceFilter());

        expect($this->presenter->data)->toBeInstanceOf(FindResourcesResponse::class);
    }
);
