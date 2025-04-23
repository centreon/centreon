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

use Core\Dashboard\Application\UseCase\FindDashboard\Response\{PanelResponseDto, RefreshResponseDto, ThumbnailResponseDto, UserResponseDto};
use Core\Dashboard\Domain\Model\Dashboard;
use Core\Dashboard\Domain\Model\DashboardPanel;
use Core\Dashboard\Domain\Model\Role\DashboardSharingRole;
use Core\Dashboard\Domain\Model\Share\DashboardContactGroupShare;
use Core\Dashboard\Domain\Model\Share\DashboardContactShare;
use Core\Dashboard\Domain\Model\Share\DashboardSharingRoles;

final class FindDashboardFactory
{
    /**
     * @param Dashboard $dashboard
     * @param array<int, array{id: int, name: string}> $contactNames
     * @param array<DashboardPanel> $panels
     * @param DashboardSharingRoles $sharingRoles
     * @param array<int, array<DashboardContactShare>> $contactShares
     * @param array<int, array<DashboardContactGroupShare>> $contactGroupShares
     * @param DashboardSharingRole $defaultRole
     *
     * @return FindDashboardResponse
     */
    public static function createResponse(
        Dashboard $dashboard,
        array $contactNames,
        array $panels,
        DashboardSharingRoles $sharingRoles,
        array $contactShares,
        array $contactGroupShares,
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

        // Add shares only if the user if editor, as the viewers should not be able to see shares.
        if ($ownRole === DashboardSharingRole::Editor && array_key_exists($dashboard->getId(), $contactShares)) {
            $response->shares['contacts'] = array_map(static fn (DashboardContactShare $contactShare): array => [
                'id' => $contactShare->getContactId(),
                'name' => $contactShare->getContactName(),
                'email' => $contactShare->getContactEmail(),
                'role' => $contactShare->getRole(),
            ],
            $contactShares[$dashboard->getId()]);
        }

        if ($ownRole === DashboardSharingRole::Editor && array_key_exists($dashboard->getId(), $contactGroupShares)) {
            $response->shares['contact_groups'] = array_map(
                static fn (DashboardContactGroupShare $contactGroupShare): array => [
                    'id' => $contactGroupShare->getContactGroupId(),
                    'name' => $contactGroupShare->getContactGroupName(),
                    'role' => $contactGroupShare->getRole(),
                ],
                $contactGroupShares[$dashboard->getId()]);
        }

        $response->panels = array_map(self::dashboardPanelToDto(...), $panels);
        $response->refresh = new RefreshResponseDto();
        $response->refresh->refreshType = $dashboard->getRefresh()->getRefreshType();
        $response->refresh->refreshInterval = $dashboard->getRefresh()->getRefreshInterval();

        if ($dashboard->getThumbnail() !== null) {
            $response->thumbnail = new ThumbnailResponseDto(
                $dashboard->getThumbnail()->getId(),
                $dashboard->getThumbnail()->getFilename(),
                $dashboard->getThumbnail()->getDirectory()
            );
        }

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
