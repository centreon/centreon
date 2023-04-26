<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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

namespace Tests\Core\HostSeverity\Application\UseCase\FindHostSeverities;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\HostSeverity\Application\Exception\HostSeverityException;
use Core\HostSeverity\Application\Repository\ReadHostSeverityRepositoryInterface;
use Core\HostSeverity\Application\UseCase\FindHostSeverities\FindHostSeverities;
use Core\HostSeverity\Application\UseCase\FindHostSeverities\FindHostSeveritiesResponse;
use Core\HostSeverity\Domain\Model\HostSeverity;
use Core\Infrastructure\Common\Api\DefaultPresenter;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Exception;

beforeEach(function () {
    $this->hostSeverityRepository = $this->createMock(ReadHostSeverityRepositoryInterface::class);
    $this->accessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class);
    $this->presenterFormatter = $this->createMock(PresenterFormatterInterface::class);
    $this->requestParameters = $this->createMock(RequestParametersInterface::class);
    $this->user = $this->createMock(ContactInterface::class);
    $this->usecase = new FindHostSeverities(
        $this->hostSeverityRepository,
        $this->accessGroupRepository,
        $this->requestParameters,
        $this->user
    );
    $this->presenter = new DefaultPresenter($this->presenterFormatter);
    $this->hostSeverity = new HostSeverity(
        1,
        $this->hostSeverityName = 'hc-name',
        $this->hostSeverityAlias = 'hc-alias',
        $this->hostSeverityLevel = 2,
        $this->hostSeverityIconId = 1
    );
    $this->hostSeverity->setComment(
        $this->hostSeverityComment = 'blablabla'
    );
    $this->responseArray = [
        'id' => 1,
        'name' => $this->hostSeverityName,
        'alias' => $this->hostSeverityAlias,
        'level' => $this->hostSeverityLevel,
        'iconId' => $this->hostSeverityIconId,
        'isActivated' => true,
        'comment' => $this->hostSeverityComment,
    ];
});

it('should present an ErrorResponse when an exception is thrown', function () {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);
    $this->hostSeverityRepository
        ->expects($this->once())
        ->method('findAll')
        ->willThrowException(new \Exception());

    ($this->usecase)($this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe(HostSeverityException::findHostSeverities(new Exception())->getMessage());
});

it('should present a ForbiddenResponse when a non-admin user has insufficient rights', function (): void {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(false);
    $this->user
        ->expects($this->atMost(2))
        ->method('hasTopologyRole')
        ->willReturnMap(
            [
                [Contact::ROLE_CONFIGURATION_HOSTS_CATEGORIES_READ, false],
                [Contact::ROLE_CONFIGURATION_HOSTS_CATEGORIES_READ_WRITE, false],
            ]
        );

    ($this->usecase)($this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->presenter->getResponseStatus()?->getMessage())
        ->toBe(HostSeverityException::accessNotAllowed()->getMessage());
});

it('should present a FindHostGroupsResponse when a non-admin user has read only rights', function (): void {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(false);
    $this->user
        ->expects($this->atMost(2))
        ->method('hasTopologyRole')
        ->willReturnMap(
            [
                [Contact::ROLE_CONFIGURATION_HOSTS_CATEGORIES_READ, true],
                [Contact::ROLE_CONFIGURATION_HOSTS_CATEGORIES_READ_WRITE, false],
            ]
        );
    $this->hostSeverityRepository
        ->expects($this->once())
        ->method('findAllByAccessGroups')
        ->willReturn([$this->hostSeverity]);

    ($this->usecase)($this->presenter);

    expect($this->presenter->getPresentedData())
        ->toBeInstanceOf(FindHostSeveritiesResponse::class)
        ->and($this->presenter->getPresentedData()->hostSeverities[0])
        ->toBe($this->responseArray);
});

it('should present a FindHostGroupsResponse when a non-admin user has read/write rights', function (): void {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(false);
    $this->user
        ->expects($this->atMost(2))
        ->method('hasTopologyRole')
        ->willReturnMap(
            [
                [Contact::ROLE_CONFIGURATION_HOSTS_CATEGORIES_READ, false],
                [Contact::ROLE_CONFIGURATION_HOSTS_CATEGORIES_READ_WRITE, true],
            ]
        );
    $this->hostSeverityRepository
        ->expects($this->once())
        ->method('findAllByAccessGroups')
        ->willReturn([$this->hostSeverity]);

    ($this->usecase)($this->presenter);

    expect($this->presenter->getPresentedData())
        ->toBeInstanceOf(FindHostSeveritiesResponse::class)
        ->and($this->presenter->getPresentedData()->hostSeverities[0])
        ->toBe($this->responseArray);
});

it('should present a FindHostSeveritiesResponse with admin user', function () {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);
    $this->hostSeverityRepository
        ->expects($this->once())
        ->method('findAll')
        ->willReturn([$this->hostSeverity]);

    ($this->usecase)($this->presenter);

    expect($this->presenter->getPresentedData())
        ->toBeInstanceOf(FindHostSeveritiesResponse::class)
        ->and($this->presenter->getPresentedData()->hostSeverities[0])
        ->toBe($this->responseArray);
});
