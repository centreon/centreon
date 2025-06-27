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

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Common\Domain\ResponseCodeEnum;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\HostGroup\Application\Exceptions\HostGroupException;
use Core\HostGroup\Application\Repository\ReadHostGroupRepositoryInterface;
use Core\HostGroup\Application\Repository\WriteHostGroupRepositoryInterface;
use Core\HostGroup\Application\UseCase\DeleteHostGroups\DeleteHostGroups;
use Core\HostGroup\Application\UseCase\DeleteHostGroups\DeleteHostGroupsRequest;
use Core\HostGroup\Application\UseCase\DeleteHostGroups\DeleteHostGroupsResponse;
use Core\Notification\Application\Repository\ReadNotificationRepositoryInterface;
use Core\Notification\Application\Repository\WriteNotificationRepositoryInterface;
use Core\ResourceAccess\Application\Repository\ReadResourceAccessRepositoryInterface;
use Core\ResourceAccess\Application\Repository\WriteResourceAccessRepositoryInterface;
use Core\Service\Application\Repository\ReadServiceRepositoryInterface;
use Core\Service\Application\Repository\WriteServiceRepositoryInterface;

beforeEach(function (): void {
    $this->useCase = new DeleteHostGroups(
        $this->contact = $this->createMock(ContactInterface::class),
        $this->writeRepository = $this->createMock(WriteHostGroupRepositoryInterface::class),
        $this->readRepository = $this->createMock(ReadHostGroupRepositoryInterface::class),
        $this->readAccessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class),
        $this->readNotificationRepository = $this->createMock(ReadNotificationRepositoryInterface::class),
        $this->writeNotificationRepository = $this->createMock(WriteNotificationRepositoryInterface::class),
        $this->readServiceRepository = $this->createMock(ReadServiceRepositoryInterface::class),
        $this->writeServiceRepository = $this->createMock(WriteServiceRepositoryInterface::class),
        $this->readResourceAccessRepository = $this->createMock(ReadResourceAccessRepositoryInterface::class),
        $this->writeResourceAccessRepository = $this->createMock(WriteResourceAccessRepositoryInterface::class),
        $this->storageEngine = $this->createMock(DataStorageEngineInterface::class),
        $this->isCloudPlatform = false
    );

    $this->request = new DeleteHostGroupsRequest([1, 2, 3]);
});

it('should check that HostGroups exists as admin', function (): void {

    $this->contact
        ->expects($this->any())
        ->method('isAdmin')
        ->willReturn(true);

    $this->readRepository
        ->expects($this->exactly(3))
        ->method('existsOne');

    ($this->useCase)($this->request);
});

it('should check that HostGroups exists as user', function (): void {

    $this->contact
        ->expects($this->any())
        ->method('isAdmin')
        ->willReturn(false);

    $this->readRepository
        ->expects($this->exactly(3))
        ->method('existsOneByAccessGroups');

    ($this->useCase)($this->request);
});

it('should return a DeleteHostGroupsResponse', function (): void {
    $this->contact
        ->expects($this->any())
        ->method('isAdmin')
        ->willReturn(true);

    $this->readRepository
        ->expects($this->exactly(3))
        ->method('existsOne')
        ->willReturnOnConsecutiveCalls(true,false,true);

    $ex = new \Exception('Error while deleting a HostGroup configuration');

    $this->writeRepository
        ->expects($this->exactly(2))
        ->method('deleteHostGroup')
        ->will($this->onConsecutiveCalls(null, $this->throwException($ex)));

    $response = ($this->useCase)($this->request);

    expect($response)->toBeInstanceOf(DeleteHostGroupsResponse::class)
        ->and($response->getData())->toBeArray()
        ->and($response->getData())->toHaveCount(3)
        ->and($response->getData()[0]->id)->toBe(1)
        ->and($response->getData()[0]->status)->toBe(ResponseCodeEnum::OK)
        ->and($response->getData()[0]->message)->toBeNull()
        ->and($response->getData()[1]->id)->toBe(2)
        ->and($response->getData()[1]->status)->toBe(ResponseCodeEnum::NotFound)
        ->and($response->getData()[1]->message)->toBe((new NotFoundResponse('Host Group'))->getMessage())
        ->and($response->getData()[2]->id)->toBe(3)
        ->and($response->getData()[2]->status)->toBe(ResponseCodeEnum::Error)
        ->and($response->getData()[2]->message)->toBe(HostGroupException::errorWhileDeleting()->getMessage());
});