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

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Domain\Common\GeoCoords;
use Core\HostGroup\Application\Exceptions\HostGroupException;
use Core\HostGroup\Application\Repository\ReadHostGroupRepositoryInterface;
use Core\HostGroup\Application\UseCase\FindHostGroups\FindHostGroups;
use Core\HostGroup\Application\UseCase\FindHostGroups\FindHostGroupsResponse;
use Core\HostGroup\Domain\Model\HostGroup;
use Core\HostGroup\Domain\Model\HostsCountById;
use Core\Infrastructure\Common\Api\DefaultPresenter;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

beforeEach(function (): void {
    $this->presenter = new DefaultPresenter($this->createMock(PresenterFormatterInterface::class));
    $this->useCase = new FindHostGroups(
        $this->readHostGroupRepository = $this->createMock(ReadHostGroupRepositoryInterface::class),
        $this->readAccessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class),
        $this->createMock(RequestParametersInterface::class),
        $this->contact = $this->createMock(ContactInterface::class),
    );

    $this->hostsCountById = new HostsCountById();
    $this->hostsCountById->setEnabledCount(1, 1);
    $this->hostsCountById->setDisabledCount(1, 2);

    $this->hostGroup = new HostGroup(
        1,
        'hg-name',
        'hg-alias',
        '',
        '',
        '',
        12,
        null,
        null,
        $this->geoCoords = GeoCoords::fromString('-2,100'),
        '',
        true
    );
    $this->hostGroupResponse = [
        'id' => 1,
        'name' => 'hg-name',
        'alias' => 'hg-alias',
        'notes' => '',
        'notesUrl' => '',
        'actionUrl' => '',
        'iconId' => 12,
        'iconMapId' => null,
        'rrdRetention' => null,
        'geoCoords' => $this->geoCoords,
        'comment' => '',
        'isActivated' => true,
        'enabledHostsCount' => 1,
        'disabledHostsCount' => 2,
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

        $response = ($this->useCase)();

        expect($response)
            ->toBeInstanceOf(ErrorResponse::class)
            ->and($response->getMessage())
            ->toBe(HostGroupException::errorWhileSearching()->getMessage());
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
            ->willReturn(new \ArrayIterator([$this->hostGroup]));
        $this->readHostGroupRepository
            ->expects($this->once())
            ->method('findHostsCountByIds')
            ->willReturn($this->hostsCountById);

        $response = ($this->useCase)();

        expect($response)
            ->toBeInstanceOf(FindHostGroupsResponse::class)
            ->and($response->hostgroups[0]->id)
            ->toBe($this->hostGroupResponse['id'])
            ->and($response->hostgroups[0]->name)
            ->toBe($this->hostGroupResponse['name'])
            ->and($response->hostgroups[0]->alias)
            ->toBe($this->hostGroupResponse['alias'])
            ->and($response->hostgroups[0]->notes)
            ->toBe($this->hostGroupResponse['notes'])
            ->and($response->hostgroups[0]->notesUrl)
            ->toBe($this->hostGroupResponse['notesUrl'])
            ->and($response->hostgroups[0]->actionUrl)
            ->toBe($this->hostGroupResponse['actionUrl'])
            ->and($response->hostgroups[0]->iconId)
            ->toBe($this->hostGroupResponse['iconId'])
            ->and($response->hostgroups[0]->iconMapId)
            ->toBe($this->hostGroupResponse['iconMapId'])
            ->and($response->hostgroups[0]->rrdRetention)
            ->toBe($this->hostGroupResponse['rrdRetention'])
            ->and($response->hostgroups[0]->geoCoords)
            ->toBe($this->hostGroupResponse['geoCoords'])
            ->and($response->hostgroups[0]->comment)
            ->toBe($this->hostGroupResponse['comment'])
            ->and($response->hostgroups[0]->isActivated)
            ->toBe($this->hostGroupResponse['isActivated'])
            ->and($response->hostgroups[0]->enabledHostsCount)
            ->toBe($this->hostGroupResponse['enabledHostsCount'])
            ->and($response->hostgroups[0]->disabledHostsCount)
            ->toBe($this->hostGroupResponse['disabledHostsCount']);
    }
);

it(
    'should present a FindHostGroupsResponse as non-admin',
    function (): void {
        $this->contact
            ->expects($this->once())
            ->method('isAdmin')
            ->willReturn(false);

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
            ->willReturn(new \ArrayIterator([$this->hostGroup]));
        $this->readHostGroupRepository
            ->expects($this->once())
            ->method('findHostsCountByAccessGroupsIds')
            ->willReturn($this->hostsCountById);

        $response = ($this->useCase)();

        expect($response)
            ->toBeInstanceOf(FindHostGroupsResponse::class)
            ->and($response->hostgroups[0]->id)
            ->toBe($this->hostGroupResponse['id'])
            ->and($response->hostgroups[0]->name)
            ->toBe($this->hostGroupResponse['name'])
            ->and($response->hostgroups[0]->alias)
            ->toBe($this->hostGroupResponse['alias'])
            ->and($response->hostgroups[0]->notes)
            ->toBe($this->hostGroupResponse['notes'])
            ->and($response->hostgroups[0]->notesUrl)
            ->toBe($this->hostGroupResponse['notesUrl'])
            ->and($response->hostgroups[0]->actionUrl)
            ->toBe($this->hostGroupResponse['actionUrl'])
            ->and($response->hostgroups[0]->iconId)
            ->toBe($this->hostGroupResponse['iconId'])
            ->and($response->hostgroups[0]->iconMapId)
            ->toBe($this->hostGroupResponse['iconMapId'])
            ->and($response->hostgroups[0]->rrdRetention)
            ->toBe($this->hostGroupResponse['rrdRetention'])
            ->and($response->hostgroups[0]->geoCoords)
            ->toBe($this->hostGroupResponse['geoCoords'])
            ->and($response->hostgroups[0]->comment)
            ->toBe($this->hostGroupResponse['comment'])
            ->and($response->hostgroups[0]->isActivated)
            ->toBe($this->hostGroupResponse['isActivated'])
            ->and($response->hostgroups[0]->enabledHostsCount)
            ->toBe($this->hostGroupResponse['enabledHostsCount'])
            ->and($response->hostgroups[0]->disabledHostsCount)
            ->toBe($this->hostGroupResponse['disabledHostsCount']);
    }
);
