<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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

namespace Tests\Core\HostGroup\Application\UseCase\DuplicateHostGroups;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Common\Domain\ResponseCodeEnum;
use Core\HostGroup\Application\Exceptions\HostGroupException;
use Core\HostGroup\Application\Repository\ReadHostGroupRepositoryInterface;
use Core\HostGroup\Application\Repository\WriteHostGroupRepositoryInterface;
use Core\HostGroup\Application\UseCase\DuplicateHostGroups\DuplicateHostGroups;
use Core\HostGroup\Application\UseCase\DuplicateHostGroups\DuplicateHostGroupsRequest;
use Core\HostGroup\Application\UseCase\DuplicateHostGroups\DuplicateHostGroupsResponse;
use Core\HostGroup\Domain\Model\HostGroupNamesById;
use Core\MonitoringServer\Application\Repository\ReadMonitoringServerRepositoryInterface;
use Core\MonitoringServer\Application\Repository\WriteMonitoringServerRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\WriteAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

beforeEach(function () {
    $this->useCase = new DuplicateHostGroups(
        $this->contact = $this->createMock(ContactInterface::class),
        $this->readHostGroupRepository = $this->createMock(ReadHostGroupRepositoryInterface::class),
        $this->writeHostGroupRepository = $this->createMock(WriteHostGroupRepositoryInterface::class),
        $this->readAccessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class),
        $this->writeAccessGroupRepository = $this->createMock(WriteAccessGroupRepositoryInterface::class),
        $this->readMonitoringServerRepository = $this->createMock(ReadMonitoringServerRepositoryInterface::class),
        $this->writeMonitoringServerRepository = $this->createMock(WriteMonitoringServerRepositoryInterface::class)
    );

    $this->accessGroups = [$this->createMock(AccessGroup::class)];
    $this->request = new DuplicateHostGroupsRequest([1, 2], 1);

    $this->hostGroupNames = $this->createMock(HostGroupNamesById::class);
    $this->hostGroupNames->method('getName')->willReturn('test_hg');
});

it('should handle host group not found', function (): void {
    $this->contact
        ->expects($this->exactly(2))
        ->method('isAdmin')
        ->willReturn(true);

    $this->readHostGroupRepository
        ->expects($this->exactly(2))
        ->method('existsOne')
        ->willReturn(false);

    $response = ($this->useCase)($this->request);

    expect($response)->toBeInstanceOf(DuplicateHostGroupsResponse::class)
        ->and($response->getData()[0]->status)->toBe(ResponseCodeEnum::NotFound)
        ->and($response->getData()[0]->message)->toBe((new NotFoundResponse('Host Group'))->getMessage());
});

it('should handle exception during duplication', function (): void {
    $this->contact
        ->expects($this->exactly(4))
        ->method('isAdmin')
        ->willReturn(true);

    $this->readHostGroupRepository
        ->expects($this->exactly(2))
        ->method('existsOne')
        ->willReturn(true);

    $this->readHostGroupRepository
        ->expects($this->exactly(2))
        ->method('findNames')
        ->willReturn($this->hostGroupNames);

    $this->readHostGroupRepository
        ->expects($this->exactly(2))
        ->method('nameAlreadyExists')
        ->willReturn(false);

    $this->writeHostGroupRepository
        ->expects($this->exactly(2))
        ->method('duplicate')
        ->willThrowException(new \Exception('Database error'));

    $response = ($this->useCase)($this->request);

    expect($response)->toBeInstanceOf(DuplicateHostGroupsResponse::class)
        ->and($response->getData()[0]->status)->toBe(ResponseCodeEnum::Error)
        ->and($response->getData()[0]->message)->toBe(HostGroupException::errorWhileDuplicating()->getMessage());
});

it('should successfully duplicate host group as admin', function (): void {
    $this->contact
        ->expects($this->atLeastOnce())
        ->method('isAdmin')
        ->willReturn(true);

    $this->readHostGroupRepository
        ->expects($this->exactly(2))
        ->method('existsOne')
        ->willReturn(true);

    $this->readHostGroupRepository
        ->expects($this->exactly(2))
        ->method('findNames')
        ->willReturn($this->hostGroupNames);

    $this->readHostGroupRepository
        ->expects($this->exactly(2))
        ->method('nameAlreadyExists')
        ->willReturn(false);

    $this->writeHostGroupRepository
        ->expects($this->exactly(2))
        ->method('duplicate')
        ->willReturn(100);

    $this->readHostGroupRepository
        ->expects($this->exactly(2))
        ->method('findLinkedHosts')
        ->willReturn([1, 2]);

    $this->readMonitoringServerRepository
        ->expects($this->exactly(2))
        ->method('findByHostsIds')
        ->willReturn([10]);

    $this->writeMonitoringServerRepository
        ->expects($this->exactly(2))
        ->method('notifyConfigurationChanges')
        ->with([10]);

    $this->writeAccessGroupRepository
        ->expects($this->exactly(2))
        ->method('updateAclResourcesFlag');

    $this->writeAccessGroupRepository
        ->expects($this->never())
        ->method('addLinksBetweenHostGroupAndAccessGroups');

    $response = ($this->useCase)($this->request);

    expect($response)->toBeInstanceOf(DuplicateHostGroupsResponse::class)
        ->and($response->getData()[0]->status)->toBe(ResponseCodeEnum::OK);
});

