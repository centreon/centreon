<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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

namespace Tests\Core\ServiceSeverity\Application\UseCase\GetServiceSeverity;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Infrastructure\Common\Api\DefaultPresenter;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\ServiceSeverity\Application\Exception\ServiceSeverityException;
use Core\ServiceSeverity\Application\Repository\ReadServiceSeverityRepositoryInterface;
use Core\ServiceSeverity\Application\UseCase\GetServiceSeverity\GetServiceSeverity;
use Core\ServiceSeverity\Application\UseCase\GetServiceSeverity\GetServiceSeverityResponse;
use Core\ServiceSeverity\Domain\Model\ServiceSeverity;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

beforeEach(function (): void {
    $this->readServiceSeverityRepository = $this->createMock(ReadServiceSeverityRepositoryInterface::class);
    $this->readAccessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class);
    $this->presenterFormatter = $this->createMock(PresenterFormatterInterface::class);
    $this->user = $this->createMock(ContactInterface::class);
    $this->presenter = new DefaultPresenter($this->presenterFormatter);
    $this->useCase = new GetServiceSeverity(
        $this->readServiceSeverityRepository,
        $this->readAccessGroupRepository,
        $this->user
    );
    $this->serviceSeverity = new ServiceSeverity(
        1,
        'sc-name',
        'sc-alias',
        2,
        1,
    );
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

    ($this->useCase)($this->presenter, $this->serviceSeverity->getId());

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ErrorResponse::class)
        ->and(value: $this->presenter->getResponseStatus()->getMessage())
        ->toBe(ServiceSeverityException::errorWhileRetrieving()->getMessage());
});

it('should present a ForbiddenResponse when a user has insufficient rights', function (): void {
    $this->user
        ->expects($this->exactly(2))
        ->method('hasTopologyRole')
        ->willReturn(false);

    ($this->useCase)($this->presenter, $this->serviceSeverity->getId());

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->presenter->getResponseStatus()->getMessage())
        ->toBe(ServiceSeverityException::accessNotAllowed()->getMessage());
});


it('should present a GetServiceResponse with non-admin user', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);
    $this->user
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(false);
    $this->readAccessGroupRepository
        ->expects($this->once())
        ->method('findByContact')
        ->willReturn([]);
    $this->readServiceSeverityRepository
        ->expects($this->once())
        ->method('existsByAccessGroups')
        ->willReturn(true);
    $this->readServiceSeverityRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($this->serviceSeverity);

    ($this->useCase)($this->presenter, $this->serviceSeverity->getId());

    $dto = $this->presenter->getPresentedData();
    expect($dto)
        ->toBeInstanceOf(GetServiceSeverityResponse::class)
        ->and($dto->name)
        ->toBe($this->serviceSeverity->getName())
        ->and($dto->alias)
        ->toBe($this->serviceSeverity->getAlias())
        ->and($dto->isActivated)
        ->toBe($this->serviceSeverity->isActivated())
        ->and($dto->level)
        ->toBe($this->serviceSeverity->getLevel())
        ->and($dto->iconId)
        ->toBe($this->serviceSeverity->getIconId());
});


it('should present a GetServiceResponse with admin user', function (): void {
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
        ->willReturn($this->serviceSeverity);

    ($this->useCase)($this->presenter, $this->serviceSeverity->getId());

    $dto = $this->presenter->getPresentedData();
    expect($dto)
        ->toBeInstanceOf(GetServiceSeverityResponse::class)
        ->and($dto->name)
        ->toBe($this->serviceSeverity->getName())
        ->and($dto->alias)
        ->toBe($this->serviceSeverity->getAlias())
        ->and($dto->isActivated)
        ->toBe($this->serviceSeverity->isActivated())
        ->and($dto->level)
        ->toBe($this->serviceSeverity->getLevel())
        ->and($dto->iconId)
        ->toBe($this->serviceSeverity->getIconId());
});