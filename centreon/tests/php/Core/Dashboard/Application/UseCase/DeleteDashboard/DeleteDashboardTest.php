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

use Core\Dashboard\Domain\Model\Dashboard;
use Core\Dashboard\Domain\Model\DashboardRights;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Dashboard\Domain\Model\Refresh;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Dashboard\Application\Exception\DashboardException;
use Core\Dashboard\Domain\Model\Refresh\RefreshType;
use Core\Dashboard\Application\UseCase\DeleteDashboard\DeleteDashboard;
use Core\Dashboard\Application\Repository\ReadDashboardRepositoryInterface;
use Core\Dashboard\Application\Repository\WriteDashboardRepositoryInterface;
use Core\Dashboard\Application\Repository\ReadDashboardShareRepositoryInterface;
use Core\Media\Application\Repository\WriteMediaRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

beforeEach(function (): void {
    $this->presenter = new DeleteDashboardPresenterStub();
    $this->useCase = new DeleteDashboard(
        $this->readDashboardRepository = $this->createMock(ReadDashboardRepositoryInterface::class),
        $this->writeDashboardRepository = $this->createMock(WriteDashboardRepositoryInterface::class),
        $this->readDashboardShareRepository = $this->createMock(ReadDashboardShareRepositoryInterface::class),
        $this->rights = $this->createMock(DashboardRights::class),
        $this->contact = $this->createMock(ContactInterface::class),
        $this->readAccessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class),
        $this->mediaRepository = $this->createMock(WriteMediaRepositoryInterface::class),
        $this->isCloudPlatform = false
    );

    $this->testedDashboard = new Dashboard(
        $this->testedDashboardId = 1,
        $this->testedDashboardName = 'dashboard-name',
        $this->testedDashboardCreatedBy = 2,
        $this->testedDashboardUpdatedBy = 3,
        $this->testedDashboardCreatedAt = new \DateTimeImmutable('2023-05-09T12:00:00+00:00'),
        $this->testedDashboardUpdatedAt = new \DateTimeImmutable('2023-05-09T16:00:00+00:00'),
        $this->testedDashboardGlobalRefresh = new Refresh(RefreshType::Global, null),
    );
});

it(
    'should present an ErrorResponse when an exception is thrown',
    function (): void {
        $this->rights->expects($this->once())
            ->method('hasAdminRole')->willReturn(true);
        $this->readDashboardRepository->expects($this->once())
            ->method('existsOne')->willThrowException(new \Exception());

        ($this->useCase)($this->testedDashboardId, $this->presenter);

        expect($this->presenter->data)
            ->toBeInstanceOf(ErrorResponse::class)
            ->and($this->presenter->data->getMessage())
            ->toBe(DashboardException::errorWhileDeleting()->getMessage());
    }
);

it(
    'should present a ForbiddenResponse when the user does not have the correct role',
    function (): void {
        $this->rights->expects($this->once())
            ->method('hasAdminRole')->willReturn(false);
        $this->rights->expects($this->once())
            ->method('canCreate')->willReturn(false);

        ($this->useCase)($this->testedDashboardId, $this->presenter);

        expect($this->presenter->data)
            ->toBeInstanceOf(ForbiddenResponse::class)
            ->and($this->presenter->data->getMessage())
            ->toBe(DashboardException::accessNotAllowedForWriting()->getMessage());
    }
);

it(
    'should present a NoContentResponse as ADMIN',
    function (): void {
        $this->rights->expects($this->once())
            ->method('hasAdminRole')->willReturn(true);
        $this->readDashboardRepository->expects($this->once())
            ->method('existsOne')->willReturn(true);

        ($this->useCase)($this->testedDashboardId, $this->presenter);

        expect($this->presenter->data)
            ->toBeInstanceOf(NoContentResponse::class);
    }
);

it(
    'should present a NoContentResponse as allowed CREATOR user',
    function (): void {
        $this->rights->expects($this->once())
            ->method('hasAdminRole')->willReturn(false);
        $this->rights->expects($this->once())
            ->method('canCreate')->willReturn(true);
        $this->rights->expects($this->once())
            ->method('canDelete')->willReturn(true);
        $this->readDashboardRepository->expects($this->once())
            ->method('findOneByContact')->willReturn($this->testedDashboard);
        $this->writeDashboardRepository->expects($this->once())
            ->method('delete');

        ($this->useCase)($this->testedDashboardId, $this->presenter);

        expect($this->presenter->data)->toBeInstanceOf(NoContentResponse::class);
    }
);

it(
    'should present a ForbiddenResponse as NOT allowed CREATOR user',
    function (): void {
        $this->rights->expects($this->once())
            ->method('hasAdminRole')->willReturn(false);
        $this->rights->expects($this->once())
            ->method('canCreate')->willReturn(true);
        $this->rights->expects($this->once())
            ->method('canDelete')->willReturn(false);
        $this->readDashboardRepository->expects($this->once())
            ->method('findOneByContact')->willReturn($this->testedDashboard);
        $this->writeDashboardRepository->expects($this->never())
            ->method('delete');

        ($this->useCase)($this->testedDashboardId, $this->presenter);

        expect($this->presenter->data)->toBeInstanceOf(ForbiddenResponse::class);
    }
);

it(
    'should present a NotFoundResponse when the dashboard does not exist',
    function (): void {
        $this->rights->expects($this->once())
            ->method('hasAdminRole')->willReturn(false);
        $this->rights->expects($this->once())
            ->method('canCreate')->willReturn(true);
        $this->readDashboardRepository->expects($this->once())
            ->method('findOneByContact')->willReturn(null);

        ($this->useCase)($this->testedDashboardId, $this->presenter);

        expect($this->presenter->data)->toBeInstanceOf(NotFoundResponse::class);
    }
);
