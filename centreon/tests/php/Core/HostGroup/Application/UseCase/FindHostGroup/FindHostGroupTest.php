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

namespace Tests\Core\HostGroup\Application\UseCase\FindHostGroup;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Domain\Common\GeoCoords;
use Core\HostGroup\Application\Exceptions\HostGroupException;
use Core\HostGroup\Application\Repository\ReadHostGroupRepositoryInterface;
use Core\HostGroup\Application\UseCase\FindHostGroup\FindHostGroup;
use Core\HostGroup\Application\UseCase\FindHostGroup\FindHostGroupResponse;
use Core\HostGroup\Domain\Model\HostGroup;
use Core\Infrastructure\Common\Api\DefaultPresenter;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

beforeEach(function (): void {
    $this->presenter = new FindHostGroupTestPresenterStub($this->createMock(PresenterFormatterInterface::class));
    $this->useCase = new FindHostGroup(
        $this->readHostGroupRepository = $this->createMock(ReadHostGroupRepositoryInterface::class),
        $this->createMock(ReadAccessGroupRepositoryInterface::class),
        $this->contact = $this->createMock(ContactInterface::class)
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
    $this->testedHostGroupResponse = (function () {
        $response = new FindHostGroupResponse();
        $response->id = 1;
        $response->name = 'hg-name';
        $response->alias = 'hg-alias';
        $response->notes = '';
        $response->notesUrl = '';
        $response->actionUrl = '';
        $response->iconId = null;
        $response->iconMapId = null;
        $response->rrdRetention = null;
        $response->geoCoords = '-2,100';
        $response->comment = '';
        $response->isActivated = true;

        return $response;
    })();
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
            ->method('findOne')
            ->willThrowException(new \Exception());

        ($this->useCase)(1, $this->presenter);

        expect($this->presenter->getResponseStatus())
            ->toBeInstanceOf(ErrorResponse::class)
            ->and($this->presenter->getResponseStatus()?->getMessage())
            ->toBe(HostGroupException::errorWhileRetrieving()->getMessage());
    }
);

it(
    'should present a NotFoundResponse when an exception is thrown',
    function (): void {
        $this->contact
            ->expects($this->once())
            ->method('isAdmin')
            ->willReturn(true);
        $this->readHostGroupRepository
            ->expects($this->once())
            ->method('findOne')
            ->willReturn(null);

        ($this->useCase)(1, $this->presenter);

        expect($this->presenter->getResponseStatus())
            ->toBeInstanceOf(NotFoundResponse::class);
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

        ($this->useCase)(1, $this->presenter);

        expect($this->presenter->getResponseStatus())
            ->toBeInstanceOf(ForbiddenResponse::class)
            ->and($this->presenter->getResponseStatus()?->getMessage())
            ->toBe(HostGroupException::accessNotAllowed()->getMessage());
    }
);

it(
    'should present a FindHostGroupResponse as admin',
    function (): void {
        $this->contact
            ->expects($this->once())
            ->method('isAdmin')
            ->willReturn(true);
        $this->readHostGroupRepository
            ->expects($this->once())
            ->method('findOne')
            ->willReturn($this->testedHostGroup);

        ($this->useCase)(1, $this->presenter);

        expect($this->presenter->getPresentedData())
            ->toBeInstanceOf(FindHostGroupResponse::class)
            ->and((array) $this->presenter->getPresentedData())
            ->toBe((array) $this->testedHostGroupResponse);
    }
);

it(
    'should present a FindHostGroupResponse as allowed READ user',
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
        $this->readHostGroupRepository
            ->expects($this->once())
            ->method('findOneByAccessGroups')
            ->willReturn($this->testedHostGroup);

        ($this->useCase)(1, $this->presenter);

        expect($this->presenter->getPresentedData())
            ->toBeInstanceOf(FindHostGroupResponse::class)
            ->and((array) $this->presenter->getPresentedData())
            ->toBe((array) $this->testedHostGroupResponse);
    }
);

it(
    'should present a FindHostGroupResponse as allowed READ_WRITE user',
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
            ->method('findOneByAccessGroups')
            ->willReturn($this->testedHostGroup);

        ($this->useCase)(1, $this->presenter);

        expect($this->presenter->getPresentedData())
            ->toBeInstanceOf(FindHostGroupResponse::class)
            ->and((array) $this->presenter->getPresentedData())
            ->toBe((array) $this->testedHostGroupResponse);
    }
);
