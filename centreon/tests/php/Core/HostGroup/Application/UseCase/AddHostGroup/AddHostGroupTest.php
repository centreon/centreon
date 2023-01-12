<?php

/*
 * Copyright 2005 - 2022 Centreon (https://www.centreon.com/)
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

namespace Tests\Core\HostGroup\Application\UseCase\AddHostGroup;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\Application\Common\UseCase\ConflictResponse;
use Core\Application\Common\UseCase\CreatedResponse;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Domain\Common\GeoCoords;
use Core\HostGroup\Application\Exceptions\HostGroupException;
use Core\HostGroup\Application\Repository\ReadHostGroupRepositoryInterface;
use Core\HostGroup\Application\Repository\WriteHostGroupRepositoryInterface;
use Core\HostGroup\Application\UseCase\AddHostGroup\AddHostGroup;
use Core\HostGroup\Application\UseCase\AddHostGroup\AddHostGroupRequest;
use Core\HostGroup\Application\UseCase\AddHostGroup\AddHostGroupResponse;
use Core\HostGroup\Domain\Model\HostGroup;
use Core\Infrastructure\Common\Api\DefaultPresenter;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\WriteAccessGroupRepositoryInterface;
use Core\ViewImg\Application\Repository\ReadViewImgRepositoryInterface;

beforeEach(function (): void {
    $this->presenter = new DefaultPresenter($this->createMock(PresenterFormatterInterface::class));
    $this->useCase = new AddHostGroup(
        $this->readHostGroupRepository = $this->createMock(ReadHostGroupRepositoryInterface::class),
        $this->writeHostGroupRepository = $this->createMock(WriteHostGroupRepositoryInterface::class),
        $this->readAccessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class),
        $this->writeAccessGroupRepository = $this->createMock(WriteAccessGroupRepositoryInterface::class),
        $this->readViewImgRepository = $this->createMock(ReadViewImgRepositoryInterface::class),
        $this->dataStorageEngine = $this->createMock(DataStorageEngineInterface::class),
        $this->contact = $this->createMock(ContactInterface::class)
    );

    $this->testedAddHostGroupRequest = new AddHostGroupRequest();
    $this->testedAddHostGroupRequest->name = 'added-hostgroup';

    $this->testedHostGroup = new HostGroup(
        66,
        'hg-name',
        'hg-alias',
        '',
        '',
        '',
        null,
        null,
        null,
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
        $this->readHostGroupRepository
            ->expects($this->once())
            ->method('nameAlreadyExists')
            ->willThrowException(new \Exception());

        ($this->useCase)($this->testedAddHostGroupRequest, $this->presenter);

        expect($this->presenter->getResponseStatus())
            ->toBeInstanceOf(ErrorResponse::class)
            ->and($this->presenter->getResponseStatus()?->getMessage())
            ->toBe(HostGroupException::errorWhileDeleting()->getMessage());
    }
);

it(
    'should present an ErrorResponse with a custom message when a HostGroupException is thrown',
    function (): void {
        $this->contact
            ->expects($this->once())
            ->method('isAdmin')
            ->willReturn(true);
        $this->readHostGroupRepository
            ->expects($this->once())
            ->method('nameAlreadyExists')
            ->willThrowException(new HostGroupException($msg = uniqid('fake message ', true)));

        ($this->useCase)($this->testedAddHostGroupRequest, $this->presenter);

        expect($this->presenter->getResponseStatus())
            ->toBeInstanceOf(ErrorResponse::class)
            ->and($this->presenter->getResponseStatus()?->getMessage())
            ->toBe($msg);
    }
);

it(
    'should present an ErrorResponse if the name already exists',
    function (): void {
        $this->contact
            ->expects($this->once())
            ->method('isAdmin')
            ->willReturn(true);
        $this->readHostGroupRepository
            ->expects($this->once())
            ->method('nameAlreadyExists')
            ->willReturn(true);

        ($this->useCase)($this->testedAddHostGroupRequest, $this->presenter);

        expect($this->presenter->getResponseStatus())
            ->toBeInstanceOf(ConflictResponse::class)
            ->and($this->presenter->getResponseStatus()?->getMessage())
            ->toBe(HostGroupException::nameAlreadyExists($this->testedAddHostGroupRequest->name)->getMessage());
    }
);

foreach (['iconId', 'iconMapId'] as $iconField) {
    it(
        "should present an ErrorResponse if the {$iconField} does not exist",
        function () use ($iconField): void {
            $this->contact
                ->expects($this->once())
                ->method('isAdmin')
                ->willReturn(true);
            $this->readViewImgRepository
                ->expects($this->once())
                ->method('existsOne')
                ->willReturn(false);

            $this->testedAddHostGroupRequest->{$iconField} = 666;
            ($this->useCase)($this->testedAddHostGroupRequest, $this->presenter);

            expect($this->presenter->getResponseStatus())
                ->toBeInstanceOf(ErrorResponse::class)
                ->and($this->presenter->getResponseStatus()?->getMessage())
                ->toBe(HostGroupException::iconDoesNotExist($iconField, 666)->getMessage());
        }
    );
}

it(
    'should present an ErrorResponse if the newly created host group cannot be retrieved',
    function (): void {
        $this->contact
            ->expects($this->once())
            ->method('isAdmin')
            ->willReturn(true);
        $this->readHostGroupRepository
            ->expects($this->once())
            ->method('nameAlreadyExists')
            ->willReturn(false);
        $this->writeHostGroupRepository
            ->expects($this->once())
            ->method('add')
            ->willReturn($this->testedHostGroup->getId());
        $this->readHostGroupRepository
            ->expects($this->once())
            ->method('findOne')
            ->willReturn(null); // the failure

        ($this->useCase)($this->testedAddHostGroupRequest, $this->presenter);

        expect($this->presenter->getResponseStatus())
            ->toBeInstanceOf(ErrorResponse::class)
            ->and($this->presenter->getResponseStatus()?->getMessage())
            ->toBe(HostGroupException::errorWhileRetrievingJustCreated()->getMessage());
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
                    [Contact::ROLE_CONFIGURATION_HOSTS_HOST_GROUPS_READ_WRITE, false],
                ]
            );

        ($this->useCase)($this->testedAddHostGroupRequest, $this->presenter);

        expect($this->presenter->getResponseStatus())
            ->toBeInstanceOf(ForbiddenResponse::class)
            ->and($this->presenter->getResponseStatus()?->getMessage())
            ->toBe(HostGroupException::accessNotAllowed()->getMessage());
    }
);

it(
    'should present a CreatedResponse<AddHostGroupResponse> as admin',
    function (): void {
        $this->contact
            ->expects($this->once())
            ->method('isAdmin')
            ->willReturn(true);
        $this->readHostGroupRepository
            ->expects($this->once())
            ->method('nameAlreadyExists')
            ->willReturn(false);
        $this->writeHostGroupRepository
            ->expects($this->once())
            ->method('add')
            ->willReturn($this->testedHostGroup->getId());
        $this->readHostGroupRepository
            ->expects($this->once())
            ->method('findOne')
            ->willReturn($this->testedHostGroup);

        ($this->useCase)($this->testedAddHostGroupRequest, $this->presenter);

        /** @var CreatedResponse<AddHostGroupResponse> $presentedData */
        $presentedData = $this->presenter->getPresentedData();

        expect($presentedData)->toBeInstanceOf(CreatedResponse::class)
            ->and($presentedData->getPayload())->toBeInstanceOf(AddHostGroupResponse::class);
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
                    [Contact::ROLE_CONFIGURATION_HOSTS_HOST_GROUPS_READ, true],
                    [Contact::ROLE_CONFIGURATION_HOSTS_HOST_GROUPS_READ_WRITE, false],
                ]
            );

        ($this->useCase)($this->testedAddHostGroupRequest, $this->presenter);

        expect($this->presenter->getResponseStatus())->toBeInstanceOf(ForbiddenResponse::class);
    }
);

