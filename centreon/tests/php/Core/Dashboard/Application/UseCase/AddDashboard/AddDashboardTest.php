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

namespace Tests\Core\Dashboard\Application\UseCase\AddDashboard;

use Centreon\Domain\Common\Assertion\AssertionException;
use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Dashboard\Application\Exception\DashboardException;
use Core\Dashboard\Application\Repository\ReadDashboardRepositoryInterface;
use Core\Dashboard\Application\Repository\WriteDashboardRepositoryInterface;
use Core\Dashboard\Application\UseCase\AddDashboard\AddDashboard;
use Core\Dashboard\Application\UseCase\AddDashboard\AddDashboardRequest;
use Core\Dashboard\Application\UseCase\AddDashboard\AddDashboardResponse;
use Core\Dashboard\Domain\Model\Dashboard;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

beforeEach(function (): void {
    $this->presenter = new AddDashboardPresenterStub($this->createMock(PresenterFormatterInterface::class));
    $this->useCase = new AddDashboard(
        $this->readDashboardRepository = $this->createMock(ReadDashboardRepositoryInterface::class),
        $this->writeDashboardRepository = $this->createMock(WriteDashboardRepositoryInterface::class),
        $this->readAccessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class),
        $this->contact = $this->createMock(ContactInterface::class)
    );

    $this->testedAddDashboardRequest = new AddDashboardRequest();
    $this->testedAddDashboardRequest->name = 'added-dashboard';

    $this->testedDashboard = new Dashboard(
        $this->testedDashboardId = 1,
        $this->testedDashboardName = 'dashboard-name',
        $this->testedDashboardDescription = 'dashboard-description',
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
            ->willThrowException(new \Exception());

        ($this->useCase)($this->testedAddDashboardRequest, $this->presenter);

        expect($this->presenter->getResponseStatus())
            ->toBeInstanceOf(ErrorResponse::class)
            ->and($this->presenter->getResponseStatus()?->getMessage())
            ->toBe(DashboardException::errorWhileAdding()->getMessage());
    }
);

it(
    'should present an ErrorResponse with a custom message when a DashboardException is thrown',
    function (): void {
        $this->contact
            ->expects($this->once())
            ->method('isAdmin')
            ->willThrowException(new DashboardException($msg = uniqid('fake message ', true)));

        ($this->useCase)($this->testedAddDashboardRequest, $this->presenter);

        expect($this->presenter->getResponseStatus())
            ->toBeInstanceOf(ErrorResponse::class)
            ->and($this->presenter->getResponseStatus()?->getMessage())
            ->toBe($msg);
    }
);

it(
    'should present a InvalidArgumentResponse when a model field value is not valid',
    function (): void {
        $this->contact
            ->expects($this->once())
            ->method('isAdmin')
            ->willReturn(true);

        $this->testedAddDashboardRequest->name = '';
        $expectedException = AssertionException::notEmptyString('NewDashboard::name');

        ($this->useCase)($this->testedAddDashboardRequest, $this->presenter);

        expect($this->presenter->getResponseStatus())
            ->toBeInstanceOf(InvalidArgumentResponse::class)
            ->and($this->presenter->getResponseStatus()?->getMessage())
            ->toBe($expectedException->getMessage());
    }
);

it(
    'should present an ErrorResponse if the newly created dashboard cannot be retrieved',
    function (): void {
        $this->contact
            ->expects($this->once())
            ->method('isAdmin')
            ->willReturn(true);
        $this->writeDashboardRepository
            ->expects($this->once())
            ->method('add')
            ->willReturn($this->testedDashboard->getId());
        $this->readDashboardRepository
            ->expects($this->once())
            ->method('findOne')
            ->willReturn(null); // the failure

        ($this->useCase)($this->testedAddDashboardRequest, $this->presenter);

        expect($this->presenter->getResponseStatus())
            ->toBeInstanceOf(ErrorResponse::class)
            ->and($this->presenter->getResponseStatus()?->getMessage())
            ->toBe(DashboardException::errorWhileRetrievingJustCreated()->getMessage());
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

        ($this->useCase)($this->testedAddDashboardRequest, $this->presenter);

        expect($this->presenter->getResponseStatus())
            ->toBeInstanceOf(ForbiddenResponse::class)
            ->and($this->presenter->getResponseStatus()?->getMessage())
            ->toBe(DashboardException::accessNotAllowedForWriting()->getMessage());
    }
);

it(
    'should present a AddDashboardResponse as admin',
    function (): void {
        $this->contact
            ->expects($this->once())
            ->method('isAdmin')
            ->willReturn(true);
        $this->writeDashboardRepository
            ->expects($this->once())
            ->method('add')
            ->willReturn($this->testedDashboard->getId());
        $this->readDashboardRepository
            ->expects($this->once())
            ->method('findOne')
            ->willReturn($this->testedDashboard);

        ($this->useCase)($this->testedAddDashboardRequest, $this->presenter);

        /** @var AddDashboardResponse $presentedData */
        $dashboard = $this->presenter->getPresentedData();

        expect($dashboard)->toBeInstanceOf(AddDashboardResponse::class)
            ->and($dashboard->id)->toBe($this->testedDashboardId)
            ->and($dashboard->name)->toBe($this->testedDashboardName)
            ->and($dashboard->description)->toBe($this->testedDashboardDescription)
            ->and($dashboard->createdAt->getTimestamp())->toBe($this->testedDashboardCreatedAt->getTimestamp())
            ->and($dashboard->updatedAt->getTimestamp())->toBeGreaterThanOrEqual($this->testedDashboardUpdatedAt->getTimestamp());
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

        ($this->useCase)($this->testedAddDashboardRequest, $this->presenter);

        expect($this->presenter->getResponseStatus())->toBeInstanceOf(ForbiddenResponse::class);
    }
);

it(
    'should present a AddDashboardResponse as allowed READ_WRITE user',
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
        $this->readAccessGroupRepository
            ->expects($this->once())
            ->method('findByContact')
            ->willReturn([]);
        $this->writeDashboardRepository
            ->expects($this->once())
            ->method('add')
            ->willReturn($this->testedDashboard->getId());
        $this->readDashboardRepository
            ->expects($this->once())
            ->method('findOneByAccessGroups')
            ->willReturn($this->testedDashboard);

        ($this->useCase)($this->testedAddDashboardRequest, $this->presenter);

        /** @var AddDashboardResponse $presentedData */
        $dashboard = $this->presenter->getPresentedData();

        expect($dashboard)->toBeInstanceOf(AddDashboardResponse::class)
            ->and($dashboard->id)->toBe($this->testedDashboardId)
            ->and($dashboard->name)->toBe($this->testedDashboardName)
            ->and($dashboard->description)->toBe($this->testedDashboardDescription)
            ->and($dashboard->createdAt->getTimestamp())->toBe($this->testedDashboardCreatedAt->getTimestamp())
            ->and($dashboard->updatedAt->getTimestamp())->toBeGreaterThanOrEqual($this->testedDashboardUpdatedAt->getTimestamp());
    }
);
