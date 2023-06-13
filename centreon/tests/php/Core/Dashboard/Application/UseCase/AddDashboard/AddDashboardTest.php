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
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Dashboard\Application\Exception\DashboardException;
use Core\Dashboard\Application\Repository\ReadDashboardRepositoryInterface;
use Core\Dashboard\Application\Repository\WriteDashboardRelationRepositoryInterface;
use Core\Dashboard\Application\Repository\WriteDashboardRepositoryInterface;
use Core\Dashboard\Application\UseCase\AddDashboard\AddDashboard;
use Core\Dashboard\Application\UseCase\AddDashboard\AddDashboardRequest;
use Core\Dashboard\Application\UseCase\AddDashboard\AddDashboardResponse;
use Core\Dashboard\Domain\Model\Dashboard;
use Core\Dashboard\Domain\Model\DashboardRights;

beforeEach(function (): void {
    $this->presenter = new AddDashboardPresenterStub();
    $this->useCase = new AddDashboard(
        $this->readDashboardRepository = $this->createMock(ReadDashboardRepositoryInterface::class),
        $this->writeDashboardRepository = $this->createMock(WriteDashboardRepositoryInterface::class),
        $this->writeDashboardRelationRepository = $this->createMock(WriteDashboardRelationRepositoryInterface::class),
        $this->createMock(DataStorageEngineInterface::class),
        $this->rights = $this->createMock(DashboardRights::class),
        $this->contact = $this->createMock(ContactInterface::class),
    );

    $this->testedAddDashboardRequest = new AddDashboardRequest();
    $this->testedAddDashboardRequest->name = 'added-dashboard';

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

it(
    'should present an ErrorResponse when a generic exception is thrown',
    function (): void {
        $this->rights->expects($this->once())
            ->method('hasAdminRole')->willThrowException(new \Exception());

        ($this->useCase)($this->testedAddDashboardRequest, $this->presenter);

        expect($this->presenter->data)
            ->toBeInstanceOf(ErrorResponse::class)
            ->and($this->presenter->data->getMessage())
            ->toBe(DashboardException::errorWhileAdding()->getMessage());
    }
);

it(
    'should present an ErrorResponse with a custom message when a DashboardException is thrown',
    function (): void {
        $this->rights->expects($this->once())
            ->method('hasAdminRole')
            ->willThrowException(new DashboardException($msg = uniqid('fake message ', true)));

        ($this->useCase)($this->testedAddDashboardRequest, $this->presenter);

        expect($this->presenter->data)
            ->toBeInstanceOf(ErrorResponse::class)
            ->and($this->presenter->data->getMessage())
            ->toBe($msg);
    }
);

it(
    'should present a InvalidArgumentResponse when a model field value is not valid',
    function (): void {
        $this->rights->expects($this->once())
            ->method('hasAdminRole')->willReturn(true);

        $this->testedAddDashboardRequest->name = '';
        $expectedException = AssertionException::notEmptyString('NewDashboard::name');

        ($this->useCase)($this->testedAddDashboardRequest, $this->presenter);

        expect($this->presenter->data)
            ->toBeInstanceOf(InvalidArgumentResponse::class)
            ->and($this->presenter->data->getMessage())
            ->toBe($expectedException->getMessage());
    }
);

it(
    'should present an ErrorResponse if the newly created dashboard cannot be retrieved as ADMIN',
    function (): void {
        $this->rights->expects($this->once())
            ->method('hasAdminRole')->willReturn(true);
        $this->contact->expects($this->atLeastOnce())
            ->method('getId')->willReturn(1);
        $this->writeDashboardRepository->expects($this->once())
            ->method('add')->willReturn($this->testedDashboard->getId());
        $this->readDashboardRepository->expects($this->once())
            ->method('findOne')->willReturn(null); // the failure

        ($this->useCase)($this->testedAddDashboardRequest, $this->presenter);

        expect($this->presenter->data)
            ->toBeInstanceOf(ErrorResponse::class)
            ->and($this->presenter->data->getMessage())
            ->toBe(DashboardException::errorWhileRetrievingJustCreated()->getMessage());
    }
);

it(
    'should present an ErrorResponse if the newly created dashboard cannot be retrieved as CREATOR',
    function (): void {
        $this->rights->expects($this->once())
            ->method('hasAdminRole')->willReturn(false);
        $this->rights->expects($this->once())
            ->method('canCreate')->willReturn(true);
        $this->contact->expects($this->atLeastOnce())
            ->method('getId')->willReturn(1);
        $this->writeDashboardRepository->expects($this->once())
            ->method('add')->willReturn($this->testedDashboard->getId());
        $this->readDashboardRepository->expects($this->once())
            ->method('findOneByContact')->willReturn(null); // the failure

        ($this->useCase)($this->testedAddDashboardRequest, $this->presenter);

        expect($this->presenter->data)
            ->toBeInstanceOf(ErrorResponse::class)
            ->and($this->presenter->data->getMessage())
            ->toBe(DashboardException::errorWhileRetrievingJustCreated()->getMessage());
    }
);

it(
    'should present a ForbiddenResponse when the user does not have the correct role',
    function (): void {
        $this->rights->expects($this->once())
            ->method('hasAdminRole')->willReturn(false);
        $this->rights->expects($this->once())
            ->method('canCreate')->willReturn(false);

        ($this->useCase)($this->testedAddDashboardRequest, $this->presenter);

        expect($this->presenter->data)
            ->toBeInstanceOf(ForbiddenResponse::class)
            ->and($this->presenter->data->getMessage())
            ->toBe(DashboardException::accessNotAllowedForWriting()->getMessage());
    }
);

it(
    'should present a AddDashboardResponse as ADMIN',
    function (): void {
        $this->rights->expects($this->once())
            ->method('hasAdminRole')->willReturn(true);
        $this->contact->expects($this->atLeastOnce())
            ->method('getId')->willReturn(1);
        $this->writeDashboardRepository->expects($this->once())
            ->method('add')->willReturn($this->testedDashboard->getId());
        $this->readDashboardRepository->expects($this->once())
            ->method('findOne')->willReturn($this->testedDashboard);

        ($this->useCase)($this->testedAddDashboardRequest, $this->presenter);

        /** @var AddDashboardResponse $presentedData */
        $dashboard = $this->presenter->data;

        expect($dashboard)->toBeInstanceOf(AddDashboardResponse::class)
            ->and($dashboard->id)->toBe($this->testedDashboardId)
            ->and($dashboard->name)->toBe($this->testedDashboardName)
            ->and($dashboard->description)->toBe($this->testedDashboardDescription)
            ->and($dashboard->createdAt->getTimestamp())->toBe($this->testedDashboardCreatedAt->getTimestamp())
            ->and($dashboard->updatedAt->getTimestamp())->toBeGreaterThanOrEqual(
                $this->testedDashboardUpdatedAt->getTimestamp()
            );
    }
);

it(
    'should present a AddDashboardResponse as allowed CREATOR user',
    function (): void {
        $this->rights->expects($this->once())
            ->method('hasAdminRole')->willReturn(false);
        $this->rights->expects($this->once())
            ->method('canCreate')->willReturn(true);
        $this->contact->expects($this->atLeastOnce())
            ->method('getId')->willReturn(1);
        $this->writeDashboardRepository->expects($this->once())
            ->method('add')->willReturn($this->testedDashboard->getId());
        $this->readDashboardRepository->expects($this->once())
            ->method('findOneByContact')->willReturn($this->testedDashboard);

        ($this->useCase)($this->testedAddDashboardRequest, $this->presenter);

        /** @var AddDashboardResponse $presentedData */
        $dashboard = $this->presenter->data;

        expect($dashboard)->toBeInstanceOf(AddDashboardResponse::class)
            ->and($dashboard->id)->toBe($this->testedDashboardId)
            ->and($dashboard->name)->toBe($this->testedDashboardName)
            ->and($dashboard->description)->toBe($this->testedDashboardDescription)
            ->and($dashboard->createdAt->getTimestamp())->toBe($this->testedDashboardCreatedAt->getTimestamp())
            ->and($dashboard->updatedAt->getTimestamp())->toBeGreaterThanOrEqual(
                $this->testedDashboardUpdatedAt->getTimestamp()
            );
    }
);

