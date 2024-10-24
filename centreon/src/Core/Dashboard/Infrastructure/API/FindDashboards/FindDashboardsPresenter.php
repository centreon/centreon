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

namespace Core\Dashboard\Infrastructure\API\FindDashboards;

use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Dashboard\Application\UseCase\FindDashboards\FindDashboardsPresenterInterface;
use Core\Dashboard\Application\UseCase\FindDashboards\FindDashboardsResponse;
use Core\Dashboard\Application\UseCase\FindDashboards\Response\ThumbnailResponseDto;
use Core\Dashboard\Application\UseCase\FindDashboards\Response\UserResponseDto;
use Core\Dashboard\Domain\Model\Role\DashboardSharingRole;
use Core\Dashboard\Infrastructure\Model\DashboardSharingRoleConverter;
use Core\Infrastructure\Common\Api\DefaultPresenter;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Infrastructure\Common\Presenter\PresenterTrait;

final class FindDashboardsPresenter extends DefaultPresenter implements FindDashboardsPresenterInterface
{
    use PresenterTrait;

    public function __construct(
        protected RequestParametersInterface $requestParameters,
        PresenterFormatterInterface $presenterFormatter,
    ) {
        parent::__construct($presenterFormatter);
    }

    public function presentResponse(FindDashboardsResponse|ResponseStatusInterface $data): void
    {
        if ($data instanceof FindDashboardsResponse) {
            $result = [];
            foreach ($data->dashboards as $dashboard) {
                $result[] = [
                    'id' => $dashboard->id,
                    'name' => $dashboard->name,
                    'description' => $dashboard->description,
                    'created_by' => $this->userToOptionalArray($dashboard->createdBy),
                    'updated_by' => $this->userToOptionalArray($dashboard->updatedBy),
                    'created_at' => $this->formatDateToIso8601($dashboard->createdAt),
                    'updated_at' => $this->formatDateToIso8601($dashboard->updatedAt),
                    'own_role' => DashboardSharingRoleConverter::toString($dashboard->ownRole),
                    'shares' => $this->formatShares($dashboard->shares),
                    'thumbnail' => $this->formatThumbnail($dashboard->thumbnail),
                ];
            }

            $this->present([
                'result' => $result,
                'meta' => $this->requestParameters->toArray(),
            ]);
        } else {
            $this->setResponseStatus($data);
        }
    }

    /**
     * @param ?UserResponseDto $dto
     *
     * @return null|array{id: int, name: string}
     */
    private function userToOptionalArray(?UserResponseDto $dto): ?array
    {
        return $dto ? [
            'id' => $dto->id,
            'name' => $dto->name,
        ] : null;
    }

    /**
     * @param null|ThumbnailResponseDto $dto
     *
     * @return null|array{id:int, name:string, directory:string}
     */
    private function formatThumbnail(?ThumbnailResponseDto $dto): ?array
    {
        return $dto
            ? [
                'id' => $dto->id,
                'name' => $dto->name,
                'directory' => $dto->directory,
            ]
            : null;

    }

    /**
     * @param array{
     *      contacts: array<int, array{
     *       id: int,
     *       name: string,
     *       email: string,
     *       role: DashboardSharingRole
     *      }>,
     *      contact_groups: array<int, array{
     *       id: int,
     *       name: string,
     *       role: DashboardSharingRole
     *      }>
     *  } $shares
     *
     * @return array{
     *       contacts: array<int, array{
     *        id: int,
     *        name: string,
     *        email: string,
     *        role: string
     *       }>,
     *       contact_groups: array<int, array{
     *        id: int,
     *        name: string,
     *        role: string
     *       }>
     *   }
     */
    private function formatShares(array $shares): array
    {
        $formattedShares = ['contacts' => [], 'contact_groups' => []];
        foreach ($shares['contacts'] as $contact) {
            $formattedShares['contacts'][] = [
                'id' => $contact['id'],
                'name' => $contact['name'],
                'email' => $contact['email'],
                'role' => DashboardSharingRoleConverter::toString($contact['role']),
            ];
        }
        foreach ($shares['contact_groups'] as $contactGroup) {
            $formattedShares['contact_groups'][] = [
                'id' => $contactGroup['id'],
                'name' => $contactGroup['name'],
                'role' => DashboardSharingRoleConverter::toString($contactGroup['role']),
            ];
        }

        return $formattedShares;
    }
}
