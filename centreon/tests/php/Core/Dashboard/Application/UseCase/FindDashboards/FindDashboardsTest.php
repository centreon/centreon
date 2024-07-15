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

namespace Tests\Core\Dashboard\Application\UseCase\FindDashboards;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Contact\Application\Repository\ReadContactRepositoryInterface;
use Core\Dashboard\Application\Exception\DashboardException;
use Core\Dashboard\Application\Repository\ReadDashboardShareRepositoryInterface;
use Core\Dashboard\Application\Repository\ReadDashboardRepositoryInterface;
use Core\Dashboard\Application\UseCase\FindDashboards\FindDashboards;
use Core\Dashboard\Application\UseCase\FindDashboards\FindDashboardsResponse;
use Core\Dashboard\Domain\Model\Dashboard;
use Core\Dashboard\Domain\Model\Refresh;
use Core\Dashboard\Domain\Model\DashboardRights;
use Core\Dashboard\Domain\Model\Refresh\RefreshType;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

beforeEach(function (): void {
    $this->presenter = new FindDashboardsPresenterStub();
    $this->useCase = new FindDashboards(
        $this->readDashboardRepository = $this->createMock(ReadDashboardRepositoryInterface::class),
        $this->createMock(ReadDashboardShareRepositoryInterface::class),
        $this->createMock(RequestParametersInterface::class),
        $this->createMock(ReadContactRepositoryInterface::class),
        $this->rights = $this->createMock(DashboardRights::class),
        $this->contact = $this->createMock(ContactInterface::class),
        $this->readAccessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class),
        $this->iscloudPlatform = false
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
            ->method('findByRequestParameter')->willThrowException(new \Exception());

        ($this->useCase)($this->presenter);

        expect($this->presenter->data)
            ->toBeInstanceOf(ErrorResponse::class)
            ->and($this->presenter->data->getMessage())
            ->toBe(DashboardException::errorWhileSearching()->getMessage());
    }
);

it(
    'should present a ForbiddenResponse when the user does not have the correct role',
    function (): void {
        $this->rights->expects($this->once())
            ->method('hasAdminRole')->willReturn(false);
        $this->rights->expects($this->once())
            ->method('canAccess')->willReturn(false);

        ($this->useCase)($this->presenter);

        expect($this->presenter->data)
            ->toBeInstanceOf(ForbiddenResponse::class)
            ->and($this->presenter->data->getMessage())
            ->toBe(DashboardException::accessNotAllowed()->getMessage());
    }
);

it(
    'should present a FindDashboardsResponse as admin',
    function (): void {
        $this->rights->expects($this->once())
            ->method('hasAdminRole')->willReturn(true);
        $this->readDashboardRepository->expects($this->once())
            ->method('findByRequestParameter')->willReturn([$this->testedDashboard]);

        ($this->useCase)($this->presenter);

        /** @var FindDashboardsResponse $presentedData */
        $presentedData = $this->presenter->data;
        $dashboard = $presentedData->dashboards[0] ?? [];

        expect($presentedData)->toBeInstanceOf(FindDashboardsResponse::class)
            ->and($dashboard->id ?? null)->toBe($this->testedDashboardId)
            ->and($dashboard->name ?? null)->toBe($this->testedDashboardName)
            ->and($dashboard->description)->toBe(null)
            ->and(($dashboard->createdAt ?? null)?->getTimestamp())->toBe(
                $this->testedDashboardCreatedAt->getTimestamp()
            )
            ->and(($dashboard->updatedAt ?? null)?->getTimestamp())->toBeGreaterThanOrEqual(
                $this->testedDashboardUpdatedAt->getTimestamp()
            );
    }
);

it(
    'should present a FindDashboardsResponse as allowed ADMIN user',
    function (): void {
        $this->rights->expects($this->once())
            ->method('hasAdminRole')->willReturn(true);
        $this->readDashboardRepository->expects($this->once())
            ->method('findByRequestParameter')->willReturn([$this->testedDashboard]);

        ($this->useCase)($this->presenter);

        /** @var FindDashboardsResponse $presentedData */
        $presentedData = $this->presenter->data;
        $dashboard = $presentedData->dashboards[0] ?? [];

        expect($presentedData)->toBeInstanceOf(FindDashboardsResponse::class)
            ->and($dashboard->id ?? null)->toBe($this->testedDashboardId)
            ->and($dashboard->name ?? null)->toBe($this->testedDashboardName)
            ->and($dashboard->description)->toBe(null)
            ->and(($dashboard->createdAt ?? null)?->getTimestamp())
            ->toBe($this->testedDashboardCreatedAt->getTimestamp())
            ->and(($dashboard->updatedAt ?? null)?->getTimestamp())
            ->toBeGreaterThanOrEqual($this->testedDashboardUpdatedAt->getTimestamp());
    }
);
it(
    'should present a FindDashboardsResponse as allowed VIEWER user',
    function (): void {
        $this->rights->expects($this->once())
            ->method('hasAdminRole')->willReturn(false);
        $this->rights->expects($this->once())
            ->method('canAccess')->willReturn(true);
        $this->readDashboardRepository->expects($this->once())
            ->method('findByRequestParameterAndContact')->willReturn([$this->testedDashboard]);

        ($this->useCase)($this->presenter);

        /** @var FindDashboardsResponse $presentedData */
        $presentedData = $this->presenter->data;
        $dashboard = $presentedData->dashboards[0] ?? [];

        expect($presentedData)->toBeInstanceOf(FindDashboardsResponse::class)
            ->and($dashboard->id ?? null)->toBe($this->testedDashboardId)
            ->and($dashboard->name ?? null)->toBe($this->testedDashboardName)
            ->and($dashboard->description)->toBe(null)
            ->and(($dashboard->createdAt ?? null)?->getTimestamp())
            ->toBe($this->testedDashboardCreatedAt->getTimestamp())
            ->and(($dashboard->updatedAt ?? null)?->getTimestamp())
            ->toBeGreaterThanOrEqual($this->testedDashboardUpdatedAt->getTimestamp());
    }
);
