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

namespace Tests\Core\Dashboard\Application\UseCase\UpdateDashboard;

use Centreon\Domain\Common\Assertion\AssertionException;
use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Dashboard\Application\Exception\DashboardException;
use Core\Dashboard\Application\Repository\ReadDashboardRepositoryInterface;
use Core\Dashboard\Application\Repository\WriteDashboardRepositoryInterface;
use Core\Dashboard\Application\UseCase\UpdateDashboard\UpdateDashboard;
use Core\Dashboard\Application\UseCase\UpdateDashboard\UpdateDashboardRequest;
use Core\Dashboard\Domain\Model\Dashboard;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

beforeEach(function (): void {
    $this->presenter = new UpdateDashboardPresenterStub($this->createMock(PresenterFormatterInterface::class));
    $this->useCase = new UpdateDashboard(
        $this->readDashboardRepository = $this->createMock(ReadDashboardRepositoryInterface::class),
        $this->writeDashboardRepository = $this->createMock(WriteDashboardRepositoryInterface::class),
        $this->readAccessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class),
        $this->contact = $this->createMock(ContactInterface::class)
    );

    $this->testedUpdateDashboardRequest = new UpdateDashboardRequest();
    $this->testedUpdateDashboardRequest->name = 'updated-dashboard';

    $this->testedDashboard = new Dashboard(
        $this->testedDashboardId = 1,
        $this->testedDashboardName = 'dashboard-updated-name',
        $this->testedDashboardDescription = 'dashboard-description',
        $this->testedDashboardCreatedBy = 2,
        $this->testedDashboardUpdatedBy = 3,
        $this->testedDashboardCreatedAt = new \DateTimeImmutable('2023-05-09T12:00:00+00:00'),
        $this->testedDashboardUpdatedAt = new \DateTimeImmutable('2023-05-09T16:00:00+00:00'),
    );
});

it(
    'should present an ErrorResponse when a generic exception is thrown',
    function (): void {
        $this->contact
            ->expects($this->once())
            ->method('isAdmin')
            ->willReturn(true);
        $this->readDashboardRepository
            ->expects($this->once())
            ->method('findOne')
            ->willThrowException(new \Exception());

        ($this->useCase)($this->testedDashboardId, $this->testedUpdateDashboardRequest, $this->presenter);

        expect($this->presenter->data)
            ->toBeInstanceOf(ErrorResponse::class)
            ->and($this->presenter->data->getMessage())
            ->toBe(DashboardException::errorWhileUpdating()->getMessage());
    }
);

it(
    'should present an ErrorResponse with a custom message when a DashboardException is thrown',
    function (): void {
        $this->contact
            ->expects($this->once())
            ->method('isAdmin')
            ->willReturn(true);
        $this->readDashboardRepository
            ->expects($this->once())
            ->method('findOne')
            ->willThrowException(new DashboardException($msg = uniqid('fake message ', true)));

        ($this->useCase)($this->testedDashboardId, $this->testedUpdateDashboardRequest, $this->presenter);

        expect($this->presenter->data)
            ->toBeInstanceOf(ErrorResponse::class)
            ->and($this->presenter->data->getMessage())
            ->toBe($msg);
    }
);

it(
    'should present an InvalidArgumentResponse when a model field value is not valid',
    function (): void {
        $this->contact
            ->expects($this->once())
            ->method('isAdmin')
            ->willReturn(true);
        $this->readDashboardRepository
            ->expects($this->once())
            ->method('findOne')
            ->willReturn($this->testedDashboard);

        $this->testedUpdateDashboardRequest->name = '';
        $expectedException = AssertionException::notEmptyString('Dashboard::name');

        ($this->useCase)($this->testedDashboardId, $this->testedUpdateDashboardRequest, $this->presenter);

        expect($this->presenter->data)
            ->toBeInstanceOf(InvalidArgumentResponse::class)
            ->and($this->presenter->data->getMessage())
            ->toBe($expectedException->getMessage());
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
                    [Contact::ROLE_HOME_DASHBOARD_WRITE, false],
                ]
            );

        ($this->useCase)($this->testedDashboardId, $this->testedUpdateDashboardRequest, $this->presenter);

        expect($this->presenter->data)
            ->toBeInstanceOf(ForbiddenResponse::class)
            ->and($this->presenter->data->getMessage())
            ->toBe(DashboardException::accessNotAllowedForWriting()->getMessage());
    }
);