it('should successfully duplicate host group as non-admin', function (): void {
    $this->contact
        ->expects($this->atLeastOnce())
        ->method('isAdmin')
        ->willReturn(false);

    $this->readAccessGroupRepository
        ->expects($this->atLeastOnce())
        ->method('findByContact')
        ->willReturn($this->accessGroups);

    $this->readHostGroupRepository
        ->expects($this->exactly(2))
        ->method('existsOneByAccessGroups')
        ->willReturn(true);

    $this->readHostGroupRepository
        ->expects($this->exactly(2))
        ->method('findNames')
        ->willReturn($this->hostGroupNames);

    $this->readHostGroupRepository
        ->expects($this->exactly(2))
        ->method('nameAlreadyExistsByAccessGroups')
        ->willReturn(false);

    $this->writeHostGroupRepository
        ->expects($this->exactly(2))
        ->method('duplicate')
        ->willReturn(100);

    $this->readHostGroupRepository
        ->expects($this->exactly(2))
        ->method('findLinkedHosts')
        ->willReturn([1, 2]);

    // Non-admin users should call duplicateContactAclResources
    $this->writeAccessGroupRepository
        ->expects($this->exactly(2))
        ->method('addLinksBetweenHostGroupAndAccessGroups')
        ->with(100, $this->accessGroups);

    $this->readMonitoringServerRepository
        ->expects($this->exactly(2))
        ->method('findByHostsIds')
        ->willReturn([10]);

    $this->writeMonitoringServerRepository
        ->expects($this->exactly(2))
        ->method('notifyConfigurationChanges');

    $this->writeAccessGroupRepository
        ->expects($this->exactly(2))
        ->method('updateAclGroupsFlag')
        ->with($this->accessGroups);

    $this->readAccessGroupRepository
        ->expects($this->exactly(2))
        ->method('findAclResourcesByHostGroupId')
        ->willReturn([]);

    $response = ($this->useCase)($this->request);

    expect($response)->toBeInstanceOf(DuplicateHostGroupsResponse::class)
        ->and($response->getData()[0]->status)->toBe(ResponseCodeEnum::OK);
});

it('should duplicate multiple host groups', function (): void {
    $this->request = new DuplicateHostGroupsRequest([62, 63], 1);

    $this->contact
        ->expects($this->atLeastOnce())
        ->method('isAdmin')
        ->willReturn(true);

    $this->readHostGroupRepository
        ->expects($this->exactly(2))
        ->method('existsOne')
        ->willReturn(true);

    $this->readHostGroupRepository
        ->expects($this->exactly(2))
        ->method('findNames')
        ->willReturn($this->hostGroupNames);

    $this->readHostGroupRepository
        ->expects($this->exactly(2))
        ->method('nameAlreadyExists')
        ->willReturn(false);

    $this->writeHostGroupRepository
        ->expects($this->exactly(2))
        ->method('duplicate')
        ->willReturnOnConsecutiveCalls(100, 101);

    $response = ($this->useCase)($this->request);

    expect($response)->toBeInstanceOf(DuplicateHostGroupsResponse::class)
        ->and($response->getData())->toHaveCount(2)
        ->and($response->getData()[0]->status)->toBe(ResponseCodeEnum::OK)
        ->and($response->getData()[1]->status)->toBe(ResponseCodeEnum::OK);
});

it('should create multiple duplicates when requested', function (): void {
    $this->request = new DuplicateHostGroupsRequest([62], 2);

    $this->contact
        ->expects($this->atLeastOnce())
        ->method('isAdmin')
        ->willReturn(true);

    $this->readHostGroupRepository
        ->expects($this->once())
        ->method('existsOne')
        ->willReturn(true);

    $this->readHostGroupRepository
        ->expects($this->once())
        ->method('findNames')
        ->willReturn($this->hostGroupNames);

    $this->readHostGroupRepository
        ->expects($this->exactly(2))
        ->method('nameAlreadyExists')
        ->willReturn(false);

    $this->writeHostGroupRepository
        ->expects($this->exactly(2))
        ->method('duplicate')
        ->with(62, $this->anything())
        ->willReturnOnConsecutiveCalls(100, 101);

    $response = ($this->useCase)($this->request);

    expect($response)->toBeInstanceOf(DuplicateHostGroupsResponse::class)
        ->and($response->getData())->toHaveCount(1)
        ->and($response->getData()[0]->status)->toBe(ResponseCodeEnum::OK);
});