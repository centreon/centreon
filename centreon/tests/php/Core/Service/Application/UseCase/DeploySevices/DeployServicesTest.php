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

namespace Tests\Core\Service\Application\UseCase\DeployServices;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Host\Application\Repository\ReadHostRepositoryInterface;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;
use Core\Service\Application\Repository\ReadServiceRepositoryInterface;
use Core\Service\Application\Repository\WriteRealTimeServiceRepositoryInterface;
use Core\Service\Application\Repository\WriteServiceRepositoryInterface;
use Core\Service\Application\UseCase\DeployServices\DeployServices;
use Core\Service\Application\UseCase\DeployServices\DeployServicesResponse;
use Core\Service\Domain\Model\Service;
use Core\Service\Domain\Model\ServiceNamesByHost;
use Core\ServiceTemplate\Application\Repository\ReadServiceTemplateRepositoryInterface;
use Core\ServiceTemplate\Domain\Model\ServiceTemplate;
use Tests\Core\Service\Infrastructure\API\DeployServices\DeployServicesPresenterStub;

beforeEach(function (): void {
    $this->useCasePresenter = new DeployServicesPresenterStub(
        $this->presenterFormatter = $this->createMock(PresenterFormatterInterface::class)
    );
    $this->deployServicesUseCase = new DeployServices(
        $this->contact = $this->createMock(ContactInterface::class),
        $this->dataStorageEngine = $this->createMock(DataStorageEngineInterface::class),
        $this->readAccessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class),
        $this->readHostRepository = $this->createMock(ReadHostRepositoryInterface::class),
        $this->readServiceRepository = $this->createMock(ReadServiceRepositoryInterface::class),
        $this->readServiceTemplateRepository = $this->createMock(ReadServiceTemplateRepositoryInterface::class),
        $this->writeServiceRepository = $this->createMock(WriteServiceRepositoryInterface::class),
        $this->writeRealTimeServiceRepository = $this->createMock(WriteRealTimeServiceRepositoryInterface::class)
    );
});

it('should present a Forbidden Response when the user has insufficient rights', function () {
    $this->contact
        ->expects($this->any())
        ->method('hasTopologyRole')
        ->willReturn(false);

    $hostId = 15;
    ($this->deployServicesUseCase)($this->useCasePresenter, $hostId);

    expect($this->useCasePresenter->response)
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->useCasePresenter->response->getMessage())
        ->toBe('User does not have sufficient rights');
});

it('should present a Not Found Response when provided host ID does not exist for a non-admin user', function () {
    $this->contact
        ->expects($this->any())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $this->contact
        ->expects($this->any())
        ->method('isAdmin')
        ->willReturn(false);

    $accessGroups = [(new AccessGroup(1, 'nonAdmin', 'nonAdmin')), (new AccessGroup(3, 'SimpleUser', 'SimpleUser'))];
    $this->readAccessGroupRepository
        ->expects($this->once())
        ->method('findByContact')
        ->willReturn($accessGroups);

    $this->readHostRepository
        ->expects($this->once())
        ->method('existsByAccessGroups')
        ->willReturn(false);

    $hostId = 15;
    ($this->deployServicesUseCase)($this->useCasePresenter, $hostId);

    expect($this->useCasePresenter->response)
        ->toBeInstanceOf(NotFoundResponse::class)
        ->and($this->useCasePresenter->response->getMessage())
        ->toBe('Host not found');
});

it('should present a Not Found Response when provided host ID does not exist for a admin user', function () {
    $this->contact
        ->expects($this->any())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $this->contact
        ->expects($this->any())
        ->method('isAdmin')
        ->willReturn(true);

    $this->readHostRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(false);

    $hostId = 15;
    ($this->deployServicesUseCase)($this->useCasePresenter, $hostId);

    expect($this->useCasePresenter->response)
        ->toBeInstanceOf(NotFoundResponse::class)
        ->and($this->useCasePresenter->response->getMessage())
        ->toBe('Host not found');
});

it(
    'should present a No Content Response when provided host ID does not have associated host templates',
    function () {
        $this->contact
            ->expects($this->any())
            ->method('hasTopologyRole')
            ->willReturn(true);

        $this->contact
            ->expects($this->any())
            ->method('isAdmin')
            ->willReturn(false);

        $accessGroups = [(new AccessGroup(1, 'nonAdmin', 'nonAdmin')), (new AccessGroup(3, 'SimpleUser', 'SimpleUser'))];
        $this->readAccessGroupRepository
            ->expects($this->once())
            ->method('findByContact')
            ->willReturn($accessGroups);

        $this->readHostRepository
            ->expects($this->once())
            ->method('existsByAccessGroups')
            ->willReturn(true);

        $this->readHostRepository
            ->expects($this->once())
            ->method('findParents')
            ->willReturn([]);

        $hostId = 15;
        ($this->deployServicesUseCase)($this->useCasePresenter, $hostId);

        expect($this->useCasePresenter->response)
            ->toBeInstanceOf(NoContentResponse::class);
    }
);

