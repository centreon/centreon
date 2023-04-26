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

namespace Tests\Core\ServiceGroup\Application\UseCase\AddServiceGroup;

use Centreon\Domain\Common\Assertion\AssertionException;
use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\Application\Common\UseCase\ConflictResponse;
use Core\Application\Common\UseCase\CreatedResponse;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Domain\Common\GeoCoords;
use Core\Domain\Exception\InvalidGeoCoordException;
use Core\ServiceGroup\Application\Exception\ServiceGroupException;
use Core\ServiceGroup\Application\Repository\ReadServiceGroupRepositoryInterface;
use Core\ServiceGroup\Application\Repository\WriteServiceGroupRepositoryInterface;
use Core\ServiceGroup\Application\UseCase\AddServiceGroup\AddServiceGroup;
use Core\ServiceGroup\Application\UseCase\AddServiceGroup\AddServiceGroupRequest;
use Core\ServiceGroup\Application\UseCase\AddServiceGroup\AddServiceGroupResponse;
use Core\ServiceGroup\Domain\Model\ServiceGroup;
use Core\ServiceGroup\Domain\Model\NewServiceGroup;
use Core\Infrastructure\Common\Api\DefaultPresenter;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\WriteAccessGroupRepositoryInterface;
use Core\ViewImg\Application\Repository\ReadViewImgRepositoryInterface;

beforeEach(function (): void {
    $this->presenter = new DefaultPresenter($this->createMock(PresenterFormatterInterface::class));
    $this->useCase = new AddServiceGroup(
        $this->readServiceGroupRepository = $this->createMock(ReadServiceGroupRepositoryInterface::class),
        $this->writeServiceGroupRepository = $this->createMock(WriteServiceGroupRepositoryInterface::class),
        $this->readAccessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class),
        $this->writeAccessGroupRepository = $this->createMock(WriteAccessGroupRepositoryInterface::class),
        $this->dataStorageEngine = $this->createMock(DataStorageEngineInterface::class),
        $this->contact = $this->createMock(ContactInterface::class)
    );

    $this->testedAddServiceGroupRequest = new AddServiceGroupRequest();
    $this->testedAddServiceGroupRequest->name = 'added-servicegroup';

    $this->testedServiceGroup = new ServiceGroup(
        66,
        'sg-name',
        'sg-alias',
        GeoCoords::fromString('-2,100'),
        '',
        true
    );
});

it(
    'should present an ErrorResponse when a generic exception is thrown',
    function (): void {
        $this->contact
            ->expects($this->once())
            ->method('isAdmin')
            ->willReturn(true);
        $this->readServiceGroupRepository
            ->expects($this->once())
            ->method('nameAlreadyExists')
            ->willThrowException(new \Exception());

        ($this->useCase)($this->testedAddServiceGroupRequest, $this->presenter);

        expect($this->presenter->getResponseStatus())
            ->toBeInstanceOf(ErrorResponse::class)
            ->and($this->presenter->getResponseStatus()?->getMessage())
            ->toBe(ServiceGroupException::errorWhileAdding()->getMessage());
    }
);

it(
    'should present an ErrorResponse with a custom message when a ServiceGroupException is thrown',
    function (): void {
        $this->contact
            ->expects($this->once())
            ->method('isAdmin')
            ->willReturn(true);
        $this->readServiceGroupRepository
            ->expects($this->once())
            ->method('nameAlreadyExists')
            ->willThrowException(new ServiceGroupException($msg = uniqid('fake message ', true)));

        ($this->useCase)($this->testedAddServiceGroupRequest, $this->presenter);

        expect($this->presenter->getResponseStatus())
            ->toBeInstanceOf(ErrorResponse::class)
            ->and($this->presenter->getResponseStatus()?->getMessage())
            ->toBe($msg);
    }
);

