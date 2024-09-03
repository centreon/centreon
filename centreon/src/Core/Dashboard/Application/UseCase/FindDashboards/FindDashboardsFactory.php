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
use Core\Dashboard\Application\UseCase\FindDashboards\Response\ThumbnailResponseDto;
use Core\Dashboard\Application\UseCase\FindDashboards\Response\UserResponseDto;
use Core\Dashboard\Domain\Model\Dashboard;
use Core\Dashboard\Domain\Model\Role\DashboardSharingRole;
use Core\Dashboard\Domain\Model\Share\DashboardContactGroupShare;
use Core\Dashboard\Domain\Model\Share\DashboardContactShare;
use Core\Dashboard\Domain\Model\Share\DashboardSharingRoles;
use Core\Media\Domain\Model\Media;

final class FindDashboardsFactory
{
    /**
     * @param list<Dashboard> $dashboards
     * @param array<int, array{id: int, name: string}> $contactNames
     * @param array<int, DashboardSharingRoles> $sharingRolesList
     * @param array<int, array<DashboardContactShare>> $contactShares
     * @param array<int, array<DashboardContactGroupShare>> $contactGroupShares
     * @param DashboardSharingRole $defaultRole
     * @param array<int, Media> $thumbnails
     *
     * @return FindDashboardsResponse
     */
    public static function createResponse(
        array $dashboards,
        array $contactNames,
        array $sharingRolesList,
        array $contactShares,
        array $contactGroupShares,
        DashboardSharingRole $defaultRole,
        array $thumbnails
    ): FindDashboardsResponse {
        $response = new FindDashboardsResponse();

        foreach ($dashboards as $dashboard) {
            $sharingRoles = $sharingRolesList[$dashboard->getId()] ?? null;
            $ownRole = $defaultRole->getTheMostPermissiveOfBoth($sharingRoles?->getTheMostPermissiveRole());

            $thumbnail = $thumbnails[$dashboard->getId()] ?? null;

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

            // Add shares only if the user if editor, as the viewers should not be able to see shares.
            if ($ownRole === DashboardSharingRole::Editor && array_key_exists($dashboard->getId(), $contactShares)) {
                $dto->shares['contacts'] = array_map(static fn (DashboardContactShare $contactShare): array => [
                    'id' => $contactShare->getContactId(),
                    'name' => $contactShare->getContactName(),
                    'email' => $contactShare->getContactEmail(),
                    'role' => $contactShare->getRole(),
                ]
                , $contactShares[$dashboard->getId()]);
            }

            if ($ownRole === DashboardSharingRole::Editor && array_key_exists($dashboard->getId(), $contactGroupShares)) {
                $dto->shares['contact_groups'] = array_map(
                    static fn (DashboardContactGroupShare $contactGroupShare): array => [
                        'id' => $contactGroupShare->getContactGroupId(),
                        'name' => $contactGroupShare->getContactGroupName(),
                        'role' => $contactGroupShare->getRole(),
                    ],
                    $contactGroupShares[$dashboard->getId()]);
            }

            if ($thumbnail !== null) {
                $dto->thumbnail = new ThumbnailResponseDto();
                $dto->thumbnail->id = $thumbnail->getId();
                $dto->thumbnail->name = $thumbnail->getFilename();
                $dto->thumbnail->directory = $thumbnail->getDirectory();
            }

            $response->dashboards[] = $dto;
        }

        return $response;
    }
}
