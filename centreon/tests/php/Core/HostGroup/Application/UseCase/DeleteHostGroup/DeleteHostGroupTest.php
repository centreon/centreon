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

namespace Tests\Core\HostGroup\Application\UseCase\DeleteHostGroup;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Domain\Common\GeoCoords;
use Core\HostGroup\Application\Exceptions\HostGroupException;
use Core\HostGroup\Application\Repository\ReadHostGroupRepositoryInterface;
use Core\HostGroup\Application\Repository\WriteHostGroupRepositoryInterface;
use Core\HostGroup\Application\UseCase\DeleteHostGroup\DeleteHostGroup;
use Core\HostGroup\Domain\Model\HostGroup;
use Core\Infrastructure\Common\Api\DefaultPresenter;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

beforeEach(function (): void {
    $this->readHostGroupRepository = $this->createMock(ReadHostGroupRepositoryInterface::class);
    $this->writeHostGroupRepository = $this->createMock(WriteHostGroupRepositoryInterface::class);
    $this->contact = $this->createMock(ContactInterface::class);

    $this->presenter = new DefaultPresenter($this->createMock(PresenterFormatterInterface::class));
    $this->useCase = new DeleteHostGroup(
        $this->readHostGroupRepository,
        $this->writeHostGroupRepository,
        $this->createMock(ReadAccessGroupRepositoryInterface::class),
        $this->contact
    );

    $this->testedHostGroup = new HostGroup(
        $this->hostgroupId = 1,
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

it('should present an ErrorResponse when an exception is thrown', function (): void {
    $this->contact
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);
    $this->readHostGroupRepository
        ->expects($this->once())
        ->method('existsOne')
        ->willThrowException(new \Exception());

    ($this->useCase)($this->hostgroupId, $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->getResponseStatus()?->getMessage())
        ->toBe(HostGroupException::errorWhileDeleting()->getMessage());
});

it('should present a ForbiddenResponse when the user does not have the correct role', function (): void {
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

    ($this->useCase)($this->hostgroupId, $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->presenter->getResponseStatus()?->getMessage())
        ->toBe(HostGroupException::accessNotAllowedForWriting()->getMessage());
});

it('should present a NoContentResponse as admin', function (): void {
    $this->contact
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);
    $this->readHostGroupRepository
        ->expects($this->once())
        ->method('existsOne')
        ->willReturn(true);

    ($this->useCase)($this->hostgroupId, $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(NoContentResponse::class);
});

it('should present a ForbiddenResponse as allowed READ user', function (): void {
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
        ->expects($this->never())
        ->method('findOneByAccessGroups');

    ($this->useCase)($this->hostgroupId, $this->presenter);

    expect($this->presenter->getResponseStatus())->toBeInstanceOf(ForbiddenResponse::class);
});

it('should present a NoContentResponse as allowed READ_WRITE user', function (): void {
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
        ->method('existsOneByAccessGroups')
        ->willReturn(true);

    ($this->useCase)($this->hostgroupId, $this->presenter);

    expect($this->presenter->getResponseStatus())->toBeInstanceOf(NoContentResponse::class);
});
