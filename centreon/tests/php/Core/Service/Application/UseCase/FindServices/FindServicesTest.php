<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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

namespace Tests\Core\Service\Application\UseCase\FindServices;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Common\Domain\TrimmedString;
use Core\Host\Application\Repository\ReadHostRepositoryInterface;
use Core\Host\Domain\Model\HostNamesById;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Service\Application\Exception\ServiceException;
use Core\Service\Application\Repository\ReadServiceRepositoryInterface;
use Core\Service\Application\UseCase\FindServices\FindServices;
use Core\Service\Application\UseCase\FindServices\FindServicesResponse;
use Core\Service\Application\UseCase\FindServices\ServiceDto;
use Core\Service\Domain\Model\ServiceLight;
use Core\ServiceCategory\Application\Repository\ReadServiceCategoryRepositoryInterface;
use Core\ServiceCategory\Domain\Model\ServiceCategoryNamesById;
use Core\ServiceGroup\Application\Repository\ReadServiceGroupRepositoryInterface;
use Core\ServiceGroup\Domain\Model\ServiceGroupNamesById;
use Core\ServiceGroup\Domain\Model\ServiceGroupRelation;
use Tests\Core\Service\Infrastructure\API\FindServices\FindServicesPresenterStub;

beforeEach(function (): void {
    $this->usecase = new FindServices(
        $this->readAccessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class),
        $this->readServiceRepository = $this->createMock(ReadServiceRepositoryInterface::class),
        $this->readHostRepository = $this->createMock(ReadHostRepositoryInterface::class),
        $this->readServiceCategoryRepository = $this->createMock(ReadServiceCategoryRepositoryInterface::class),
        $this->readServiceGroupRepository = $this->createMock(ReadServiceGroupRepositoryInterface::class),
        $this->requestParameters = $this->createMock(RequestParametersInterface::class),
        $this->user = $this->createMock(ContactInterface::class),
    );
    $this->presenter = new FindServicesPresenterStub(
        $this->presenterFormatter = $this->createMock(PresenterFormatterInterface::class)
    );

    $this->service = new ServiceLight(
        id: 1,
        name: new TrimmedString('my-service-name'),
        hostIds: [1],
        categoryIds: [2],
        groups: [new ServiceGroupRelation(3, 1, 1)],
        serviceTemplate: null,
        notificationTimePeriod: null,
        checkTimePeriod: null,
        severity: null,
        normalCheckInterval: null,
        retryCheckInterval: null,
        isActivated: true,
    );

    $this->hostNames = new HostNamesById();
    $this->hostNames->addName(1, new TrimmedString('host-name'));

    $this->categoryNames = new ServiceCategoryNamesById();
    $this->categoryNames->addName(2, new TrimmedString('category-name'));

    $this->groupNames = new ServiceGroupNamesById();
    $this->groupNames->addName(3, new TrimmedString('group-name'));

    $this->responseDto = new ServiceDto();
    $this->responseDto->id = 1;
    $this->responseDto->name = 'my-service-name';
    $this->responseDto->isActivated = true;
    $this->responseDto->normalCheckInterval = null;
    $this->responseDto->retryCheckInterval = null;
    $this->responseDto->serviceTemplate = null;
    $this->responseDto->checkTimePeriod = null;
    $this->responseDto->notificationTimePeriod = null;
    $this->responseDto->severity = null;
    $this->responseDto->hosts = [
        ['id' => 1, 'name' => 'host-name'],
    ];
    $this->responseDto->categories = [
        ['id' => 2, 'name' => 'category-name'],
    ];
    $this->responseDto->groups = [
        ['id' => 3, 'name' => 'group-name', 'hostId' => 1, 'hostName' => 'host-name'],
    ];
});

it('should present an ErrorResponse when an exception is thrown', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->readServiceRepository
        ->expects($this->once())
        ->method('findByRequestParameter')
        ->willThrowException(new \Exception());

    ($this->usecase)($this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(ServiceException::errorWhileSearching(new \Exception())->getMessage());
});

