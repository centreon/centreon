<?php

/*
 * Copyright 2005 - 2022 Centreon (https://www.centreon.com/)
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

namespace Tests\Core\HostSeverity\Application\UseCase\DeleteHostSeverity;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\HostSeverity\Application\Exception\HostSeverityException;
use Core\HostSeverity\Application\Repository\ReadHostSeverityRepositoryInterface;
use Core\HostSeverity\Application\Repository\WriteHostSeverityRepositoryInterface;
use Core\HostSeverity\Application\UseCase\DeleteHostSeverity\DeleteHostSeverity;
use Core\HostSeverity\Domain\Model\HostSeverity;
use Core\Infrastructure\Common\Api\DefaultPresenter;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

beforeEach(function () {
    $this->writeHostSeverityRepository = $this->createMock(WriteHostSeverityRepositoryInterface::class);
    $this->readHostSeverityRepository = $this->createMock(ReadHostSeverityRepositoryInterface::class);
    $this->accessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class);
    $this->presenterFormatter = $this->createMock(PresenterFormatterInterface::class);
    $this->presenter = new DefaultPresenter($this->presenterFormatter);
    $this->user = $this->createMock(ContactInterface::class);
    $this->hostSeverity = $this->createMock(HostSeverity::class);
    $this->hostSeverityId = 1;
});

it('should present an ErrorResponse when an exception is thrown', function () {
    $useCase = new DeleteHostSeverity(
        $this->writeHostSeverityRepository,
        $this->readHostSeverityRepository,
        $this->accessGroupRepository,
        $this->user
    );

    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);
    $this->readHostSeverityRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(true);
    $this->writeHostSeverityRepository
        ->expects($this->once())
        ->method('deleteById')
        ->willThrowException(new \Exception());

    $useCase($this->hostSeverityId, $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe(HostSeverityException::deleteHostSeverity(new \Exception())->getMessage());
});

it('should present a ForbiddenResponse when a non-admin user has insufficient rights', function (): void {
    $useCase = new DeleteHostSeverity(
        $this->writeHostSeverityRepository,
        $this->readHostSeverityRepository,
        $this->accessGroupRepository,
        $this->user
    );

    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(false);
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(false);

    $useCase($this->hostSeverityId, $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe(HostSeverityException::deleteNotAllowed()->getMessage());
});

it('should present a NotFoundResponse when the host severity does not exist (with admin user)', function () {
    $useCase = new DeleteHostSeverity(
        $this->writeHostSeverityRepository,
        $this->readHostSeverityRepository,
        $this->accessGroupRepository,
        $this->user
    );

    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);
    $this->readHostSeverityRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(false);

    $useCase($this->hostSeverityId, $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(NotFoundResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe('Host severity not found');
});

it('should present a NotFoundResponse when the host severity does not exist (with non-admin user)', function () {
    $useCase = new DeleteHostSeverity(
        $this->writeHostSeverityRepository,
        $this->readHostSeverityRepository,
        $this->accessGroupRepository,
        $this->user
    );

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

    $useCase($this->hostSeverityId, $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(NotFoundResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe('Host severity not found');
});

it('should present a NoContentResponse on success (with admin user)', function () {
    $useCase = new DeleteHostSeverity(
        $this->writeHostSeverityRepository,
        $this->readHostSeverityRepository,
        $this->accessGroupRepository,
        $this->user
    );
    $hostSeverityId = 1;

    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);
    $this->readHostSeverityRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(true);
    $this->writeHostSeverityRepository
        ->expects($this->once())
        ->method('deleteById');

    $useCase($hostSeverityId, $this->presenter);

    expect($this->presenter->getResponseStatus())->toBeInstanceOf(NoContentResponse::class);
});

it('should present a NoContentResponse on success (with non-admin user)', function () {
    $useCase = new DeleteHostSeverity(
        $this->writeHostSeverityRepository,
        $this->readHostSeverityRepository,
        $this->accessGroupRepository,
        $this->user
    );

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
        ->willReturn(true);
    $this->writeHostSeverityRepository
        ->expects($this->once())
        ->method('deleteById');

    $useCase($this->hostSeverityId, $this->presenter);

    expect($this->presenter->getResponseStatus())->toBeInstanceOf(NoContentResponse::class);
});
