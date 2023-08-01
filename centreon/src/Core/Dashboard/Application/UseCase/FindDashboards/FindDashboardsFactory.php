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

namespace Core\Dashboard\Application\UseCase\FindDashboards;

use Core\Dashboard\Application\UseCase\FindDashboards\Response\DashboardResponseDto;
use Core\Dashboard\Application\UseCase\FindDashboards\Response\UserResponseDto;
use Core\Dashboard\Domain\Model\Dashboard;
use Core\Dashboard\Domain\Model\Role\DashboardSharingRole;
use Core\Dashboard\Domain\Model\Share\DashboardSharingRoles;

final class FindDashboardsFactory
{
    /**
     * @param list<Dashboard> $dashboards
     * @param array<int, array{id: int, name: string}> $contactNames
     * @param array<int, DashboardSharingRoles> $sharingRolesList
     * @param DashboardSharingRole $defaultRole
     *
     * @return FindDashboardsResponse
     */
    public static function createResponse(
        array $dashboards,
        array $contactNames,
        array $sharingRolesList,
        DashboardSharingRole $defaultRole
    ): FindDashboardsResponse {
        $response = new FindDashboardsResponse();

        foreach ($dashboards as $dashboard) {
            $sharingRoles = $sharingRolesList[$dashboard->getId()] ?? null;
            $ownRole = $defaultRole->getTheMostPermissiveOfBoth($sharingRoles?->getTheMostPermissiveRole());

            $dto = new DashboardResponseDto();

            $dto->id = $dashboard->getId();
            $dto->name = $dashboard->getName();
            $dto->description = $dashboard->getDescription();
            $dto->createdAt = $dashboard->getCreatedAt();
            $dto->updatedAt = $dashboard->getUpdatedAt();
            $dto->ownRole = $ownRole;

            if (null !== ($contactId = $dashboard->getCreatedBy())) {
                $dto->createdBy = new UserResponseDto();
                $dto->createdBy->id = $contactId;
                $dto->createdBy->name = $contactNames[$contactId]['name'] ?? '';
            }
            if (null !== ($contactId = $dashboard->getCreatedBy())) {
                $dto->updatedBy = new UserResponseDto();
                $dto->updatedBy->id = $contactId;
                $dto->updatedBy->name = $contactNames[$contactId]['name'] ?? '';
            }

            $response->dashboards[] = $dto;
        }

        return $response;
    }
}
