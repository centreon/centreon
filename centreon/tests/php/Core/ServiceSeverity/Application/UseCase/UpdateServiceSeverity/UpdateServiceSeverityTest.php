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

namespace Tests\Core\ServiceSeverity\Application\UseCase\UpdateServiceSeverity;

use Centreon\Domain\Common\Assertion\AssertionException;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Application\Common\UseCase\ConflictResponse;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Infrastructure\Common\Api\DefaultPresenter;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\ServiceSeverity\Application\Exception\ServiceSeverityException;
use Core\ServiceSeverity\Application\Repository\ReadServiceSeverityRepositoryInterface;
use Core\ServiceSeverity\Application\Repository\WriteServiceSeverityRepositoryInterface;
use Core\ServiceSeverity\Application\UseCase\UpdateServiceSeverity\UpdateServiceSeverity;
use Core\ServiceSeverity\Application\UseCase\UpdateServiceSeverity\UpdateServiceSeverityRequest;
use Core\ServiceSeverity\Domain\Model\ServiceSeverity;
use Core\ViewImg\Application\Repository\ReadViewImgRepositoryInterface;

beforeEach(function (): void {
    $this->presenter = new DefaultPresenter(
        $this->presenterFormatter = $this->createMock(PresenterFormatterInterface::class)
    );
    $this->useCase = new UpdateServiceSeverity(
        $this->writeServiceSeverityRepository = $this->createMock(WriteServiceSeverityRepositoryInterface::class),
        $this->readServiceSeverityRepository = $this->createMock(ReadServiceSeverityRepositoryInterface::class),
        $this->readViewImgRepository = $this->createMock(ReadViewImgRepositoryInterface::class),
        $this->readAccessGroupRepositoryInterface = $this->createMock(ReadAccessGroupRepositoryInterface::class),
        $this->user = $this->createMock(ContactInterface::class)
    );

    $this->severity = new ServiceSeverity(
        1,
        'sev-name',
        'sev-alias',
        2,
        1,
    );

    $this->request = new UpdateServiceSeverityRequest();
    $this->request->name = $this->severity->getName() . '-edited';
    $this->request->alias = $this->severity->getAlias() . '-edited';
    $this->request->level = $this->severity->getLevel();
    $this->request->iconId = $this->severity->getIconId();
});

it('should present an ErrorResponse when a generic exception is thrown', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->readServiceSeverityRepository
        ->expects($this->once())
        ->method('findById')
        ->willThrowException(new \Exception());

    ($this->useCase)($this->request, $this->presenter, $this->severity->getId());

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe(ServiceSeverityException::editServiceSeverity(new \Exception())->getMessage());
});

it('should present a ForbiddenResponse when a user has insufficient rights', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(false);

    ($this->useCase)($this->request, $this->presenter, $this->severity->getId());

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe(ServiceSeverityException::editNotAllowed()->getMessage());
});

it('should present a ConflictResponse when name is already used', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->readServiceSeverityRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($this->severity);

    $this->readServiceSeverityRepository
        ->expects($this->once())
        ->method('existsByName')
        ->willReturn(true);

    ($this->useCase)($this->request, $this->presenter, $this->severity->getId());

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->presenter->getResponseStatus()?->getMessage())
        ->toBe(ServiceSeverityException::serviceNameAlreadyExists()->getMessage());
});

it('should throw a ConflictResponse if the service severity icon does not exist', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->readServiceSeverityRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($this->severity);

    $this->readServiceSeverityRepository
        ->expects($this->once())
        ->method('existsByName')
        ->willReturn(false);

    ($this->useCase)($this->request, $this->presenter, $this->severity->getId());

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->presenter->getResponseStatus()?->getMessage())
        ->toBe(ServiceSeverityException::iconDoesNotExist($this->request->iconId)->getMessage());
});

it('should present an InvalidArgumentResponse when a field assert failed', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->readServiceSeverityRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($this->severity);

    $this->readServiceSeverityRepository
        ->expects($this->once())
        ->method('existsByName')
        ->willReturn(false);
    $this->readViewImgRepository
        ->expects($this->once())
        ->method('existsOne')
        ->willReturn(true);

    $this->request->level = ServiceSeverity::MIN_LEVEL_VALUE - 1;
    $expectedException = AssertionException::min(
        $this->request->level,
        ServiceSeverity::MIN_LEVEL_VALUE,
        'ServiceSeverity::level'
    );

    ($this->useCase)($this->request, $this->presenter, $this->severity->getId());

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(InvalidArgumentResponse::class)
        ->and($this->presenter->getResponseStatus()?->getMessage())
        ->toBe($expectedException->getMessage());
});

it('should return created object on success', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->readServiceSeverityRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($this->severity);

    $this->readServiceSeverityRepository
        ->expects($this->once())
        ->method('existsByName')
        ->willReturn(false);
    $this->readViewImgRepository
        ->expects($this->once())
        ->method('existsOne')
        ->willReturn(true);

    $this->writeServiceSeverityRepository
        ->expects($this->once())
        ->method('update');

    ($this->useCase)($this->request, $this->presenter, $this->severity->getId());

    expect($this->presenter->getResponseStatus())->toBeInstanceOf(NoContentResponse::class);
});
