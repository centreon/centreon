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
use Core\Application\Common\UseCase\ConflictResponse;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Domain\Common\GeoCoords;
use Core\Domain\Exception\InvalidGeoCoordException;
use Core\HostGroup\Application\Exceptions\HostGroupException;
use Core\HostGroup\Application\Repository\ReadHostGroupRepositoryInterface;
use Core\HostGroup\Application\Repository\WriteHostGroupRepositoryInterface;
use Core\HostGroup\Application\UseCase\UpdateHostGroup\UpdateHostGroup;
use Core\HostGroup\Application\UseCase\UpdateHostGroup\UpdateHostGroupRequest;
use Core\HostGroup\Domain\Model\HostGroup;
use Core\Infrastructure\Common\Api\DefaultPresenter;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\ViewImg\Application\Repository\ReadViewImgRepositoryInterface;

beforeEach(function (): void {
    $this->presenter = new UpdateHostGroupTestPresenterStub($this->createMock(PresenterFormatterInterface::class));
    $this->useCase = new UpdateHostGroup(
        $this->readHostGroupRepository = $this->createMock(ReadHostGroupRepositoryInterface::class),
        $this->writeHostGroupRepository = $this->createMock(WriteHostGroupRepositoryInterface::class),
        $this->readAccessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class),
        $this->readViewImgRepository = $this->createMock(ReadViewImgRepositoryInterface::class),
        $this->contact = $this->createMock(ContactInterface::class)
    );

    $this->testedUpdateHostGroupRequest = new UpdateHostGroupRequest();
    $this->testedUpdateHostGroupRequest->name = 'updated-hostgroup';

    $this->testedHostGroup = new HostGroup(
        $this->hostGroupId = 66,
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
            ->method('findOne')
            ->willReturn($this->testedHostGroup);
        $this->readHostGroupRepository
            ->expects($this->once())
            ->method('nameAlreadyExists')
            ->willThrowException(new \Exception());

        ($this->useCase)($this->hostGroupId, $this->testedUpdateHostGroupRequest, $this->presenter);

        expect($this->presenter->getResponseStatus())
            ->toBeInstanceOf(ErrorResponse::class)
            ->and($this->presenter->getResponseStatus()?->getMessage())
            ->toBe(HostGroupException::errorWhileUpdating()->getMessage());
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
            ->method('findOne')
            ->willReturn($this->testedHostGroup);
        $this->readHostGroupRepository
            ->expects($this->once())
            ->method('nameAlreadyExists')
            ->willThrowException(new HostGroupException($msg = uniqid('fake message ', true)));

        ($this->useCase)($this->hostGroupId, $this->testedUpdateHostGroupRequest, $this->presenter);

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
        $this->readHostGroupRepository
            ->expects($this->once())
            ->method('findOne')
            ->willReturn($this->testedHostGroup);
        $this->readHostGroupRepository
            ->expects($this->once())
            ->method('nameAlreadyExists')
            ->willReturn(true);

        ($this->useCase)($this->hostGroupId, $this->testedUpdateHostGroupRequest, $this->presenter);

        expect($this->presenter->getResponseStatus())
            ->toBeInstanceOf(ConflictResponse::class)
            ->and($this->presenter->getResponseStatus()?->getMessage())
            ->toBe(HostGroupException::nameAlreadyExists($this->testedUpdateHostGroupRequest->name)->getMessage());
    }
);

