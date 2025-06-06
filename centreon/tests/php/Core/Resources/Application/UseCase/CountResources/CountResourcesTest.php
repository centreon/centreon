<?php
/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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

namespace Tests\Core\Resources\Application\UseCase\CountResources;

use Centreon\Domain\Monitoring\ResourceFilter;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Common\Domain\Exception\RepositoryException;
use Core\Resources\Application\Repository\ReadResourceRepositoryInterface;
use Core\Resources\Application\UseCase\CountResources\CountResources;
use Core\Resources\Application\UseCase\CountResources\CountResourcesRequest;
use Core\Resources\Application\UseCase\CountResources\CountResourcesResponse;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Collection\AccessGroupCollection;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;
use Mockery;
use Tests\Core\Resources\Application\UseCase\CountResources\CountResourcesPresenterStub;

beforeEach(function () {
    $this->filters = Mockery::mock(ResourceFilter::class);
    $this->resourcesRepository = Mockery::mock(ReadResourceRepositoryInterface::class);
    $this->contactRepository = Mockery::mock(ReadAccessGroupRepositoryInterface::class);
    $this->presenter = new CountResourcesPresenterStub();
});

it('count resources with an invalid contact_id should throw an InvalidArgumentException', function () {
    $request = new CountResourcesRequest(
        resourceFilter: $this->filters,
        allPages: true,
        contactId: 0,
        isAdmin: true
    );
})->throws(\InvalidArgumentException::class);

it('count resources with an error from repository should throw an ErrorResponse', function () {
    $this->resourcesRepository
        ->shouldReceive('countResourcesByFilter')
        ->andThrow(Mockery::mock(RepositoryException::class));
    $request = new CountResourcesRequest(
        resourceFilter: $this->filters,
        allPages: true,
        contactId: 1,
        isAdmin: true
    );
    $useCase = new CountResources($this->resourcesRepository, $this->contactRepository);
    $useCase($request, $this->presenter);
    expect($this->presenter->response)->toBeInstanceOf(ErrorResponse::class);
});

it('count resources with admin mode should throw a response with all resources', function () {
    $this->resourcesRepository
        ->shouldReceive('countResourcesByFilter')
        ->with($this->filters, true)
        ->andReturn(2);
    $this->resourcesRepository
        ->shouldReceive('countAllResources')
        ->withNoArgs()
        ->andReturn(10);
    $request = new CountResourcesRequest(
        resourceFilter: $this->filters,
        allPages: true,
        contactId: 1,
        isAdmin: true
    );
    $useCase = new CountResources($this->resourcesRepository, $this->contactRepository);
    $useCase($request, $this->presenter);
    expect($this->presenter->response)->toBeInstanceOf(CountResourcesResponse::class)
        ->and($this->presenter->response->getTotalFilteredResources())->toBe(2)
        ->and($this->presenter->response->getTotalResources())->toBe(10);
});

it('count resources with acl should throw a response with allowed resources', function () {
    $this->contactRepository
        ->shouldReceive('findByContactId')
        ->andReturn(AccessGroupCollection::create([new AccessGroup(1, 'test', 'test')]));
    $this->resourcesRepository
        ->shouldReceive('countResourcesByFilterAndAccessGroupIds')
        ->with($this->filters, true, [1])
        ->andReturn(2);
    $this->resourcesRepository
        ->shouldReceive('countAllResourcesByAccessGroupIds')
        ->with([1])
        ->andReturn(10);
    $request = new CountResourcesRequest(
        resourceFilter: $this->filters,
        allPages: true,
        contactId: 1,
        isAdmin: false
    );
    $useCase = new CountResources($this->resourcesRepository, $this->contactRepository);
    $useCase($request, $this->presenter);
    expect($this->presenter->response)->toBeInstanceOf(CountResourcesResponse::class)
        ->and($this->presenter->response->getTotalFilteredResources())->toBe(2)
        ->and($this->presenter->response->getTotalResources())->toBe(10);
});
