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

namespace Tests\Core\HostSeverity\Application\UseCase\FindHostSeverity;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\HostSeverity\Application\Exception\HostSeverityException;
use Core\HostSeverity\Application\Repository\ReadHostSeverityRepositoryInterface;
use Core\HostSeverity\Application\UseCase\FindHostSeverity\FindHostSeverity;
use Core\HostSeverity\Application\UseCase\FindHostSeverity\FindHostSeverityResponse;
use Core\HostSeverity\Domain\Model\HostSeverity;
use Core\Infrastructure\Common\Api\DefaultPresenter;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Exception;

beforeEach(function (): void {
    $this->readHostSeverityRepository = $this->createMock(ReadHostSeverityRepositoryInterface::class);
    $this->accessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class);
    $this->presenterFormatter = $this->createMock(PresenterFormatterInterface::class);
    $this->user = $this->createMock(ContactInterface::class);
    $this->usecase = new FindHostSeverity(
        $this->readHostSeverityRepository,
        $this->accessGroupRepository,
        $this->user
    );
    $this->presenter = new DefaultPresenter($this->presenterFormatter);
    $this->hostSeverityName = 'hs-name';
    $this->hostSeverityAlias = 'hs-alias';
    $this->hostSeverityComment = 'blablabla';
    $this->hostSeverity = new HostSeverity(
        1,
        $this->hostSeverityName,
        $this->hostSeverityAlias,
        1,
        1
    );
    $this->hostSeverity->setComment($this->hostSeverityComment);
    $this->responseArray = [
        'id' => 1,
        'name' => $this->hostSeverityName,
        'alias' => $this->hostSeverityAlias,
        'level' => 1,
        'icon_id' => 1,
        'is_activated' => true,
        'comment' => $this->hostSeverityComment,
    ];
});

it('should present an ErrorResponse when an exception is thrown', function (): void {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);
    $this->readHostSeverityRepository
        ->expects($this->once())
        ->method('exists')
        ->willThrowException(new \Exception());

    ($this->usecase)($this->hostSeverity->getId(), $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe(HostSeverityException::findHostSeverity(new Exception(), $this->hostSeverity->getId())->getMessage());
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

    ($this->usecase)($this->hostSeverity->getId(), $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->presenter->getResponseStatus()?->getMessage())
        ->toBe(HostSeverityException::accessNotAllowed()->getMessage());
});

it('should present a NotFoundResponse when the host severity does not exist (with admin user)', function (): void {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);
    $this->readHostSeverityRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(false);

    ($this->usecase)($this->hostSeverity->getId(), $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(NotFoundResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe('Host severity not found');
});

it('should present a NotFoundResponse when the host severity does not exist (with non-admin user)', function (): void {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(false);
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->readHostSeverityRepository
        ->expects($this->once())
        ->method('existsByAccessGroups')
        ->willReturn(false);

    ($this->usecase)($this->hostSeverity->getId(), $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(NotFoundResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe('Host severity not found');
});

it('should present a FindHostSeverityResponse when a non-admin user has read-only rights', function (): void {
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
    $this->readHostSeverityRepository
        ->expects($this->once())
        ->method('existsByAccessGroups')
        ->willReturn(true);
    $this->readHostSeverityRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($this->hostSeverity);

    ($this->usecase)($this->hostSeverity->getId(), $this->presenter);

    $response = $this->presenter->getPresentedData();
    expect($response)
        ->toBeInstanceOf(FindHostSeverityResponse::class)
        ->and($response->id)
        ->toBe($this->responseArray['id'])
        ->and($response->name)
        ->toBe($this->responseArray['name'])
        ->and($response->alias)
        ->toBe($this->responseArray['alias'])
        ->and($response->isActivated)
        ->toBe($this->responseArray['is_activated'])
        ->and($response->level)
        ->toBe($this->responseArray['level'])
        ->and($response->iconId)
        ->toBe($this->responseArray['icon_id'])
        ->and($response->comment)
        ->toBe($this->responseArray['comment']);
    });

it('should present a FindHostSeverityResponse when a non-admin user has read/write rights', function (): void {
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
    $this->readHostSeverityRepository
        ->expects($this->once())
        ->method('existsByAccessGroups')
        ->willReturn(true);
    $this->readHostSeverityRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($this->hostSeverity);

    ($this->usecase)($this->hostSeverity->getId(), $this->presenter);

    $response = $this->presenter->getPresentedData();
    expect($response)
        ->toBeInstanceOf(FindHostSeverityResponse::class)
        ->and($response->id)
        ->toBe($this->responseArray['id'])
        ->and($response->name)
        ->toBe($this->responseArray['name'])
        ->and($response->alias)
        ->toBe($this->responseArray['alias'])
        ->and($response->isActivated)
        ->toBe($this->responseArray['is_activated'])
        ->and($response->level)
        ->toBe($this->responseArray['level'])
        ->and($response->iconId)
        ->toBe($this->responseArray['icon_id'])
        ->and($response->comment)
        ->toBe($this->responseArray['comment']);
});


it('should present a FindHostSeverityResponse with admin user', function (): void {
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);
    $this->readHostSeverityRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(true);
    $this->readHostSeverityRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($this->hostSeverity);

    ($this->usecase)($this->hostSeverity->getId(), $this->presenter);

    $response = $this->presenter->getPresentedData();
    expect($response)
        ->toBeInstanceOf(FindHostSeverityResponse::class)
        ->and($response->id)
        ->toBe($this->responseArray['id'])
        ->and($response->name)
        ->toBe($this->responseArray['name'])
        ->and($response->alias)
        ->toBe($this->responseArray['alias'])
        ->and($response->isActivated)
        ->toBe($this->responseArray['is_activated'])
        ->and($response->level)
        ->toBe($this->responseArray['level'])
        ->and($response->iconId)
        ->toBe($this->responseArray['icon_id'])
        ->and($response->comment)
        ->toBe($this->responseArray['comment']);
});