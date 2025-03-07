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

namespace Tests\Core\HostGroup\Application\UseCase\UpdateHostGroup;

use Centreon\Domain\Common\Assertion\AssertionException;
use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\Application\Common\UseCase\ConflictResponse;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Common\Domain\SimpleEntity;
use Core\Common\Domain\TrimmedString;
use Core\Domain\Common\GeoCoords;
use Core\Domain\Exception\InvalidGeoCoordException;
use Core\Host\Application\Exception\HostException;
use Core\Host\Application\Repository\ReadHostRepositoryInterface;
use Core\HostGroup\Application\Exceptions\HostGroupException;
use Core\HostGroup\Application\Repository\ReadHostGroupRepositoryInterface;
use Core\HostGroup\Application\Repository\WriteHostGroupRepositoryInterface;
use Core\HostGroup\Application\UseCase\UpdateHostGroup\UpdateHostGroup;
use Core\HostGroup\Application\UseCase\UpdateHostGroup\UpdateHostGroupRequest;
use Core\HostGroup\Application\UseCase\UpdateHostGroup\UpdateHostGroupValidator;
use Core\HostGroup\Domain\Model\HostGroup;
use Core\MonitoringServer\Application\Repository\ReadMonitoringServerRepositoryInterface;
use Core\MonitoringServer\Application\Repository\WriteMonitoringServerRepositoryInterface;
use Core\ResourceAccess\Application\Repository\ReadResourceAccessRepositoryInterface;
use Core\ResourceAccess\Application\Repository\WriteResourceAccessRepositoryInterface;
use Core\ResourceAccess\Domain\Model\DatasetFilter\DatasetFilterRelation;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\WriteAccessGroupRepositoryInterface;

beforeEach(function (): void {
    $this->useCase = new UpdateHostGroup(
        $this->user = $this->createMock(ContactInterface::class),
        $this->validator = $this->createMock(UpdateHostGroupValidator::class),
        $this->storageEngine = $this->createMock(DataStorageEngineInterface::class),
        $this->isCloudPlatform = true,
        $this->readHostGroupRepository = $this->createMock(ReadHostGroupRepositoryInterface::class),
        $this->readHostRepository = $this->createMock(ReadHostRepositoryInterface::class),
        $this->readAccessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class),
        $this->readResourceAccessRepository = $this->createMock(ReadResourceAccessRepositoryInterface::class),
        $this->readMonitoringServerRepository = $this->createMock(ReadMonitoringServerRepositoryInterface::class),
        $this->writeHostGroupRepository = $this->createMock(WriteHostGroupRepositoryInterface::class),
        $this->writeResourceAccessRepository = $this->createMock(WriteResourceAccessRepositoryInterface::class),
        $this->writeMonitoringServerRepository = $this->createMock(WriteMonitoringServerRepositoryInterface::class),
        $this->writeAccessGroupRepository = $this->createMock(WriteAccessGroupRepositoryInterface::class)
    );

    $this->updateHostGroupRequest = new UpdateHostGroupRequest(
        id: 1,
        name: 'name update',
        alias: 'alias update',
        geoCoords: '-10,10',
        comment: 'comment',
        hosts: [1, 2],
        resourceAccessRules: [1, 2]
    );
});

it(
    'should present a NotFoundResponse when the hostgroup does not exists',
    function (): void {
        $this->user
            ->expects($this->once())
            ->method('isAdmin')
            ->willReturn(true);
        $this->readHostGroupRepository
            ->expects($this->once())
            ->method('findOne')
            ->willReturn(null);

        $response = ($this->useCase)($this->updateHostGroupRequest);

        expect($response)
            ->toBeInstanceOf(NotFoundResponse::class);
    }
);

it(
    'should return an InvalidArgumentResponse When an hostgroup already exists with this name',
    function (): void {
        $this->user
            ->expects($this->once())
            ->method('isAdmin')
            ->willReturn(true);
        $this->readHostGroupRepository
            ->expects($this->once())
            ->method('findOne')
            ->willReturn(new HostGroup(
                id: 1,
                name: 'name',
                alias: 'alias',
                geoCoords: GeoCoords::fromString('-10,10'),
                comment: 'comment',
            ));

        $this->validator
            ->expects($this->once())
            ->method('assertNameDoesNotAlreadyExists')
            ->willThrowException(HostGroupException::nameAlreadyExists($this->updateHostGroupRequest->name));

        $response = ($this->useCase)($this->updateHostGroupRequest);

        expect($response)
            ->toBeInstanceOf(InvalidArgumentResponse::class)
            ->and($response->getMessage())
            ->toBe(HostGroupException::nameAlreadyExists($this->updateHostGroupRequest->name)->getMessage());
    }
);

it(
    "should return an InvalidArgumentResponse When a given host doesn't exist",
    function (): void {
        $this->user
            ->expects($this->once())
            ->method('isAdmin')
            ->willReturn(true);
        $this->readHostGroupRepository
            ->expects($this->once())
            ->method('findOne')
            ->willReturn(new HostGroup(
                id: 1,
                name: 'name',
                alias: 'alias',
                geoCoords: GeoCoords::fromString('-10,10'),
                comment: 'comment',
            ));
        $this->validator
            ->expects($this->once())
            ->method('assertHostsExist')
            ->willThrowException(HostException::idsDoNotExist('hosts', [2]));

        $response = ($this->useCase)($this->updateHostGroupRequest);

        expect($response)
            ->toBeInstanceOf(InvalidArgumentResponse::class)
            ->and($response->getMessage())
            ->toBe(HostException::idsDoNotExist('hosts', [2])->getMessage());
    }
);

