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

namespace Tests\Core\Resources\Application\UseCase\ExportResources;

use Centreon\Domain\Monitoring\Resource;
use Centreon\Domain\Monitoring\ResourceFilter;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Common\Domain\Collection\StringCollection;
use Core\Common\Domain\Exception\RepositoryException;
use Core\Resources\Application\Repository\ReadResourceRepositoryInterface;
use Core\Resources\Application\UseCase\ExportResources\ExportResources;
use Core\Resources\Application\UseCase\ExportResources\ExportResourcesRequest;
use Core\Resources\Application\UseCase\ExportResources\ExportResourcesResponse;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Collection\AccessGroupCollection;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;
use Mockery;

beforeEach(function () {
    $this->filters = Mockery::mock(ResourceFilter::class);
    $this->resourcesRepository = Mockery::mock(ReadResourceRepositoryInterface::class);
    $this->contactRepository = Mockery::mock(ReadAccessGroupRepositoryInterface::class);
    $this->presenter = new ExportResourcesPresenterStub();
});

it('test export resources with an invalid contact_id should throw an InvalidArgumentException', function () {
    $request = new ExportResourcesRequest(
        exportedFormat: 'csv',
        resourceFilter: $this->filters,
        allPages: false,
        maxResults: 0,
        columns: new StringCollection(),
        contactId: 0,
        isAdmin: true
    );
})->throws(\InvalidArgumentException::class);

it('test export resources with an invalid format should throw an InvalidArgumentException', function () {
    $request = new ExportResourcesRequest(
        exportedFormat: 'invalid',
        resourceFilter: $this->filters,
        allPages: false,
        maxResults: 0,
        columns: new StringCollection(),
        contactId: 1,
        isAdmin: true
    );
})->throws(\InvalidArgumentException::class);

it('export all resources without max results should throw an InvalidArgumentException', function () {
    $request = new ExportResourcesRequest(
        exportedFormat: 'csv',
        resourceFilter: $this->filters,
        allPages: true,
        maxResults: 0,
        columns: new StringCollection(),
        contactId: 1,
        isAdmin: true
    );
})->throws(\InvalidArgumentException::class);

it('export all resources with max results greater than 10000 should throw an InvalidArgumentException', function () {
    $request = new ExportResourcesRequest(
        exportedFormat: 'csv',
        resourceFilter: $this->filters,
        allPages: true,
        maxResults: 12000,
        columns: new StringCollection(),
        contactId: 1,
        isAdmin: true
    );
})->throws(\InvalidArgumentException::class);

it('export resources with an error from repository should throw an ErrorResponse', function () {
    $this->resourcesRepository
        ->shouldReceive('iterateResources')
        ->once()
        ->andThrow(Mockery::mock(RepositoryException::class));
    $request = new ExportResourcesRequest(
        exportedFormat: 'csv',
        resourceFilter: $this->filters,
        allPages: false,
        maxResults: 0,
        columns: new StringCollection(),
        contactId: 1,
        isAdmin: true
    );
    $useCase = new ExportResources($this->resourcesRepository, $this->contactRepository);
    $useCase($request, $this->presenter);
    expect($this->presenter->response)->toBeInstanceOf(ErrorResponse::class);
});

it('export resources with admin mode should throw a response with all resources', function () {
    $this->resourcesRepository
        ->shouldReceive('iterateResources')
        ->once()
        ->andReturn(new \ArrayObject([Mockery::mock(Resource::class)]));
    $request = new ExportResourcesRequest(
        exportedFormat: 'csv',
        resourceFilter: $this->filters,
        allPages: false,
        maxResults: 0,
        columns: StringCollection::create(['columns1', 'columns2']),
        contactId: 1,
        isAdmin: true
    );
    $useCase = new ExportResources($this->resourcesRepository, $this->contactRepository);
    $useCase($request, $this->presenter);
    expect($this->presenter->response)->toBeInstanceOf(ExportResourcesResponse::class)
        ->and($this->presenter->response->getResources())->toBeInstanceOf(\ArrayObject::class)
        ->and($this->presenter->response->getExportedFormat())->toBe('csv')
        ->and($this->presenter->response->getFilteredColumns()->toArray())->toBe(['columns1', 'columns2']);
});

it('export resources with acl should throw a response with allowed resources', function () {
    $this->contactRepository
        ->shouldReceive('findByContactId')
        ->once()
        ->andReturn(new AccessGroupCollection([new AccessGroup(1, 'test', 'test')]));
    $this->resourcesRepository
        ->shouldReceive('iterateResourcesByAccessGroupIds')
        ->once()
        ->andReturn(new \ArrayObject([Mockery::mock(Resource::class)]));
    $request = new ExportResourcesRequest(
        exportedFormat: 'csv',
        resourceFilter: $this->filters,
        allPages: false,
        maxResults: 0,
        columns: StringCollection::create(['columns1', 'columns2']),
        contactId: 1,
        isAdmin: false
    );
    $useCase = new ExportResources($this->resourcesRepository, $this->contactRepository);
    $useCase($request, $this->presenter);
    expect($this->presenter->response)->toBeInstanceOf(ExportResourcesResponse::class)
        ->and($this->presenter->response->getResources())->toBeInstanceOf(\ArrayObject::class)
        ->and($this->presenter->response->getExportedFormat())->toBe('csv')
        ->and($this->presenter->response->getFilteredColumns()->toArray())->toBe(['columns1', 'columns2']);
});
