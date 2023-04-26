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

namespace Tests\Core\ServiceGroup\Application\UseCase\FindServiceGroups;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Domain\Common\GeoCoords;
use Core\ServiceGroup\Application\Exception\ServiceGroupException;
use Core\ServiceGroup\Application\Repository\ReadServiceGroupRepositoryInterface;
use Core\ServiceGroup\Application\UseCase\FindServiceGroups\FindServiceGroups;
use Core\ServiceGroup\Application\UseCase\FindServiceGroups\FindServiceGroupsResponse;
use Core\ServiceGroup\Domain\Model\ServiceGroup;
use Core\Infrastructure\Common\Api\DefaultPresenter;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

beforeEach(function (): void {
    $this->readServiceGroupRepository = $this->createMock(ReadServiceGroupRepositoryInterface::class);
    $this->contact = $this->createMock(ContactInterface::class);

    $this->presenter = new DefaultPresenter($this->createMock(PresenterFormatterInterface::class));
    $this->useCase = new FindServiceGroups(
        $this->readServiceGroupRepository,
        $this->createMock(ReadAccessGroupRepositoryInterface::class),
        $this->createMock(RequestParametersInterface::class),
        $this->contact
    );

    $this->testedServiceGroup = new ServiceGroup(
        1,
        'sg-name',
        'sg-alias',
        GeoCoords::fromString('-2,100'),
        '',
        true
    );
    $this->testedServiceGroupArray = [
        'id' => 1,
        'name' => 'sg-name',
        'alias' => 'sg-alias',
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
        $this->readServiceGroupRepository
            ->expects($this->once())
            ->method('findAll')
            ->willThrowException(new \Exception());

        ($this->useCase)($this->presenter);

        expect($this->presenter->getResponseStatus())
            ->toBeInstanceOf(ErrorResponse::class)
            ->and($this->presenter->getResponseStatus()?->getMessage())
            ->toBe(ServiceGroupException::errorWhileSearching()->getMessage());
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
                    [Contact::ROLE_CONFIGURATION_SERVICES_SERVICE_GROUPS_READ, false],
                    [Contact::ROLE_CONFIGURATION_SERVICES_SERVICE_GROUPS_READ_WRITE, false],
                ]
            );

        ($this->useCase)($this->presenter);

        expect($this->presenter->getResponseStatus())
            ->toBeInstanceOf(ForbiddenResponse::class)
            ->and($this->presenter->getResponseStatus()?->getMessage())
            ->toBe(ServiceGroupException::accessNotAllowed()->getMessage());
    }
);

it(
    'should present a FindServiceGroupsResponse as admin',
    function (): void {
        $this->contact
            ->expects($this->once())
            ->method('isAdmin')
            ->willReturn(true);
        $this->readServiceGroupRepository
            ->expects($this->once())
            ->method('findAll')
            ->willReturn([$this->testedServiceGroup]);

        ($this->useCase)($this->presenter);

        expect($this->presenter->getPresentedData())
            ->toBeInstanceOf(FindServiceGroupsResponse::class)
            ->and($this->presenter->getPresentedData()->servicegroups[0])
            ->toBe($this->testedServiceGroupArray);
    }
);

it(
    'should present a FindServiceGroupsResponse as allowed READ user',
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
        $this->readServiceGroupRepository
            ->expects($this->once())
            ->method('findAllByAccessGroups')
            ->willReturn([$this->testedServiceGroup]);

        ($this->useCase)($this->presenter);

        expect($this->presenter->getPresentedData())
            ->toBeInstanceOf(FindServiceGroupsResponse::class)
            ->and($this->presenter->getPresentedData()->servicegroups[0])
            ->toBe($this->testedServiceGroupArray);
    }
);

it(
    'should present a FindServiceGroupsResponse as allowed READ_WRITE user',
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
            ->method('findAllByAccessGroups')
            ->willReturn([$this->testedServiceGroup]);

        ($this->useCase)($this->presenter);

        expect($this->presenter->getPresentedData())
            ->toBeInstanceOf(FindServiceGroupsResponse::class)
            ->and($this->presenter->getPresentedData()->servicegroups[0])
            ->toBe($this->testedServiceGroupArray);
    }
);