it('should present a No Content Response when threre are no services to deploy', function () {
    $this->contact
        ->expects($this->any())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $this->contact
        ->expects($this->any())
        ->method('isAdmin')
        ->willReturn(false);

    $accessGroups = [(new AccessGroup(1, 'nonAdmin', 'nonAdmin')), (new AccessGroup(3, 'SimpleUser', 'SimpleUser'))];
    $this->readAccessGroupRepository
        ->expects($this->once())
        ->method('findByContact')
        ->willReturn($accessGroups);

    $this->readHostRepository
        ->expects($this->once())
        ->method('existsByAccessGroups')
        ->willReturn(true);

    $hostParents = [
        ['parent_id' => 3, 'child_id' => 5, 'order' => 0],
    ];

    $this->readHostRepository
        ->expects($this->once())
        ->method('findParents')
        ->willReturn($hostParents);

        $serviceTemplates = [
            (new ServiceTemplate(2, 'generic-ping', 'generic-ping')),
            (new ServiceTemplate(3, 'generic-disk', 'generic-disk')),
        ];
        $this->readServiceTemplateRepository
            ->expects($this->any())
            ->method('findByHostId')
            ->willReturn($serviceTemplates);

        $serviceNames = new ServiceNamesByHost(12, ['generic-ping', 'generic-disk']);
        $this->readServiceRepository
            ->expects($this->any())
            ->method('findServiceNamesByHost')
            ->willReturn($serviceNames);

    $hostId = 15;
    ($this->deployServicesUseCase)($this->useCasePresenter, $hostId);

    expect($this->useCasePresenter->response)
        ->toBeInstanceOf(NoContentResponse::class);
});

it('should present an Error Response when an unhandled error occurs', function () {
    $this->contact
        ->expects($this->any())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $this->contact
        ->expects($this->any())
        ->method('isAdmin')
        ->willReturn(false);

    $this->readAccessGroupRepository
        ->expects($this->once())
        ->method('findByContact')
        ->willThrowException(new \Exception());

    $hostId = 15;
    ($this->deployServicesUseCase)($this->useCasePresenter, $hostId);

    expect($this->useCasePresenter->response)
        ->toBeInstanceOf(ErrorResponse::class);
});

it('should present a Created Response when services were successfully deployed', function () {
    $this->contact
        ->expects($this->any())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $this->contact
        ->expects($this->any())
        ->method('isAdmin')
        ->willReturn(true);

    $this->readHostRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(true);

    $hostParents = [
        ['parent_id' => 3, 'child_id' => 5, 'order' => 0],
    ];
    $this->readHostRepository
        ->expects($this->once())
        ->method('findParents')
        ->willReturn($hostParents);

    $serviceTemplates = [
        (new ServiceTemplate(2, 'generic-ping', 'generic-ping')),
        (new ServiceTemplate(3, 'generic-disk', 'generic-disk')),
    ];
    $this->readServiceTemplateRepository
        ->expects($this->any())
        ->method('findByHostId')
        ->willReturn($serviceTemplates);

    $serviceNames = new ServiceNamesByHost(15, []);
    $this->readServiceRepository
        ->expects($this->any())
        ->method('findServiceNamesByHost')
        ->willReturn($serviceNames);

    $hostId = 15;

    $createdPing = new Service(16, 'generic-ping', $hostId);
    $createdDisk = new Service(17, 'generic-disk', $hostId);

    $this->readServiceRepository
        ->expects($this->any())
        ->method('findById')
        ->willReturn($createdPing);

    $this->readServiceRepository
        ->expects($this->any())
        ->method('findById')
        ->willReturn($createdDisk);

    $this->dataStorageEngine
        ->expects($this->once())
        ->method('commitTransaction')
        ->willReturn(true);

    ($this->deployServicesUseCase)($this->useCasePresenter, $hostId);

    expect($this->useCasePresenter->response)
        ->toBeInstanceOf(DeployServicesResponse::class)
        ->and($this->useCasePresenter->response->services)
        ->toBeArray();
});
