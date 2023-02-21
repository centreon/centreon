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

namespace Tests\Core\ServiceSeverity\Application\UseCase\AddServiceSeverity;

use Centreon\Domain\Common\Assertion\AssertionException;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Application\Common\UseCase\ConflictResponse;
use Core\Application\Common\UseCase\CreatedResponse;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\ServiceSeverity\Application\Exception\ServiceSeverityException;
use Core\ServiceSeverity\Application\Repository\ReadServiceSeverityRepositoryInterface;
use Core\ServiceSeverity\Application\Repository\WriteServiceSeverityRepositoryInterface;
use Core\ServiceSeverity\Application\UseCase\AddServiceSeverity\AddServiceSeverity;
use Core\ServiceSeverity\Application\UseCase\AddServiceSeverity\AddServiceSeverityRequest;
use Core\ServiceSeverity\Domain\Model\ServiceSeverity;
use Core\ServiceSeverity\Domain\Model\NewServiceSeverity;
use Core\Infrastructure\Common\Api\DefaultPresenter;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\ViewImg\Application\Repository\ReadViewImgRepositoryInterface;

beforeEach(function (): void {
    $this->writeServiceSeverityRepository = $this->createMock(WriteServiceSeverityRepositoryInterface::class);
    $this->readServiceSeverityRepository = $this->createMock(ReadServiceSeverityRepositoryInterface::class);
    $this->presenterFormatter = $this->createMock(PresenterFormatterInterface::class);
    $this->user = $this->createMock(ContactInterface::class);
    $this->request = new AddServiceSeverityRequest();
    $this->request->name = 'sc-name';
    $this->request->alias = 'sc-alias';
    $this->request->level = 2;
    $this->request->iconId = 1;
    $this->presenter = new DefaultPresenter($this->presenterFormatter);
    $this->useCase = new AddServiceSeverity(
        $this->writeServiceSeverityRepository,
        $this->readServiceSeverityRepository,
        $this->readViewImgRepository = $this->createMock(ReadViewImgRepositoryInterface::class),
        $this->user
    );
    $this->serviceSeverity = new ServiceSeverity(
        1,
        $this->request->name,
        $this->request->alias,
        $this->request->level,
        $this->request->iconId,
    );
});

it('should present an ErrorResponse when a generic exception is thrown', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->readServiceSeverityRepository
        ->expects($this->once())
        ->method('existsByName')
        ->willThrowException(new \Exception());

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe(ServiceSeverityException::addServiceSeverity(new \Exception())->getMessage());
});

it('should present a ForbiddenResponse when a user has insufficient rights', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(false);

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe(ServiceSeverityException::addNotAllowed()->getMessage());
});

it('should present a ConflictResponse when name is already used', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->readServiceSeverityRepository
        ->expects($this->once())
        ->method('existsByName')
        ->willReturn(true);

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->presenter->getResponseStatus()?->getMessage())
        ->toBe(ServiceSeverityException::serviceNameAlreadyExists()->getMessage());
});

it('should present an InvalidArgumentResponse when a field assert failed', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->readServiceSeverityRepository
        ->expects($this->once())
        ->method('existsByName')
        ->willReturn(false);
    $this->readViewImgRepository
        ->expects($this->once())
        ->method('existsOne')
        ->willReturn(true);

    $this->request->level = NewServiceSeverity::MIN_LEVEL_VALUE - 1;
    $expectedException = AssertionException::min(
        $this->request->level,
        NewServiceSeverity::MIN_LEVEL_VALUE,
        'NewServiceSeverity::level'
    );
    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(InvalidArgumentResponse::class)
        ->and($this->presenter->getResponseStatus()?->getMessage())
        ->toBe($expectedException->getMessage());
});

it('should throw a ConflictResponse if the service severity icon does not exist', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->readServiceSeverityRepository
        ->expects($this->once())
        ->method('existsByName')
        ->willReturn(false);
    $this->readViewImgRepository
        ->expects($this->once())
        ->method('existsOne')
        ->willReturn(false);

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ConflictResponse::class)
        ->and($this->presenter->getResponseStatus()?->getMessage())
        ->toBe(ServiceSeverityException::iconDoesNotExist($this->request->iconId)->getMessage());
});

it('should present an ErrorResponse if the newly created service severity cannot be retrieved', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
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
        ->method('add')
        ->willReturn(1);
    $this->readServiceSeverityRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn(null);

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->getResponseStatus()?->getMessage())
        ->toBe(ServiceSeverityException::errorWhileRetrievingJustCreated(new \Exception())->getMessage());
});

it('should return created object on success', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
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
        ->method('add')
        ->willReturn(1);
    $this->readServiceSeverityRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($this->serviceSeverity);

    ($this->useCase)($this->request, $this->presenter);

    expect($this->presenter->getPresentedData())->toBeInstanceOf(CreatedResponse::class);
    expect($this->presenter->getPresentedData()->getResourceId())->toBe($this->serviceSeverity->getId());

    $payload = $this->presenter->getPresentedData()->getPayload();
    expect($payload->name)
        ->toBe($this->serviceSeverity->getName())
        ->and($payload->alias)
        ->toBe($this->serviceSeverity->getAlias())
        ->and($payload->isActivated)
        ->toBe($this->serviceSeverity->isActivated());
});

