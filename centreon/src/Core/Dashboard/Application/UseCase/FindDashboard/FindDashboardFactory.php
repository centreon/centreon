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

namespace Core\Dashboard\Application\UseCase\FindDashboard;

use Core\Dashboard\Application\Model\DashboardSharingRoleConverter;
use Core\Dashboard\Application\UseCase\FindDashboard\Response\PanelResponseDto;
use Core\Dashboard\Application\UseCase\FindDashboard\Response\UserResponseDto;
use Core\Dashboard\Domain\Model\Dashboard;
use Core\Dashboard\Domain\Model\DashboardPanel;
use Core\Dashboard\Domain\Model\Role\DashboardSharingRole;
use Core\Dashboard\Domain\Model\Share\DashboardSharingRoles;

final class FindDashboardFactory
{
    /**
     * @param Dashboard $dashboard
     * @param array<int, array{id: int, name: string}> $contactNames
     * @param array<DashboardPanel> $panels
     * @param DashboardSharingRoles $sharingRoles
     * @param DashboardSharingRole $defaultRole
     *
     * @return FindDashboardResponse
     */
    public static function createResponse(
        Dashboard $dashboard,
        array $contactNames,
        array $panels,
        DashboardSharingRoles $sharingRoles,
        DashboardSharingRole $defaultRole
    ): FindDashboardResponse {
        $ownRole = $defaultRole->getTheMostPermissiveOfBoth($sharingRoles->getTheMostPermissiveRole());
        $response = new FindDashboardResponse();

        $response->id = $dashboard->getId();
        $response->name = $dashboard->getName();
        $response->description = $dashboard->getDescription();
        $response->createdAt = $dashboard->getCreatedAt();
        $response->updatedAt = $dashboard->getUpdatedAt();
        $response->ownRole = $ownRole;

        if (null !== ($contactId = $dashboard->getCreatedBy())) {
            $response->createdBy = new UserResponseDto();
            $response->createdBy->id = $contactId;
            $response->createdBy->name = $contactNames[$contactId]['name'] ?? '';
        }

        if (null !== ($contactId = $dashboard->getUpdatedBy())) {
            $response->updatedBy = new UserResponseDto();
            $response->updatedBy->id = $contactId;
            $response->updatedBy->name = $contactNames[$contactId]['name'] ?? '';
        }

        $response->panels = array_map(self::dashboardPanelToDto(...), $panels);

        return $response;
    }

    private static function dashboardPanelToDto(DashboardPanel $panel): PanelResponseDto
    {
        $dto = new PanelResponseDto();

        $dto->id = $panel->getId();
        $dto->name = $panel->getName();
        $dto->widgetType = $panel->getWidgetType();

        $dto->layout->posX = $panel->getLayoutX();
        $dto->layout->posY = $panel->getLayoutY();
        $dto->layout->width = $panel->getLayoutWidth();
        $dto->layout->height = $panel->getLayoutHeight();
        $dto->layout->minWidth = $panel->getLayoutMinWidth();
        $dto->layout->minHeight = $panel->getLayoutMinHeight();

        $dto->widgetSettings = $panel->getWidgetSettings();

        return $dto;
    }
}