it(
    'should present a CreatedResponse<AddHostGroupResponse> as allowed READ_WRITE user',
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
                    [Contact::ROLE_CONFIGURATION_HOSTS_HOST_GROUPS_READ, false],
                    [Contact::ROLE_CONFIGURATION_HOSTS_HOST_GROUPS_READ_WRITE, true],
                ]
            );
        $this->readHostGroupRepository
            ->expects($this->once())
            ->method('nameAlreadyExists')
            ->willReturn(false);
        $this->readAccessGroupRepository
            ->expects($this->once())
            ->method('findByContact')
            ->willReturn([]);
        $this->writeHostGroupRepository
            ->expects($this->once())
            ->method('add')
            ->willReturn($this->testedHostGroup->getId());
        $this->writeAccessGroupRepository
            ->expects($this->once())
            ->method('addLinksBetweenHostGroupAndAccessGroups');
        $this->readHostGroupRepository
            ->expects($this->once())
            ->method('findOneByAccessGroups')
            ->willReturn($this->testedHostGroup);

        ($this->useCase)($this->testedAddHostGroupRequest, $this->presenter);

        /** @var CreatedResponse<AddHostGroupResponse> $presentedData */
        $presentedData = $this->presenter->getPresentedData();

        expect($presentedData)->toBeInstanceOf(CreatedResponse::class)
            ->and($presentedData->getPayload())->toBeInstanceOf(AddHostGroupResponse::class);
    }
);