it(
    'should return an InvalidArgumentResponse When a given resource access rule does not exist',
    function (): void {
        $this->user
            ->expects($this->once())
            ->method('isAdmin')
            ->willReturn(true);
        $this->readHostGroupRepository
            ->expects($this->once())
            ->method('findOne')
            ->willReturn(new HostGroup(
                id: 1,
                name: 'name',
                alias: 'alias',
                geoCoords: GeoCoords::fromString('-10,10'),
                comment: 'comment',
            ));
        $this->validator
            ->expects($this->once())
            ->method('assertResourceAccessRulesExist')
            ->willThrowException(HostException::idsDoNotExist('resourceAccessRules', [2]));

        $response = ($this->useCase)($this->updateHostGroupRequest);

        expect($response)
            ->toBeInstanceOf(InvalidArgumentResponse::class)
            ->and($response->getMessage())
            ->toBe(HostException::idsDoNotExist('resourceAccessRules', [2])->getMessage());
    }
);

it(
    'should update the host group configuration',
    function (): void {
        $this->user
            ->expects($this->any())
            ->method('isAdmin')
            ->willReturn(true);
        $this->readHostGroupRepository
            ->expects($this->once())
            ->method('findOne')
            ->willReturn(new HostGroup(
                id: 1,
                name: 'name',
                alias: 'alias',
                geoCoords: GeoCoords::fromString('-10,10'),
                comment: 'comment',
            ));

        $this->writeHostGroupRepository
            ->expects($this->once())
            ->method('update')
            ->with(new HostGroup(
                id: 1,
                name: 'name update',
                alias: 'alias update',
                geoCoords: GeoCoords::fromString('-10,10'),
                comment: 'comment',
            ));

        $response = ($this->useCase)($this->updateHostGroupRequest);

        expect($response)
            ->toBeInstanceOf(NoContentResponse::class);
    }
);

it(
    'should update the hosts of the host group',
    function (): void {
        $this->user
            ->expects($this->any())
            ->method('isAdmin')
            ->willReturn(true);
        $this->readHostGroupRepository
            ->expects($this->once())
            ->method('findOne')
            ->willReturn(new HostGroup(
                id: 1,
                name: 'name',
                alias: 'alias',
                geoCoords: GeoCoords::fromString('-10,10'),
                comment: 'comment',
            ));
        $this->readHostRepository
            ->expects($this->once())
            ->method('findByHostGroup')
            ->willReturn([
                new SimpleEntity(
                    id: 1,
                    name: new TrimmedString('host'),
                    objectName: 'Host',
                ),
            ]);
        $this->writeHostGroupRepository
            ->expects($this->once())
            ->method('deleteHosts')
            ->with($this->updateHostGroupRequest->id, [1]);
        $this->writeHostGroupRepository
            ->expects($this->once())
            ->method('addHosts')
            ->with($this->updateHostGroupRequest->id, [1,2]);

        $response = ($this->useCase)($this->updateHostGroupRequest);

        expect($response)
            ->toBeInstanceOf(NoContentResponse::class);
    }
);

it(
    'should update the resource access rules of the host group',
    function (): void {
        $this->user
            ->expects($this->any())
            ->method('isAdmin')
            ->willReturn(true);
        $this->readHostGroupRepository
            ->expects($this->once())
            ->method('findOne')
            ->willReturn(new HostGroup(
                id: 1,
                name: 'name',
                alias: 'alias',
                geoCoords: GeoCoords::fromString('-10,10'),
                comment: 'comment',
            ));
        $this->readResourceAccessRepository
            ->expects($this->once())
            ->method('existByTypeAndResourceId')
            ->willReturn([
                1,2,3
            ]);

        $this->readResourceAccessRepository
            ->expects($this->exactly(2))
            ->method('findLastLevelDatasetFilterByRuleIdsAndType')
            ->willReturnOnConsecutiveCalls(
                [
                    new DatasetFilterRelation(
                        datasetFilterId: 1,
                        datasetFilterType: 'hostgroup',
                        parentId: null,
                        resourceAccessGroupId: 1,
                        aclGroupId: 1,
                        resourceIds: [1,2,3]
                    ),
                    new DatasetFilterRelation(
                        datasetFilterId: 2,
                        datasetFilterType: 'hostgroup',
                        parentId: null,
                        resourceAccessGroupId: 2,
                        aclGroupId: 2,
                        resourceIds: [1,5,6]
                    ),
                ],
                []
            );

        $this->writeResourceAccessRepository
            ->expects($this->exactly(2))
            ->method('updateDatasetResources');

        $response = ($this->useCase)($this->updateHostGroupRequest);
        dump($response);
        expect($response)
            ->toBeInstanceOf(NoContentResponse::class);
    }
);