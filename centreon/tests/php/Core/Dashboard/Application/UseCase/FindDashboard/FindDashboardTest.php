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
use Core\Contact\Application\Repository\ReadContactRepositoryInterface;
use Core\Dashboard\Application\Exception\DashboardException;
use Core\Dashboard\Application\Repository\ReadDashboardPanelRepositoryInterface;
use Core\Dashboard\Application\Repository\ReadDashboardRepositoryInterface;
use Core\Dashboard\Application\UseCase\FindDashboard\FindDashboard;
use Core\Dashboard\Application\UseCase\FindDashboard\FindDashboardResponse;
use Core\Dashboard\Domain\Model\Dashboard;
use Core\Dashboard\Domain\Model\DashboardPanel;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

beforeEach(function (): void {
    $this->presenter = new FindDashboardPresenterStub();
    $this->useCase = new FindDashboard(
        $this->readDashboardRepository = $this->createMock(ReadDashboardRepositoryInterface::class),
        $this->readDashboardPanelRepository = $this->createMock(ReadDashboardPanelRepositoryInterface::class),
        $this->createMock(ReadAccessGroupRepositoryInterface::class),
        $this->readContactRepository = $this->createMock(ReadContactRepositoryInterface::class),
        $this->contact = $this->createMock(ContactInterface::class)
    );

    $this->testedDashboard = new Dashboard(
        $this->testedDashboardId = random_int(1, 1_000_000),
        $this->testedDashboardName = uniqid('name', true),
        $this->testedDashboardDescription = uniqid('description', true),
        $this->testedDashboardCreatedBy = random_int(1, 1_000_000),
        $this->testedDashboardUpdatedBy = random_int(1, 1_000_000),
        $this->testedDashboardCreatedAt = new \DateTimeImmutable('2023-05-09T12:00:00+00:00'),
        $this->testedDashboardUpdatedAt = new \DateTimeImmutable('2023-05-09T16:00:00+00:00'),
    );

    $this->testedDashboardPanel = new DashboardPanel(
        $this->testedPanelId = random_int(1, 1_000_000),
        $this->testedPanelName = uniqid('name', true),
        $this->testedPanelWidgetType = uniqid('widgetType', true),
        $this->testedPanelWidgetSettings = [uniqid('key', true) => 42],
        $this->testedPanelLayoutX = random_int(1, 1_000),
        $this->testedPanelLayoutY = random_int(1, 1_000),
        $this->testedPanelLayoutWidth = random_int(1, 1_000),
        $this->testedPanelLayoutHeight = random_int(1, 1_000),
        $this->testedPanelLayoutMinWidth = random_int(1, 1_000),
        $this->testedPanelLayoutMinHeight = random_int(1, 1_000),
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

        expect($this->presenter->data)
            ->toBeInstanceOf(ErrorResponse::class)
            ->and($this->presenter->data->getMessage())
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

        expect($this->presenter->data)
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

        expect($this->presenter->data)
            ->toBeInstanceOf(ForbiddenResponse::class)
            ->and($this->presenter->data->getMessage())
            ->toBe(DashboardException::accessNotAllowed()->getMessage());
    }
);

it(
    'should present a FindDashboardResponse as admin',
    function (): void {
        $this->contact->expects($this->once())->method('isAdmin')->willReturn(true);
        $this->readDashboardRepository->expects($this->once())->method('findOne')->willReturn($this->testedDashboard);
        $this->readContactRepository->expects($this->once())->method('findNamesByIds')
            ->willReturn([
                $this->testedDashboardCreatedBy => ['id' => $this->testedDashboardCreatedBy, 'name' => $creator = uniqid('creator', true)],
                $this->testedDashboardUpdatedBy => ['id' => $this->testedDashboardUpdatedBy, 'name' => $updater = uniqid('updater', true)],
            ]);
        $this->readDashboardPanelRepository->expects($this->once())->method('findPanelsByDashboardId')
            ->willReturn([$this->testedDashboardPanel]);

        ($this->useCase)(1, $this->presenter);

        $dashboard = $this->presenter->data;

        expect($dashboard)->toBeInstanceOf(FindDashboardResponse::class)
            ->and($dashboard->id)->toBe($this->testedDashboardId)
            ->and($dashboard->name)->toBe($this->testedDashboardName)
            ->and($dashboard->panels)->toHaveCount(1)
            ->and($dashboard->panels[0]->id)->toBe($this->testedPanelId)
            ->and($dashboard->panels[0]->name)->toBe($this->testedPanelName)
            ->and($dashboard->panels[0]->widgetType)->toBe($this->testedPanelWidgetType)
            ->and($dashboard->panels[0]->widgetSettings)->toBe($this->testedPanelWidgetSettings)
            ->and($dashboard->panels[0]->layout->posX)->toBe($this->testedPanelLayoutX)
            ->and($dashboard->panels[0]->layout->posY)->toBe($this->testedPanelLayoutY)
            ->and($dashboard->panels[0]->layout->width)->toBe($this->testedPanelLayoutWidth)
            ->and($dashboard->panels[0]->layout->height)->toBe($this->testedPanelLayoutHeight)
            ->and($dashboard->panels[0]->layout->minWidth)->toBe($this->testedPanelLayoutMinWidth)
            ->and($dashboard->panels[0]->layout->minHeight)->toBe($this->testedPanelLayoutMinHeight)
            ->and($dashboard->createdBy->id)->toBe($this->testedDashboardCreatedBy)
            ->and($dashboard->createdBy->name)->toBe($creator)
            ->and($dashboard->updatedBy->id)->toBe($this->testedDashboardUpdatedBy)
            ->and($dashboard->updatedBy->name)->toBe($updater)
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
        $dashboard = $this->presenter->data;

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
        $dashboard = $this->presenter->data;

        expect($dashboard)->toBeInstanceOf(FindDashboardResponse::class)
            ->and($dashboard->id)->toBe($this->testedDashboardId)
            ->and($dashboard->name)->toBe($this->testedDashboardName)
            ->and($dashboard->description)->toBe($this->testedDashboardDescription)
            ->and($dashboard->createdAt->getTimestamp())->toBe($this->testedDashboardCreatedAt->getTimestamp())
            ->and($dashboard->updatedAt->getTimestamp())->toBeGreaterThanOrEqual($this->testedDashboardUpdatedAt->getTimestamp());
    }
);
