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

namespace Tests\Core\Dashboard\Application\UseCase\FindDashboard;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Dashboard\Application\Exception\DashboardException;
use Core\Dashboard\Application\Repository\ReadDashboardRepositoryInterface;
use Core\Dashboard\Application\UseCase\FindDashboard\FindDashboard;
use Core\Dashboard\Application\UseCase\FindDashboard\FindDashboardResponse;
use Core\Dashboard\Domain\Model\Dashboard;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

beforeEach(function (): void {
    $this->presenter = new FindDashboardPresenterStub($this->createMock(PresenterFormatterInterface::class));
    $this->useCase = new FindDashboard(
        $this->readDashboardRepository = $this->createMock(ReadDashboardRepositoryInterface::class),
        $this->createMock(ReadAccessGroupRepositoryInterface::class),
        $this->contact = $this->createMock(ContactInterface::class)
    );

    $this->testedDashboard = new Dashboard(
        $this->testedDashboardId = 1,
        $this->testedDashboardName = 'dashboard-name',
        $this->testedDashboardDescription = 'dashboard-description',
        $this->testedDashboardCreatedAt = new \DateTimeImmutable('2023-05-09T12:00:00+00:00'),
        $this->testedDashboardUpdatedAt = new \DateTimeImmutable('2023-05-09T16:00:00+00:00'),
    );
});

it(
    'should present an ErrorResponse when an exception is thrown',
    function (): void {
        $this->contact
            ->expects($this->once())
            ->method('isAdmin')
            ->willReturn(true);
        $this->readDashboardRepository
            ->expects($this->once())
            ->method('findOne')
            ->willThrowException(new \Exception());

        ($this->useCase)(1, $this->presenter);

        expect($this->presenter->getResponseStatus())
            ->toBeInstanceOf(ErrorResponse::class)
            ->and($this->presenter->getResponseStatus()?->getMessage())
            ->toBe(DashboardException::errorWhileRetrieving()->getMessage());
    }
);

it(
    'should present a NotFoundResponse when an exception is thrown',
    function (): void {
        $this->contact
            ->expects($this->once())
            ->method('isAdmin')
            ->willReturn(true);
        $this->readDashboardRepository
            ->expects($this->once())
            ->method('findOne')
            ->willReturn(null);

        ($this->useCase)(1, $this->presenter);

        expect($this->presenter->getResponseStatus())
            ->toBeInstanceOf(NotFoundResponse::class);
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
                    [Contact::ROLE_HOME_DASHBOARD_READ, false],
                    [Contact::ROLE_HOME_DASHBOARD_WRITE, false],
                ]
            );

        ($this->useCase)(1, $this->presenter);

        expect($this->presenter->getResponseStatus())
            ->toBeInstanceOf(ForbiddenResponse::class)
            ->and($this->presenter->getResponseStatus()?->getMessage())
            ->toBe(DashboardException::accessNotAllowed()->getMessage());
    }
);

it(
    'should present a FindDashboardResponse as admin',
    function (): void {
        $this->contact
            ->expects($this->once())
            ->method('isAdmin')
            ->willReturn(true);
        $this->readDashboardRepository
            ->expects($this->once())
            ->method('findOne')
            ->willReturn($this->testedDashboard);

        ($this->useCase)(1, $this->presenter);

        /** @var FindDashboardResponse $dashboard */
        $dashboard = $this->presenter->getPresentedData();

        expect($dashboard)->toBeInstanceOf(FindDashboardResponse::class)
            ->and($dashboard->id)->toBe($this->testedDashboardId)
            ->and($dashboard->name)->toBe($this->testedDashboardName)
            ->and($dashboard->description)->toBe($this->testedDashboardDescription)
            ->and($dashboard->createdAt->getTimestamp())->toBe($this->testedDashboardCreatedAt->getTimestamp())
            ->and($dashboard->updatedAt->getTimestamp())->toBeGreaterThanOrEqual($this->testedDashboardUpdatedAt->getTimestamp());
    }
);

it(
    'should present a FindDashboardResponse as allowed READ user',
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
                    [Contact::ROLE_HOME_DASHBOARD_READ, true],
                    [Contact::ROLE_HOME_DASHBOARD_WRITE, false],
                ]
            );
        $this->readDashboardRepository
            ->expects($this->once())
            ->method('findOneByAccessGroups')
            ->willReturn($this->testedDashboard);

        ($this->useCase)(1, $this->presenter);

        /** @var FindDashboardResponse $dashboard */
        $dashboard = $this->presenter->getPresentedData();

        expect($dashboard)->toBeInstanceOf(FindDashboardResponse::class)
            ->and($dashboard->id)->toBe($this->testedDashboardId)
            ->and($dashboard->name)->toBe($this->testedDashboardName)
            ->and($dashboard->description)->toBe($this->testedDashboardDescription)
            ->and($dashboard->createdAt->getTimestamp())->toBe($this->testedDashboardCreatedAt->getTimestamp())
            ->and($dashboard->updatedAt->getTimestamp())->toBeGreaterThanOrEqual($this->testedDashboardUpdatedAt->getTimestamp());
    }
);

it(
    'should present a FindDashboardResponse as allowed READ_WRITE user',
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
                    [Contact::ROLE_HOME_DASHBOARD_READ, false],
                    [Contact::ROLE_HOME_DASHBOARD_WRITE, true],
                ]
            );
        $this->readDashboardRepository
            ->expects($this->once())
            ->method('findOneByAccessGroups')
            ->willReturn($this->testedDashboard);

        ($this->useCase)(1, $this->presenter);

        /** @var FindDashboardResponse $dashboard */
        $dashboard = $this->presenter->getPresentedData();

        expect($dashboard)->toBeInstanceOf(FindDashboardResponse::class)
            ->and($dashboard->id)->toBe($this->testedDashboardId)
            ->and($dashboard->name)->toBe($this->testedDashboardName)
            ->and($dashboard->description)->toBe($this->testedDashboardDescription)
            ->and($dashboard->createdAt->getTimestamp())->toBe($this->testedDashboardCreatedAt->getTimestamp())
            ->and($dashboard->updatedAt->getTimestamp())->toBeGreaterThanOrEqual($this->testedDashboardUpdatedAt->getTimestamp());
    }
);
