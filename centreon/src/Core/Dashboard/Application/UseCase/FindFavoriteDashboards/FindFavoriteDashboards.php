<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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

namespace Core\Dashboard\Application\UseCase\FindFavoriteDashboards;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Contact\Application\Repository\ReadContactRepositoryInterface;
use Core\Dashboard\Application\Exception\DashboardException;
use Core\Dashboard\Application\Repository\ReadDashboardRepositoryInterface;
use Core\Dashboard\Application\Repository\ReadDashboardShareRepositoryInterface;
use Core\Dashboard\Application\UseCase\FindFavoriteDashboards\Response\DashboardResponseDto;
use Core\Dashboard\Application\UseCase\FindFavoriteDashboards\Response\ThumbnailResponseDto;
use Core\Dashboard\Application\UseCase\FindFavoriteDashboards\Response\UserResponseDto;
use Core\Dashboard\Domain\Model\Dashboard;
use Core\Dashboard\Domain\Model\DashboardRights;
use Core\Dashboard\Domain\Model\Role\DashboardSharingRole;
use Core\Dashboard\Domain\Model\Share\DashboardContactGroupShare;
use Core\Dashboard\Domain\Model\Share\DashboardContactShare;
use Core\Dashboard\Domain\Model\Share\DashboardSharingRoles;
use Core\Media\Domain\Model\Media;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;
use Core\UserProfile\Application\Repository\ReadUserProfileRepositoryInterface;
use Throwable;

final class FindFavoriteDashboards
{
    use LoggerTrait;
    public const AUTHORIZED_ACL_GROUPS = ['customer_admin_acl'];

    /** @var int[] */
    private array $usersFavoriteDashboards;

    /**
     * @param RequestParametersInterface $requestParameters
     * @param ReadDashboardRepositoryInterface $dashboardReader
     * @param ReadUserProfileRepositoryInterface $userProfileReader
     * @param ContactInterface $contact
     * @param DashboardRights $rights
     * @param ReadAccessGroupRepositoryInterface $readAccessGroupRepository
     * @param ReadContactRepositoryInterface $readContactRepository
     * @param ReadDashboardShareRepositoryInterface $readDashboardShareRepository
     * @param bool $isCloudPlatform
     */
    public function __construct(
        private readonly RequestParametersInterface $requestParameters,
        private readonly ReadDashboardRepositoryInterface $dashboardReader,
        private readonly ReadUserProfileRepositoryInterface $userProfileReader,
        private readonly ContactInterface $contact,
        private readonly DashboardRights $rights,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly ReadContactRepositoryInterface $readContactRepository,
        private readonly ReadDashboardShareRepositoryInterface $readDashboardShareRepository,
        private readonly bool $isCloudPlatform,
    ) {
    }

    /**
     * @return FindFavoriteDashboardsResponse|ResponseStatusInterface
     */
    public function __invoke(): FindFavoriteDashboardsResponse|ResponseStatusInterface
    {
        try {
            $profile = $this->userProfileReader->findByContact($this->contact);
            $this->usersFavoriteDashboards = $profile !== null ? $profile->getFavoriteDashboards(): [];

            if ($this->usersFavoriteDashboards === []) {
                return new FindFavoriteDashboardsResponse([]);
            }

            // Hack to benifit from existing code.
            $search = $this->requestParameters->getSearch();
            $search = ['id' => ['$in' => $this->usersFavoriteDashboards], ...$search];
            $this->requestParameters->setSearch(json_encode($search, JSON_THROW_ON_ERROR) ?: '');

            return $this->isUserAdmin() ? $this->findDashboardAsAdmin() : $this->findDashboardAsViewer();
        } catch (Throwable $ex) {
            $this->error(
                "Error while searching favorite dashboards : {$ex->getMessage()}",
                [
                    'contact_id' => $this->contact->getId(),
                    'favorite_dashboards' => $this->usersFavoriteDashboards,
                    'request_parameters' => $this->requestParameters->toArray(),
                    'exception' => ['message' => $ex->getMessage(), 'trace' => $ex->getTraceAsString()],
                ]
            );

            return new ErrorResponse(DashboardException::errorWhileSearching());
        }
    }