it(
    'should present a ConflictResponse if the name already exists',
    function (): void {
        $this->contact
            ->expects($this->once())
            ->method('isAdmin')
            ->willReturn(true);
        $this->readServiceGroupRepository
            ->expects($this->once())
            ->method('nameAlreadyExists')
            ->willReturn(true);

        ($this->useCase)($this->testedAddServiceGroupRequest, $this->presenter);

        expect($this->presenter->getResponseStatus())
            ->toBeInstanceOf(ConflictResponse::class)
            ->and($this->presenter->getResponseStatus()?->getMessage())
            ->toBe(ServiceGroupException::nameAlreadyExists($this->testedAddServiceGroupRequest->name)->getMessage());
    }
);

it(
    'should present an InvalidArgumentResponse when a model field value is not valid',
    function (): void {
        $this->contact
            ->expects($this->once())
            ->method('isAdmin')
            ->willReturn(true);
        $this->readServiceGroupRepository
            ->expects($this->once())
            ->method('nameAlreadyExists')
            ->willReturn(false);

        $this->testedAddServiceGroupRequest->name = '';
        $expectedException = AssertionException::minLength(
            $this->testedAddServiceGroupRequest->name,
            strlen($this->testedAddServiceGroupRequest->name),
            NewServiceGroup::MIN_NAME_LENGTH,
            'NewServiceGroup::name'
        );

        ($this->useCase)($this->testedAddServiceGroupRequest, $this->presenter);

        expect($this->presenter->getResponseStatus())
            ->toBeInstanceOf(InvalidArgumentResponse::class)
            ->and($this->presenter->getResponseStatus()?->getMessage())
            ->toBe($expectedException->getMessage());
    }
);

it(
    'should present an InvalidArgumentResponse when the "geoCoords" field value is not valid',
    function (): void {
        $this->contact
            ->expects($this->once())
            ->method('isAdmin')
            ->willReturn(true);
        $this->readServiceGroupRepository
            ->expects($this->once())
            ->method('nameAlreadyExists')
            ->willReturn(false);

        $this->testedAddServiceGroupRequest->geoCoords = 'this,is,wrong';

        ($this->useCase)($this->testedAddServiceGroupRequest, $this->presenter);

        expect($this->presenter->getResponseStatus())
            ->toBeInstanceOf(InvalidArgumentResponse::class)
            ->and($this->presenter->getResponseStatus()?->getMessage())
            ->toBe(InvalidGeoCoordException::invalidFormat()->getMessage());
    }
);

it(
    'should present an ErrorResponse if the newly created service group cannot be retrieved',
    function (): void {
        $this->contact
            ->expects($this->once())
            ->method('isAdmin')
            ->willReturn(true);
        $this->readServiceGroupRepository
            ->expects($this->once())
            ->method('nameAlreadyExists')
            ->willReturn(false);
        $this->writeServiceGroupRepository
            ->expects($this->once())
            ->method('add')
            ->willReturn($this->testedServiceGroup->getId());
        $this->readServiceGroupRepository
            ->expects($this->once())
            ->method('findOne')
            ->willReturn(null); // the failure

        ($this->useCase)($this->testedAddServiceGroupRequest, $this->presenter);

        expect($this->presenter->getResponseStatus())
            ->toBeInstanceOf(ErrorResponse::class)
            ->and($this->presenter->getResponseStatus()?->getMessage())
            ->toBe(ServiceGroupException::errorWhileRetrievingJustCreated()->getMessage());
    }
);

it(
    'should present a ForbiddenResponse when the user does not have the correct role',
    function (): void {
        $this->contact
            ->expects($this->once())
            ->method('isAdmin')
            ->willReturn(false);
        $this->contact
            ->expects($this->atMost(2))
            ->method('hasTopologyRole')
            ->willReturnMap(
                [
                    [Contact::ROLE_CONFIGURATION_SERVICES_SERVICE_GROUPS_READ_WRITE, false],
                ]
            );

        ($this->useCase)($this->testedAddServiceGroupRequest, $this->presenter);

        expect($this->presenter->getResponseStatus())
            ->toBeInstanceOf(ForbiddenResponse::class)
            ->and($this->presenter->getResponseStatus()?->getMessage())
            ->toBe(ServiceGroupException::accessNotAllowedForWriting()->getMessage());
    }
);

