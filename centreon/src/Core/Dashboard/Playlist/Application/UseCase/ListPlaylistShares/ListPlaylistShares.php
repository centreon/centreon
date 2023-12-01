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

namespace Core\Dashboard\Playlist\Application\UseCase\ListPlaylistShares;

use Assert\AssertionFailedException;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Contact\Application\Repository\ReadContactGroupRepositoryInterface;
use Core\Contact\Domain\Model\ContactGroup;
use Core\Dashboard\Domain\Model\DashboardRights;
use Core\Dashboard\Playlist\Application\Exception\PlaylistException;
use Core\Dashboard\Playlist\Application\Repository\ReadPlaylistRepositoryInterface;
use Core\Dashboard\Playlist\Application\Repository\ReadPlaylistShareRepositoryInterface;
use Core\Dashboard\Playlist\Domain\Model\PlaylistContactGroupShare;
use Core\Dashboard\Playlist\Domain\Model\PlaylistContactShare;
use Core\Dashboard\Playlist\Domain\Model\PlaylistShare;

final class ListPlaylistShares
{
    use LoggerTrait;

    /**
     * @param DashboardRights $rights
     * @param ContactInterface $user
     * @param ReadPlaylistRepositoryInterface $readPlaylistRepository
     * @param ReadPlaylistShareRepositoryInterface $readPlaylistShareRepository
     * @param ReadContactGroupRepositoryInterface $readContactGroupRepository
     */
    public function __construct(
        private readonly DashboardRights $rights,
        private readonly ContactInterface $user,
        private readonly ReadPlaylistRepositoryInterface $readPlaylistRepository,
        private readonly ReadPlaylistShareRepositoryInterface $readPlaylistShareRepository,
        private readonly ReadContactGroupRepositoryInterface $readContactGroupRepository
    ) {}

    /**
     * @param int $playlistId
     * @param ListPlaylistSharesPresenterInterface $presenter
     */
    public function __invoke(int $playlistId, ListPlaylistSharesPresenterInterface $presenter): void
    {
        try {
            if (! $this->rights->canAccess()) {
                $this->error('User does not have sufficient rigths to list playlist shares');
                $presenter->presentResponse(new ForbiddenResponse(
                    PlaylistException::accessNotAllowed()->getMessage()
                ));

                return;
            }

            if (! $this->readPlaylistRepository->exists($playlistId)) {
                $this->error('Playlist not found', ['playlist_id' => $playlistId]);
                $presenter->presentResponse(new NotFoundResponse('Playlist'));

                return;
            }

            if (
                ! $this->rights->hasAdminRole()
                && ! $this->readPlaylistShareRepository->exists($playlistId, $this->user)
            ) {
                $this->error('Playlist not shared with the user', ['contact_id' => $this->user->getId()]);
                $presenter->presentResponse(
                    new InvalidArgumentResponse(PlaylistException::playlistNotShared($playlistId))
                );

                return;
            }

            $shares = $this->findPlaylistShares($playlistId);

            $presenter->presentResponse($this->createResponse($shares));
        } catch (AssertionFailedException $ex) {
            $this->error(PlaylistException::errorWhileListingShares()->getMessage(), ['trace' => (string) $ex]);
            $presenter->presentResponse(new InvalidArgumentResponse($ex->getMessage()));
        } catch (\Throwable $ex) {
            $this->error(PlaylistException::errorWhileListingShares()->getMessage(), ['trace' => (string) $ex]);
            $presenter->presentResponse(new ErrorResponse(PlaylistException::errorWhileListingShares()));
        }
    }

    /**
     * @param int $playlistId
     *
     * @throws \Throwable|AssertionFailedException
     *
     * @return PlaylistShare
     */
    private function findPlaylistShares(int $playlistId): PlaylistShare {
        if ($this->rights->hasAdminRole()) {
            return $this->readPlaylistShareRepository->findByPlaylistId($playlistId);
        }
        $userContactGroups = $this->readContactGroupRepository->findAllByUserId($this->user->getId());
        $userContactGroupIds = array_map(
            fn (ContactGroup $contactGroup): int => $contactGroup->getId(), $userContactGroups
        );

        return $this->readPlaylistShareRepository->findByPlaylistIdAndContactGroupIds(
            $playlistId,
            $userContactGroupIds
        );
    }

    /**
     * @param PlaylistShare $shares
     *
     * @return ListPlaylistSharesResponse
     */
    private function createResponse(PlaylistShare $shares): ListPlaylistSharesResponse
    {
        $response = new ListPlaylistSharesResponse();
        $response->contacts = array_map(function (PlaylistContactShare $contactShare): array {
            return [
                'id' => $contactShare->getContactId(),
                'name' => $contactShare->getContactName(),
                'role' => $contactShare->getRole(),
            ];
        }, $shares->getPlaylistContactShares());
        $response->contactGroups = array_map(function (PlaylistContactGroupShare $contactShare): array {
            return [
                'id' => $contactShare->getContactGroupId(),
                'name' => $contactShare->getContactGroupName(),
                'role' => $contactShare->getRole(),
            ];
        }, $shares->getPlaylistContactGroupShares());

        return $response;
    }
}