    /**
     * @throws Throwable
     *
     * @return FindFavoriteDashboardsResponse
     */
    private function findDashboardAsAdmin(): FindFavoriteDashboardsResponse
    {
        $dashboards = $this->dashboardReader->findByRequestParameter($this->requestParameters);

        $dashboardIds = array_map(
            static fn (Dashboard $dashboard): int => $dashboard->getId(),
            $dashboards
        );

        $thumbnails = $this->dashboardReader->findThumbnailsByDashboardIds($dashboardIds);
        $contactIds = $this->extractAllContactIdsFromDashboards($dashboards);

        return $this->createResponse(
            dashboards: $dashboards,
            contactNames: $this->readContactRepository->findNamesByIds(...$contactIds),
            sharingRolesList: $this->readDashboardShareRepository->getMultipleSharingRoles($this->contact, ...$dashboards),
            contactShares: $this->readDashboardShareRepository->findDashboardsContactShares(...$dashboards),
            contactGroupShares: $this->readDashboardShareRepository->findDashboardsContactGroupShares(...$dashboards),
            defaultRole: DashboardSharingRole::Editor,
            thumbnails: $thumbnails
        );
    }

    /**
     * @throws Throwable
     *
     * @return FindFavoriteDashboardsResponse
     */
    private function findDashboardAsViewer(): FindFavoriteDashboardsResponse
    {
        $dashboards = $this->dashboardReader->findByRequestParameterAndContact(
            $this->requestParameters,
            $this->contact,
        );

        $dashboardIds = array_map(
            static fn (Dashboard $dashboard): int => $dashboard->getId(),
            $dashboards
        );

        $thumbnails = $this->dashboardReader->findThumbnailsByDashboardIds($dashboardIds);

        $editorIds = $this->extractAllContactIdsFromDashboards($dashboards);

        $userAccessGroups = $this->readAccessGroupRepository->findByContact($this->contact);
        $accessGroupsIds = array_map(
            static fn (AccessGroup $accessGroup): int => $accessGroup->getId(),
            $userAccessGroups
        );

        $userInCurrentUserAccessGroups = $this->readContactRepository->findContactIdsByAccessGroups($accessGroupsIds);

        return $this->createResponse(
            dashboards: $dashboards,
            contactNames: $this->readContactRepository->findNamesByIds(...$editorIds),
            sharingRolesList: $this->readDashboardShareRepository->getMultipleSharingRoles($this->contact, ...$dashboards),
            contactShares: $this->readDashboardShareRepository->findDashboardsContactSharesByContactIds(
                $userInCurrentUserAccessGroups,
                ...$dashboards
            ),
            contactGroupShares: $this->readDashboardShareRepository->findDashboardsContactGroupSharesByContact($this->contact, ...$dashboards),
            defaultRole: DashboardSharingRole::Viewer,
            thumbnails: $thumbnails
        );
    }

    /**
     * @param list<Dashboard> $dashboards
     *
     * @return int[]
     */
    private function extractAllContactIdsFromDashboards(array $dashboards): array
    {
        $contactIds = [];
        foreach ($dashboards as $dashboard) {
            if ($id = $dashboard->getCreatedBy()) {
                $contactIds[] = $id;
            }
            if ($id = $dashboard->getUpdatedBy()) {
                $contactIds[] = $id;
            }
        }

        return $contactIds;
    }

    /**
     * @throws Throwable
     *
     * @return bool
     */
    private function isUserAdmin(): bool
    {
        if ($this->rights->hasAdminRole()) {
            return true;
        }

        $userAccessGroupNames = array_map(
            static fn (AccessGroup $accessGroup): string => $accessGroup->getName(),
            $this->readAccessGroupRepository->findByContact($this->contact)
        );

        return ! (empty(array_intersect($userAccessGroupNames, self::AUTHORIZED_ACL_GROUPS)))
            && $this->isCloudPlatform;
    }

    /**
     * @param list<Dashboard> $dashboards
     * @param array<int, array{id: int, name: string}> $contactNames
     * @param array<int, DashboardSharingRoles> $sharingRolesList
     * @param array<int, array<DashboardContactShare>> $contactShares
     * @param array<int, array<DashboardContactGroupShare>> $contactGroupShares
     * @param DashboardSharingRole $defaultRole
     * @param array<int, Media> $thumbnails
     *
     * @return FindFavoriteDashboardsResponse
     */
    private function createResponse(
        array $dashboards,
        array $contactNames,
        array $sharingRolesList,
        array $contactShares,
        array $contactGroupShares,
        DashboardSharingRole $defaultRole,
        array $thumbnails
    ): FindFavoriteDashboardsResponse {

        $dashboardsResponse = [];
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
                $dto->thumbnail = new ThumbnailResponseDto(
                    $thumbnail->getId(),
                    $thumbnail->getFilename(),
                    $thumbnail->getDirectory()
                );
            }

            if (in_array($dto->id, $this->usersFavoriteDashboards, true)) {
                $dto->isFavorite = true;
            }

            $dashboardsResponse[] = $dto;
        }

        return new FindFavoriteDashboardsResponse(dashboards: $dashboardsResponse);
    }
}