it(
    'should present a NoContentResponse as admin',
    function (): void {
        $this->contact->expects($this->once())->method('isAdmin')->willReturn(true);
        $this->contact->expects($this->atLeastOnce())->method('getId')->willReturn(1);
        $this->writeDashboardRepository
            ->expects($this->once())
            ->method('update');
        $this->readDashboardRepository
            ->expects($this->once())
            ->method('findOne')
            ->willReturn($this->testedDashboard);

        ($this->useCase)($this->testedDashboardId, $this->testedUpdateDashboardRequest, $this->presenter);

        /** @var NoContentResponse $presentedData */
        $presentedData = $this->presenter->data;

        expect($presentedData)->toBeInstanceOf(NoContentResponse::class);
    }
);

it(
    'should update the updatedAt field',
    function (): void {
        $updatedAt = null;
        $updatedAtBeforeUseCase = $this->testedDashboardUpdatedAt->getTimestamp();

        $this->contact->expects($this->once())->method('isAdmin')->willReturn(true);
        $this->contact->expects($this->atLeastOnce())->method('getId')->willReturn(1);
        $this->writeDashboardRepository
            ->expects($this->once())
            ->method('update')
            ->with(
                $this->callback(function (Dashboard $dashboard) use (&$updatedAt): bool {
                    $updatedAt = $dashboard->getUpdatedAt();

                    return true;
                })
            );
        $this->readDashboardRepository
            ->expects($this->once())
            ->method('findOne')
            ->willReturn($this->testedDashboard);

        $timeBeforeUsecase = time();
        ($this->useCase)($this->testedDashboardId, $this->testedUpdateDashboardRequest, $this->presenter);

        expect($updatedAt)->not()->toBeNull()
            ->and($updatedAt->getTimestamp())->toBeGreaterThanOrEqual($updatedAtBeforeUseCase)
            ->and($updatedAt->getTimestamp())->toBeGreaterThanOrEqual($timeBeforeUsecase);
    }
);

it(
    'should present a ForbiddenResponse as allowed READ user',
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

        ($this->useCase)($this->testedDashboardId, $this->testedUpdateDashboardRequest, $this->presenter);

        expect($this->presenter->data)->toBeInstanceOf(ForbiddenResponse::class);
    }
);

it(
    'should present a NoContentResponse as allowed READ_WRITE user',
    function (): void {
        $this->contact->expects($this->once())->method('isAdmin')->willReturn(false);
        $this->contact->expects($this->atLeastOnce())->method('getId')->willReturn(1);
        $this->contact
            ->expects($this->atMost(2))
            ->method('hasTopologyRole')
            ->willReturnMap(
                [
                    [Contact::ROLE_HOME_DASHBOARD_READ, false],
                    [Contact::ROLE_HOME_DASHBOARD_WRITE, true],
                ]
            );
        $this->readAccessGroupRepository
            ->expects($this->once())
            ->method('findByContact')
            ->willReturn([]);
        $this->writeDashboardRepository
            ->expects($this->once())
            ->method('update');
        $this->readDashboardRepository
            ->expects($this->once())
            ->method('findOneByAccessGroups')
            ->willReturn($this->testedDashboard);

        ($this->useCase)($this->testedDashboardId, $this->testedUpdateDashboardRequest, $this->presenter);

        /** @var NoContentResponse $presentedData */
        $presentedData = $this->presenter->data;

        expect($presentedData)->toBeInstanceOf(NoContentResponse::class);
    }
);

