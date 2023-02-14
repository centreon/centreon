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

namespace Tests\Core\ServiceSeverity\Application\UseCase\FindServiceSeverities;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\ServiceSeverity\Application\Exception\ServiceSeverityException;
use Core\ServiceSeverity\Application\Repository\ReadServiceSeverityRepositoryInterface;
use Core\ServiceSeverity\Application\UseCase\FindServiceSeverities\FindServiceSeverities;
use Core\ServiceSeverity\Application\UseCase\FindServiceSeverities\FindServiceSeveritiesResponse;
use Core\ServiceSeverity\Domain\Model\ServiceSeverity;
use Core\Infrastructure\Common\Api\DefaultPresenter;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Exception;

beforeEach(function () {
    $this->serviceSeverityRepository = $this->createMock(ReadServiceSeverityRepositoryInterface::class);
    $this->accessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class);
    $this->presenterFormatter = $this->createMock(PresenterFormatterInterface::class);
    $this->requestParameters = $this->createMock(RequestParametersInterface::class);
    $this->user = $this->createMock(ContactInterface::class);
    $this->usecase = new FindServiceSeverities(
        $this->serviceSeverityRepository,
        $this->accessGroupRepository,
        $this->requestParameters,
        $this->user
    );
    $this->presenter = new DefaultPresenter($this->presenterFormatter);
    $this->serviceSeverity = new ServiceSeverity(
        1,
        $this->serviceSeverityName = 'sc-name',
        $this->serviceSeverityAlias = 'sc-alias',
        $this->serviceSeverityLevel = 2,
        $this->serviceSeverityIconId = 1
    );
    $this->responseArray = [
        'id' => 1,
        'name' => $this->serviceSeverityName,
        'alias' => $this->serviceSeverityAlias,
        'level' => $this->serviceSeverityLevel,
        'iconId' => $this->serviceSeverityIconId,
        'isActivated' => true,
    ];
});

it('should present an ErrorResponse when an exception is thrown', function (): void {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);
    $this->serviceSeverityRepository
        ->expects($this->once())
        ->method('findByRequestParameter')
        ->willThrowException(new \Exception());

    ($this->usecase)($this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe(ServiceSeverityException::findServiceSeverities(new Exception())->getMessage());
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
                [Contact::ROLE_CONFIGURATION_SERVICES_CATEGORIES_READ, false],
                [Contact::ROLE_CONFIGURATION_SERVICES_CATEGORIES_READ_WRITE, false],
            ]
        );

    ($this->usecase)($this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->presenter->getResponseStatus()?->getMessage())
        ->toBe(ServiceSeverityException::accessNotAllowed()->getMessage());
});

it('should present a FindServiceGroupsResponse when a non-admin user has read only rights', function (): void {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(false);
    $this->user
        ->expects($this->atMost(2))
        ->method('hasTopologyRole')
        ->willReturnMap(
            [
                [Contact::ROLE_CONFIGURATION_SERVICES_CATEGORIES_READ, true],
                [Contact::ROLE_CONFIGURATION_SERVICES_CATEGORIES_READ_WRITE, false],
            ]
        );
    $this->serviceSeverityRepository
        ->expects($this->once())
        ->method('findByRequestParameterAndAccessGroups')
        ->willReturn([$this->serviceSeverity]);

    ($this->usecase)($this->presenter);

    expect($this->presenter->getPresentedData())
        ->toBeInstanceOf(FindServiceSeveritiesResponse::class)
        ->and($this->presenter->getPresentedData()->serviceSeverities[0])
        ->toBe($this->responseArray);
});

it('should present a FindServiceGroupsResponse when a non-admin user has read/write rights', function (): void {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(false);
    $this->user
        ->expects($this->atMost(2))
        ->method('hasTopologyRole')
        ->willReturnMap(
            [
                [Contact::ROLE_CONFIGURATION_SERVICES_CATEGORIES_READ, false],
                [Contact::ROLE_CONFIGURATION_SERVICES_CATEGORIES_READ_WRITE, true],
            ]
        );
    $this->serviceSeverityRepository
        ->expects($this->once())
        ->method('findByRequestParameterAndAccessGroups')
        ->willReturn([$this->serviceSeverity]);

    ($this->usecase)($this->presenter);

    expect($this->presenter->getPresentedData())
        ->toBeInstanceOf(FindServiceSeveritiesResponse::class)
        ->and($this->presenter->getPresentedData()->serviceSeverities[0])
        ->toBe($this->responseArray);
});

it('should present a FindServiceSeveritiesResponse with admin user', function (): void {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);
    $this->serviceSeverityRepository
        ->expects($this->once())
        ->method('findByRequestParameter')
        ->willReturn([$this->serviceSeverity]);

    ($this->usecase)($this->presenter);

    expect($this->presenter->getPresentedData())
        ->toBeInstanceOf(FindServiceSeveritiesResponse::class)
        ->and($this->presenter->getPresentedData()->serviceSeverities[0])
        ->toBe($this->responseArray);
});