it('should present a ForbiddenResponse when user has insufficient rights', function (): void {
    $this->user
        ->expects($this->exactly(2))
        ->method('hasTopologyRole')
        ->willReturn(false);

    ($this->usecase)($this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(ServiceException::accessNotAllowed()->getMessage());
});

it('should present a FindServicesResponse with non-admin user', function (): void {
    $this->user
        ->expects($this->exactly(2))
        ->method('hasTopologyRole')
        ->willReturnMap(
            [
                [Contact::ROLE_CONFIGURATION_SERVICES_READ, false],
                [Contact::ROLE_CONFIGURATION_SERVICES_WRITE, true],
            ]
        );
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(false);

    $this->readAccessGroupRepository
        ->expects($this->once())
        ->method('findByContact');
    $this->readServiceRepository
        ->expects($this->once())
        ->method('findByRequestParameterAndAccessGroup')
        ->willReturn([$this->service]);

    $this->readServiceCategoryRepository
        ->expects($this->once())
        ->method('findNames')
        ->willReturn($this->categoryNames);
    $this->readServiceGroupRepository
        ->expects($this->once())
        ->method('findNames')
        ->willReturn($this->groupNames);
    $this->readHostRepository
        ->expects($this->once())
        ->method('findNames')
        ->willReturn($this->hostNames);

    ($this->usecase)($this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(FindServicesResponse::class)
        ->and($this->presenter->response->services[0]->id)
        ->toBe($this->responseDto->id)
        ->and($this->presenter->response->services[0]->name)
        ->toBe($this->responseDto->name)
        ->and($this->presenter->response->services[0]->isActivated)
        ->toBe($this->responseDto->isActivated)
        ->and($this->presenter->response->services[0]->normalCheckInterval)
        ->toBe($this->responseDto->normalCheckInterval)
        ->and($this->presenter->response->services[0]->retryCheckInterval)
        ->toBe($this->responseDto->retryCheckInterval)
        ->and($this->presenter->response->services[0]->serviceTemplate)
        ->toBe($this->responseDto->serviceTemplate)
        ->and($this->presenter->response->services[0]->checkTimePeriod)
        ->toBe($this->responseDto->checkTimePeriod)
        ->and($this->presenter->response->services[0]->notificationTimePeriod)
        ->toBe($this->responseDto->notificationTimePeriod)
        ->and($this->presenter->response->services[0]->severity)
        ->toBe($this->responseDto->severity)
        ->and($this->presenter->response->services[0]->hosts)
        ->toBe($this->responseDto->hosts)
        ->and($this->presenter->response->services[0]->categories)
        ->toBe($this->responseDto->categories)
        ->and($this->presenter->response->services[0]->groups)
        ->toBe($this->responseDto->groups);
});

it('should present a FindServicesResponse with admin user', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->readServiceRepository
        ->expects($this->once())
        ->method('findByRequestParameter')
        ->willReturn([$this->service]);

    $this->readServiceCategoryRepository
        ->expects($this->once())
        ->method('findNames')
        ->willReturn($this->categoryNames);
    $this->readServiceGroupRepository
        ->expects($this->once())
        ->method('findNames')
        ->willReturn($this->groupNames);
    $this->readHostRepository
        ->expects($this->once())
        ->method('findNames')
        ->willReturn($this->hostNames);

    ($this->usecase)($this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(FindServicesResponse::class)
        ->and($this->presenter->response->services[0]->id)
        ->toBe($this->responseDto->id)
        ->and($this->presenter->response->services[0]->name)
        ->toBe($this->responseDto->name)
        ->and($this->presenter->response->services[0]->isActivated)
        ->toBe($this->responseDto->isActivated)
        ->and($this->presenter->response->services[0]->normalCheckInterval)
        ->toBe($this->responseDto->normalCheckInterval)
        ->and($this->presenter->response->services[0]->retryCheckInterval)
        ->toBe($this->responseDto->retryCheckInterval)
        ->and($this->presenter->response->services[0]->serviceTemplate)
        ->toBe($this->responseDto->serviceTemplate)
        ->and($this->presenter->response->services[0]->checkTimePeriod)
        ->toBe($this->responseDto->checkTimePeriod)
        ->and($this->presenter->response->services[0]->notificationTimePeriod)
        ->toBe($this->responseDto->notificationTimePeriod)
        ->and($this->presenter->response->services[0]->severity)
        ->toBe($this->responseDto->severity)
        ->and($this->presenter->response->services[0]->hosts)
        ->toBe($this->responseDto->hosts)
        ->and($this->presenter->response->services[0]->categories)
        ->toBe($this->responseDto->categories)
        ->and($this->presenter->response->services[0]->groups)
        ->toBe($this->responseDto->groups);
});
