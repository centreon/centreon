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

namespace Tests\Core\HostGroup\Application\UseCase\FindHostGroups;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Domain\Common\GeoCoords;
use Core\HostGroup\Application\Exceptions\HostGroupException;
use Core\HostGroup\Application\Repository\ReadHostGroupRepositoryInterface;
use Core\HostGroup\Application\UseCase\FindHostGroups\FindHostGroups;
use Core\HostGroup\Application\UseCase\FindHostGroups\FindHostGroupsResponse;
use Core\HostGroup\Application\UseCase\FindHostGroups\HostGroupResponse;
use Core\HostGroup\Domain\Model\HostGroup;
use Core\HostGroup\Domain\Model\HostGroupRelationCount;
use Core\Media\Application\Repository\ReadMediaRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

beforeEach(function (): void {
    $this->useCase = new FindHostGroups(
        $this->readHostGroupRepository = $this->createMock(ReadHostGroupRepositoryInterface::class),
        $this->readAccessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class),
        $this->readMediaRepository = $this->createMock(ReadMediaRepositoryInterface::class),
        $this->createMock(RequestParametersInterface::class),
        $this->contact = $this->createMock(ContactInterface::class),
    );

    $this->hostCounts = new HostGroupRelationCount(1, 2);

    $this->hostGroup = new HostGroup(
        id: 1,
        name: 'hg-name',
        alias: 'hg-alias',
        geoCoords: $this->geoCoords = GeoCoords::fromString('-2,100'),
    );

    $this->hostGroupResponse = new HostGroupResponse(
        $this->hostGroup,
        $this->hostCounts,
    );
});

it(
    'should present an ErrorResponse when an exception is thrown',
    function (): void {
        $this->contact
            ->expects($this->once())
            ->method('isAdmin')
            ->willReturn(true);
        $this->readHostGroupRepository
            ->expects($this->once())
            ->method('findAll')
            ->willThrowException(new \Exception());

        $response = ($this->useCase)();

        expect($response)
            ->toBeInstanceOf(ErrorResponse::class)
            ->and($response->getMessage())
            ->toBe(HostGroupException::errorWhileSearching()->getMessage());
    }
);

it(
    'should present a FindHostGroupsResponse as admin',
    function (): void {
        $this->contact
            ->expects($this->once())
            ->method('isAdmin')
            ->willReturn(true);
        $this->readHostGroupRepository
            ->expects($this->once())
            ->method('findAll')
            ->willReturn(new \ArrayIterator([$this->hostGroup]));
        $this->readHostGroupRepository
            ->expects($this->once())
            ->method('findHostsCountByIds')
            ->willReturn([$this->hostGroup->getId() => $this->hostCounts]);

        $response = ($this->useCase)();

        expect($response)
            ->toBeInstanceOf(FindHostGroupsResponse::class)
            ->and($response->hostgroups[0]->hostgroup)
            ->toBe($this->hostGroup)
            ->and($response->hostgroups[0]->hostsCount)
            ->toBe($this->hostCounts);
    }
);

it(
    'should present a FindHostGroupsResponse as non-admin',
    function (): void {
        $this->contact
            ->expects($this->once())
            ->method('isAdmin')
            ->willReturn(false);

        $this->readAccessGroupRepository
            ->expects($this->any())
            ->method('findByContact')
            ->willReturn([new AccessGroup(id: 1, name: 'testName', alias: 'testAlias')]);
        $this->readHostGroupRepository
            ->expects($this->once())
            ->method('hasAccessToAllHostGroups')
            ->willReturn(false);
        $this->readHostGroupRepository
            ->expects($this->once())
            ->method('findAllByAccessGroupIds')
            ->willReturn(new \ArrayIterator([$this->hostGroup]));
        $this->readHostGroupRepository
            ->expects($this->once())
            ->method('findHostsCountByAccessGroupsIds')
            ->willReturn([$this->hostGroup->getId() => $this->hostCounts]);

        $response = ($this->useCase)();

        expect($response)
            ->toBeInstanceOf(FindHostGroupsResponse::class)
            ->and($response->hostgroups[0]->hostgroup)
            ->toBe($this->hostGroup)
            ->and($response->hostgroups[0]->hostsCount)
            ->toBe($this->hostCounts);
    }
);