it(
    'should present an InvalidArgumentResponse when a model field value is not valid',
    function (): void {
        $this->contact
            ->expects($this->once())
            ->method('isAdmin')
            ->willReturn(true);
        $this->readHostGroupRepository
            ->expects($this->once())
            ->method('findOne')
            ->willReturn($this->testedHostGroup);
        $this->readHostGroupRepository
            ->expects($this->once())
            ->method('nameAlreadyExists')
            ->willReturn(false);

        $this->testedUpdateHostGroupRequest->name = '';
        $expectedException = AssertionException::minLength(
            $this->testedUpdateHostGroupRequest->name,
            mb_strlen($this->testedUpdateHostGroupRequest->name),
            HostGroup::MIN_NAME_LENGTH,
            'HostGroup::name'
        );

        ($this->useCase)($this->hostGroupId, $this->testedUpdateHostGroupRequest, $this->presenter);

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
        $this->readHostGroupRepository
            ->expects($this->once())
            ->method('findOne')
            ->willReturn($this->testedHostGroup);
        $this->readHostGroupRepository
            ->expects($this->once())
            ->method('nameAlreadyExists')
            ->willReturn(false);

        $this->testedUpdateHostGroupRequest->geoCoords = 'this,is,wrong';

        ($this->useCase)($this->hostGroupId, $this->testedUpdateHostGroupRequest, $this->presenter);

        expect($this->presenter->getResponseStatus())
            ->toBeInstanceOf(InvalidArgumentResponse::class)
            ->and($this->presenter->getResponseStatus()?->getMessage())
            ->toBe(InvalidGeoCoordException::invalidFormat()->getMessage());
    }
);

foreach (['iconId', 'iconMapId'] as $iconField) {
    it(
        "should present a ConflictResponse if the {$iconField} does not exist",
        function () use ($iconField): void {
            $this->contact
                ->expects($this->once())
                ->method('isAdmin')
                ->willReturn(true);
            $this->readHostGroupRepository
                ->expects($this->once())
                ->method('findOne')
                ->willReturn($this->testedHostGroup);
            $this->readViewImgRepository
                ->expects($this->once())
                ->method('existsOne')
                ->willReturn(false);

            $this->testedUpdateHostGroupRequest->{$iconField} = 666;
            ($this->useCase)($this->hostGroupId, $this->testedUpdateHostGroupRequest, $this->presenter);

            expect($this->presenter->getResponseStatus())
                ->toBeInstanceOf(ConflictResponse::class)
                ->and($this->presenter->getResponseStatus()?->getMessage())
                ->toBe(HostGroupException::iconDoesNotExist($iconField, 666)->getMessage());
        }
    );
}

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

        ($this->useCase)($this->hostGroupId, $this->testedUpdateHostGroupRequest, $this->presenter);

        expect($this->presenter->getResponseStatus())
            ->toBeInstanceOf(ForbiddenResponse::class)
            ->and($this->presenter->getResponseStatus()?->getMessage())
            ->toBe(HostGroupException::accessNotAllowedForWriting()->getMessage());
    }
);

it(
    'should present a NoContentResponse as admin',
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
            ->method('update');
        $this->readHostGroupRepository
            ->expects($this->once())
            ->method('findOne')
            ->willReturn($this->testedHostGroup);

        ($this->useCase)($this->hostGroupId, $this->testedUpdateHostGroupRequest, $this->presenter);

        /** @var NoContentResponse $presentedData */
        $presentedData = $this->presenter->getPresentedData();

        expect($presentedData)->toBeInstanceOf(NoContentResponse::class);
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

        ($this->useCase)($this->hostGroupId, $this->testedUpdateHostGroupRequest, $this->presenter);

        expect($this->presenter->getResponseStatus())->toBeInstanceOf(ForbiddenResponse::class);
    }
);

it(
    'should present a NoContentResponse as allowed READ_WRITE user',
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
            ->method('update');
        $this->readHostGroupRepository
            ->expects($this->once())
            ->method('findOneByAccessGroups')
            ->willReturn($this->testedHostGroup);

        ($this->useCase)($this->hostGroupId, $this->testedUpdateHostGroupRequest, $this->presenter);

        /** @var NoContentResponse $presentedData */
        $presentedData = $this->presenter->getPresentedData();

        expect($presentedData)->toBeInstanceOf(NoContentResponse::class);
    }
);
