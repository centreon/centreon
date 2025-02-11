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
use Core\MonitoringServer\Application\Repository\WriteMonitoringServerRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Service\Application\Exception\ServiceException;
use Core\Service\Application\Repository\ReadServiceRepositoryInterface;
use Core\Service\Application\Repository\WriteServiceRepositoryInterface;
use Core\Service\Application\UseCase\DeleteServices\DeleteServices;
use Core\Service\Application\UseCase\DeleteServices\DeleteServicesRequest;
use Core\Service\Application\UseCase\DeleteServices\DeleteServicesResponse;

beforeEach(function () {
    $this->useCase = new DeleteServices(
        $this->contact = $this->createMock(ContactInterface::class),
        $this->writeRepository = $this->createMock(WriteServiceRepositoryInterface::class),
        $this->readRepository = $this->createMock(ReadServiceRepositoryInterface::class),
        $this->writeMonitoringServerRepository = $this->createMock(WriteMonitoringServerRepositoryInterface::class),
        $this->readAccessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class),
        $this->storageEngine = $this->createMock(DataStorageEngineInterface::class)
    );

    $this->request = new DeleteServicesRequest([1, 2, 3]);
});

it('should check that services exists as admin', function () {

    $this->contact
        ->expects($this->any())
        ->method('isAdmin')
        ->willReturn(true);

    $this->readRepository
        ->expects($this->exactly(3))
        ->method('exists');

    ($this->useCase)($this->request);
});

it('should check that services exists as user', function () {

    $this->contact
        ->expects($this->any())
        ->method('isAdmin')
        ->willReturn(false);

    $this->readRepository
        ->expects($this->exactly(3))
        ->method('existsByAccessGroups');

    ($this->useCase)($this->request);
});

it('should return a DeleteServicesResponse', function () {
    $this->contact
        ->expects($this->any())
        ->method('isAdmin')
        ->willReturn(true);

    $this->readRepository
        ->expects($this->exactly(3))
        ->method('exists')
        ->willReturnOnConsecutiveCalls(true,false,true);

    $ex = new \Exception('Error while deleting a service configuration');

    $this->writeRepository
        ->expects($this->exactly(2))
        ->method('delete')
        ->will($this->onConsecutiveCalls(null, $this->throwException($ex)));

    $response = ($this->useCase)($this->request);

    expect($response)->toBeInstanceOf(DeleteServicesResponse::class)
        ->and($response->getData())->toBeArray()
        ->and($response->getData())->toHaveCount(3)
        ->and($response->getData()[0]->id)->toBe(1)
        ->and($response->getData()[0]->status)->toBe(ResponseCodeEnum::OK)
        ->and($response->getData()[0]->message)->toBeNull()
        ->and($response->getData()[1]->id)->toBe(2)
        ->and($response->getData()[1]->status)->toBe(ResponseCodeEnum::NotFound)
        ->and($response->getData()[1]->message)->toBe((new NotFoundResponse('Service'))->getMessage())
        ->and($response->getData()[2]->id)->toBe(3)
        ->and($response->getData()[2]->status)->toBe(ResponseCodeEnum::Error)
        ->and($response->getData()[2]->message)->toBe(ServiceException::errorWhileDeleting($ex)->getMessage());
});