it(
    'should present a CreatedResponse<AddServiceGroupResponse> as admin',
    function (): void {
        $this->contact
            ->expects($this->once())
            ->method('isAdmin')
            ->willReturn(true);
        $this->readServiceGroupRepository
            ->expects($this->once())
            ->method('nameAlreadyExists')
            ->willReturn(false);
        $this->writeServiceGroupRepository
            ->expects($this->once())
            ->method('add')
            ->willReturn($this->testedServiceGroup->getId());
        $this->readServiceGroupRepository
            ->expects($this->once())
            ->method('findOne')
            ->willReturn($this->testedServiceGroup);

        ($this->useCase)($this->testedAddServiceGroupRequest, $this->presenter);

        /** @var CreatedResponse<AddServiceGroupResponse> $presentedData */
        $presentedData = $this->presenter->getPresentedData();

        expect($presentedData)->toBeInstanceOf(CreatedResponse::class)
            ->and($presentedData->getPayload())->toBeInstanceOf(AddServiceGroupResponse::class);
    }
);

it(
    'should present a ForbiddenResponse as allowed READ user',
    function (): void {
        $this->contact
            ->expects($this->once())
            ->method('isAdmin')
            ->willReturn(false);
        $this->contact
            ->expects($this->atMost(2))
            ->method('hasTopologyRole')
            ->willReturnMap(
                [
                    [Contact::ROLE_CONFIGURATION_SERVICES_SERVICE_GROUPS_READ, true],
                    [Contact::ROLE_CONFIGURATION_SERVICES_SERVICE_GROUPS_READ_WRITE, false],
                ]
            );

        ($this->useCase)($this->testedAddServiceGroupRequest, $this->presenter);

        expect($this->presenter->getResponseStatus())->toBeInstanceOf(ForbiddenResponse::class);
    }
);

it(
    'should present a CreatedResponse<AddServiceGroupResponse> as allowed READ_WRITE user',
    function (): void {
        $this->contact
            ->expects($this->once())
            ->method('isAdmin')
            ->willReturn(false);
        $this->contact
            ->expects($this->atMost(2))
            ->method('hasTopologyRole')
            ->willReturnMap(
                [
                    [Contact::ROLE_CONFIGURATION_SERVICES_SERVICE_GROUPS_READ, false],
                    [Contact::ROLE_CONFIGURATION_SERVICES_SERVICE_GROUPS_READ_WRITE, true],
                ]
            );
        $this->readServiceGroupRepository
            ->expects($this->once())
            ->method('nameAlreadyExists')
            ->willReturn(false);
        $this->readAccessGroupRepository
            ->expects($this->once())
            ->method('findByContact')
            ->willReturn([]);
        $this->writeServiceGroupRepository
            ->expects($this->once())
            ->method('add')
            ->willReturn($this->testedServiceGroup->getId());
        $this->writeAccessGroupRepository
            ->expects($this->once())
            ->method('addLinksBetweenServiceGroupAndAccessGroups');
        $this->readServiceGroupRepository
            ->expects($this->once())
            ->method('findOneByAccessGroups')
            ->willReturn($this->testedServiceGroup);

        ($this->useCase)($this->testedAddServiceGroupRequest, $this->presenter);

        /** @var CreatedResponse<AddServiceGroupResponse> $presentedData */
        $presentedData = $this->presenter->getPresentedData();

        expect($presentedData)->toBeInstanceOf(CreatedResponse::class)
            ->and($presentedData->getPayload())->toBeInstanceOf(AddServiceGroupResponse::class);
    }
);
