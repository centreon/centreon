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

namespace Tests\Core\Dashboard\Application\UseCase\PartialUpdateDashboard;

use Core\Dashboard\Application\Exception\DashboardException;
use Core\Dashboard\Application\UseCase\PartialUpdateDashboard\PartialUpdateDashboardPanelsDifference;
use Core\Dashboard\Application\UseCase\PartialUpdateDashboard\Request\PanelRequestDto;

beforeEach(function (): void {
    $this->randomName = static fn(): string => 'panel-' . mb_substr(md5(random_bytes(10)), 0, 6);
});

it(
    'should return items to Delete',
    function (): void {
        $difference = new PartialUpdateDashboardPanelsDifference(
            panelIdsFromRepository: [1, 2, 3],
            panelsFromRequest: []
        );

        $toDelete = $difference->getPanelIdsToDelete();
        $toCreate = $difference->getPanelsToCreate();
        $toUpdate = $difference->getPanelsToUpdate();

        expect($toDelete)->toHaveCount(3)
            ->and($toCreate)->toHaveCount(0)
            ->and($toUpdate)->toHaveCount(0)
            ->and($toDelete)->toBe([1, 2, 3]);
    }
);

it(
    'should return items to Create',
    function (): void {
        $difference = new PartialUpdateDashboardPanelsDifference(
            panelIdsFromRepository: [],
            panelsFromRequest: [
                new PanelRequestDto(name: $panelName1 = ($this->randomName)(), widgetType: 'non-empty'),
                new PanelRequestDto(name: $panelName2 = ($this->randomName)(), widgetType: 'non-empty'),
            ]
        );

        $toDelete = $difference->getPanelIdsToDelete();
        $toCreate = $difference->getPanelsToCreate();
        $toUpdate = $difference->getPanelsToUpdate();

        expect($toDelete)->toHaveCount(0)
            ->and($toCreate)->toHaveCount(2)
            ->and($toUpdate)->toHaveCount(0)
            ->and($toCreate[0]->getName())->toBe($panelName1)
            ->and($toCreate[1]->getName())->toBe($panelName2);
    }
);

it(
    'should return items to Update',
    function (): void {
        $difference = new PartialUpdateDashboardPanelsDifference(
            panelIdsFromRepository: [1, 2],
            panelsFromRequest: [
                new PanelRequestDto(id: 1, name: $panelName1 = ($this->randomName)(), widgetType: 'non-empty'),
                new PanelRequestDto(id: 2, name: $panelName2 = ($this->randomName)(), widgetType: 'non-empty'),
            ]
        );

        $toDelete = $difference->getPanelIdsToDelete();
        $toCreate = $difference->getPanelsToCreate();
        $toUpdate = $difference->getPanelsToUpdate();

        expect($toDelete)->toHaveCount(0)
            ->and($toCreate)->toHaveCount(0)
            ->and($toUpdate)->toHaveCount(2)
            ->and($toUpdate[0]->getId())->toBe(1)->and($toUpdate[0]->getName())->toBe($panelName1)
            ->and($toUpdate[1]->getId())->toBe(2)->and($toUpdate[1]->getName())->toBe($panelName2);
    }
);

it(
    'should return items to Create + Update + Update',
    function (): void {
        $difference = new PartialUpdateDashboardPanelsDifference(
            panelIdsFromRepository: [1, 2, 3, 4],
            panelsFromRequest: [
                new PanelRequestDto(id: 1, name: $panelName1 = ($this->randomName)(), widgetType: 'non-empty'),
                new PanelRequestDto(name: $panelName2 = ($this->randomName)(), widgetType: 'non-empty'),
                new PanelRequestDto(name: $panelName3 = ($this->randomName)(), widgetType: 'non-empty'),
            ]
        );

        $toDelete = $difference->getPanelIdsToDelete();
        $toCreate = $difference->getPanelsToCreate();
        $toUpdate = $difference->getPanelsToUpdate();

        expect($toDelete)->toHaveCount(3)
            ->and($toCreate)->toHaveCount(2)
            ->and($toUpdate)->toHaveCount(1)
            ->and($toDelete)->toBe([2, 3, 4])
            ->and($toCreate[0]->getName())->toBe($panelName2)
            ->and($toCreate[1]->getName())->toBe($panelName3)
            ->and($toUpdate[0]->getId())->toBe(1)->and($toUpdate[0]->getName())->toBe($panelName1);
    }
);

it(
    'should raise a DashboardException when trying to update a panel which does not belongs to the dashboard',
    function (array $existingIds, int $newPanelId): void {
        $this->expectExceptionObject(
            DashboardException::errorTryingToUpdateAPanelWhichDoesNotBelongsToTheDashboard()
        );
        new PartialUpdateDashboardPanelsDifference(
            panelIdsFromRepository: $existingIds,
            panelsFromRequest: [new PanelRequestDto(id: $newPanelId, name: 'any-name', widgetType: 'non-empty')]
        );
    }
)->with([
    [[/* no ids */], 42],
    [[1, 2], 42],
]);
