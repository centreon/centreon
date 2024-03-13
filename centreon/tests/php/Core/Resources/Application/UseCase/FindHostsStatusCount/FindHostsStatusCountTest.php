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

namespace Tests\Core\Resources\Application\UseCase\FindHostsStatusCount;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Monitoring\ResourceFilter;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Resources\Application\Exception\ResourceException;
use Core\Resources\Application\Repository\ReadResourceRepositoryInterface;
use Core\Resources\Application\UseCase\FindHostsStatusCount\FindHostsStatusCount;
use Core\Resources\Application\UseCase\FindHostsStatusCount\FindHostsStatusCountResponse;
use Core\Resources\Domain\Model\DownStatusCount;
use Core\Resources\Domain\Model\HostsStatusCount;
use Core\Resources\Domain\Model\PendingStatusCount;
use Core\Resources\Domain\Model\ResourcesStatusCount;
use Core\Resources\Domain\Model\UnreachableStatusCount;
use Core\Resources\Domain\Model\UpStatusCount;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

beforeEach(function () {
   $this->user = $this->createMock(ContactInterface::class);
   $this->readAccessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class);
   $this->readResourceRepository = $this->createMock(ReadResourceRepositoryInterface::class);
   $this->resourceFilter = $this->createMock(ResourceFilter::class);
});

it('should present an Error Response when an error occurred', function () {
   $useCase = new FindHostsStatusCount(
       $this->user,
       $this->readAccessGroupRepository,
       $this->readResourceRepository
   );
   $presenter = new FindHostsStatusCountPresenterStub();

   $this->user
       ->expects($this->once())
       ->method('isAdmin')
       ->willReturn(true);

   $this->readResourceRepository
       ->expects($this->once())
       ->method('findResourcesStatusCount')
       ->willThrowException(new \Exception());

   $useCase($presenter, $this->resourceFilter);

    expect($presenter->data)
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($presenter->data->getMessage())
        ->toBe(ResourceException::errorWhileFindingHostsStatusCount()->getMessage());
});

it('should present a FindHostsStatusCountResponse when no error occurred', function () {
    $useCase = new FindHostsStatusCount(
        $this->user,
        $this->readAccessGroupRepository,
        $this->readResourceRepository
    );
    $presenter = new FindHostsStatusCountPresenterStub();

    $hostStatusCount = new HostsStatusCount(
        new DownStatusCount(3),
        new UnreachableStatusCount(5),
        new UpStatusCount(2),
        new PendingStatusCount(4)
    );
    $servicesStatusCount = null;
    $resourceStatusCount = new ResourcesStatusCount($hostStatusCount, $servicesStatusCount);

    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->readResourceRepository
        ->expects($this->once())
        ->method('findResourcesStatusCount')
        ->willReturn($resourceStatusCount);

    $useCase($presenter, $this->resourceFilter);

    $response = $presenter->data;
    expect($response)
        ->toBeInstanceOf(FindHostsStatusCountResponse::class)
        ->and($response->downStatus)->toBe(['total' => 3])
        ->and($response->unreachableStatus)->toBe(['total' => 5])
        ->and($response->upStatus)->toBe(['total' => 2])
        ->and($response->pendingStatus)->toBe(['total' => 4])
        ->and($response->total)->toBe(14);
});

it('should filter resources by ACL when the user is not admin', function () {
    $useCase = new FindHostsStatusCount(
        $this->user,
        $this->readAccessGroupRepository,
        $this->readResourceRepository
    );
    $presenter = new FindHostsStatusCountPresenterStub();

    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(false);

    $this->readResourceRepository
        ->expects($this->once())
        ->method('findResourcesStatusCountByAccessGroupIds');

    $useCase($presenter, $this->resourceFilter);
});