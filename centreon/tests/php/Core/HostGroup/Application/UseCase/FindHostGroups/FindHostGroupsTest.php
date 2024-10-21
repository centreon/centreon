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

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Domain\Common\GeoCoords;
use Core\HostGroup\Application\Exceptions\HostGroupException;
use Core\HostGroup\Application\Repository\ReadHostGroupRepositoryInterface;
use Core\HostGroup\Application\UseCase\FindHostGroups\FindHostGroups;
use Core\HostGroup\Application\UseCase\FindHostGroups\FindHostGroupsResponse;
use Core\HostGroup\Domain\Model\HostGroup;
use Core\Infrastructure\Common\Api\DefaultPresenter;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

beforeEach(function (): void {
    $this->readHostGroupRepository = $this->createMock(ReadHostGroupRepositoryInterface::class);
    $this->contact = $this->createMock(ContactInterface::class);

    $this->presenter = new DefaultPresenter($this->createMock(PresenterFormatterInterface::class));
    $this->useCase = new FindHostGroups(
        $this->readHostGroupRepository,
        $this->readAccessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class),
        $this->createMock(RequestParametersInterface::class),
        $this->contact,
    );

    $this->testedHostGroup = new HostGroup(
        1,
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
    $this->testedHostGroupArray = [
        'id' => 1,
        'name' => 'hg-name',
        'alias' => 'hg-alias',
        'notes' => '',
        'notesUrl' => '',
        'actionUrl' => '',
        'iconId' => null,
        'iconMapId' => null,
        'rrdRetention' => null,
        'geoCoords' => '-2,100',
        'comment' => '',
        'isActivated' => true,
    ];
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

        ($this->useCase)($this->presenter);

        expect($this->presenter->getResponseStatus())
            ->toBeInstanceOf(ErrorResponse::class)
            ->and($this->presenter->getResponseStatus()?->getMessage())
            ->toBe(HostGroupException::errorWhileSearching()->getMessage());
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
                    [Contact::ROLE_CONFIGURATION_HOSTS_HOST_GROUPS_READ, false],
                    [Contact::ROLE_CONFIGURATION_HOSTS_HOST_GROUPS_READ_WRITE, false],
                ]
            );

        ($this->useCase)($this->presenter);

        expect($this->presenter->getResponseStatus())
            ->toBeInstanceOf(ForbiddenResponse::class)
            ->and($this->presenter->getResponseStatus()?->getMessage())
            ->toBe(HostGroupException::accessNotAllowed()->getMessage());
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
            ->willReturn(new \ArrayIterator([$this->testedHostGroup]));

        ($this->useCase)($this->presenter);

        expect($this->presenter->getPresentedData())
            ->toBeInstanceOf(FindHostGroupsResponse::class)
            ->and($this->presenter->getPresentedData()->hostgroups[0])
            ->toBe($this->testedHostGroupArray);
    }
);

it(
    'should present a FindHostGroupsResponse as allowed READ user',
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
            ->willReturn(new \ArrayIterator([$this->testedHostGroup]));

        ($this->useCase)($this->presenter);

        expect($this->presenter->getPresentedData())
            ->toBeInstanceOf(FindHostGroupsResponse::class)
            ->and($this->presenter->getPresentedData()->hostgroups[0])
            ->toBe($this->testedHostGroupArray);
    }
);

it(
    'should present a FindHostGroupsResponse as allowed READ_WRITE user',
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
            ->willReturn(new \ArrayIterator([$this->testedHostGroup]));

        ($this->useCase)($this->presenter);

        expect($this->presenter->getPresentedData())
            ->toBeInstanceOf(FindHostGroupsResponse::class)
            ->and($this->presenter->getPresentedData()->hostgroups[0])
            ->toBe($this->testedHostGroupArray);
    }
);
