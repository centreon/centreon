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

namespace Tests\Core\Dashboard\Application\UseCase\DeleteDashboard;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Dashboard\Application\Exception\DashboardException;
use Core\Dashboard\Application\Repository\ReadDashboardRepositoryInterface;
use Core\Dashboard\Application\Repository\WriteDashboardRepositoryInterface;
use Core\Dashboard\Application\UseCase\DeleteDashboard\DeleteDashboard;
use Core\Dashboard\Domain\Model\Dashboard;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

beforeEach(function (): void {
    $this->readDashboardRepository = $this->createMock(ReadDashboardRepositoryInterface::class);
    $this->writeDashboardRepository = $this->createMock(WriteDashboardRepositoryInterface::class);
    $this->contact = $this->createMock(ContactInterface::class);

    $this->presenter = new DeleteDashboardPresenterStub($this->createMock(PresenterFormatterInterface::class));
    $this->useCase = new DeleteDashboard(
        $this->readDashboardRepository,
        $this->writeDashboardRepository,
        $this->createMock(ReadAccessGroupRepositoryInterface::class),
        $this->contact
    );

    $this->testedDashboard = new Dashboard(
        $this->testedDashboardId = 1,
        $this->testedDashboardName = 'dashboard-name',
        $this->testedDashboardDescription = 'dashboard-description',
        $this->testedDashboardCreatedBy = 2,
        $this->testedDashboardUpdatedBy = 3,
        $this->testedDashboardCreatedAt = new \DateTimeImmutable('2023-05-09T12:00:00+00:00'),
        $this->testedDashboardUpdatedAt = new \DateTimeImmutable('2023-05-09T16:00:00+00:00'),
    );
});

it('should present an ErrorResponse when an exception is thrown', function (): void {
    $this->contact
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);
    $this->readDashboardRepository
        ->expects($this->once())
        ->method('existsOne')
        ->willThrowException(new \Exception());

    ($this->useCase)($this->testedDashboardId, $this->presenter);

    expect($this->presenter->data)
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->data->getMessage())
        ->toBe(DashboardException::errorWhileDeleting()->getMessage());
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
                [Contact::ROLE_HOME_DASHBOARD_WRITE, false],
            ]
        );

    ($this->useCase)($this->testedDashboardId, $this->presenter);

    expect($this->presenter->data)
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->presenter->data->getMessage())
        ->toBe(DashboardException::accessNotAllowedForWriting()->getMessage());
});

it('should present a NoContentResponse as admin', function (): void {
    $this->contact
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);
    $this->readDashboardRepository
        ->expects($this->once())
        ->method('existsOne')
        ->willReturn(true);

    ($this->useCase)($this->testedDashboardId, $this->presenter);

    expect($this->presenter->data)
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
                [Contact::ROLE_HOME_DASHBOARD_READ, true],
                [Contact::ROLE_HOME_DASHBOARD_WRITE, false],
            ]
        );
    $this->readDashboardRepository
        ->expects($this->never())
        ->method('findOneByAccessGroups');

    ($this->useCase)($this->testedDashboardId, $this->presenter);

    expect($this->presenter->data)->toBeInstanceOf(ForbiddenResponse::class);
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
                [Contact::ROLE_HOME_DASHBOARD_READ, false],
                [Contact::ROLE_HOME_DASHBOARD_WRITE, true],
            ]
        );
    $this->readDashboardRepository
        ->expects($this->once())
        ->method('existsOneByAccessGroups')
        ->willReturn(true);

    ($this->useCase)($this->testedDashboardId, $this->presenter);

    expect($this->presenter->data)->toBeInstanceOf(NoContentResponse::class);
});
