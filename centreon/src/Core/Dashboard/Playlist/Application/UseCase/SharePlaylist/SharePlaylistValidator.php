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

namespace Core\Dashboard\Playlist\Application\UseCase\SharePlaylist;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Contact\Application\Repository\ReadContactGroupRepositoryInterface;
use Core\Contact\Application\Repository\ReadContactRepositoryInterface;
use Core\Dashboard\Playlist\Application\Exception\PlaylistException;
use Core\Dashboard\Playlist\Application\Repository\ReadPlaylistRepositoryInterface;
use Core\Dashboard\Playlist\Application\Repository\ReadPlaylistShareRepositoryInterface;

class SharePlaylistValidator
{
    /**
     * @param ReadPlaylistRepositoryInterface $playlistRepository
     * @param ReadPlaylistShareRepositoryInterface $shareRepository
     * @param ReadContactGroupRepositoryInterface $contactGroupRepository
     * @param ReadContactRepositoryInterface $contactRepository
     */
    public function __construct(
        private readonly ReadPlaylistRepositoryInterface $playlistRepository,
        private readonly ReadPlaylistShareRepositoryInterface $shareRepository,
        private readonly ReadContactGroupRepositoryInterface $contactGroupRepository,
        private readonly ReadContactRepositoryInterface $contactRepository,
    ) {
    }

    /**
     * @param int[] $contactIds
     * @param bool $hasAdminRole
     * @param int[] $contactIdsInUserContactGroups
     *
     * @throws \Throwable|PlaylistException
     */
    public function validateContacts(
        array $contactIds,
        bool $hasAdminRole,
        array $contactIdsInUserContactGroups = []
    ):void {
        $this->validateContactsExist($contactIds);
        $this->validateUniqueContacts($contactIds);
        if (! $hasAdminRole) {
            $this->validateUserHasAccessToContacts($contactIds, $contactIdsInUserContactGroups);
        }
    }

    /**
     * @param int[] $contactGroupIds
     * @param bool $hasAdminRole
     * @param int[] $userContactGroups
     *
     * @throws \Throwable|PlaylistException
     */
    public function validateContactGroups(
        array $contactGroupIds,
        bool $hasAdminRole,
        array $userContactGroups = []
    ): void {
        $this->validateContactGroupsExists($contactGroupIds);
        $this->validateUniqueContactGroups($contactGroupIds);
        if (! $hasAdminRole) {
            $this->validateUserHasAccessToContactGroups($contactGroupIds, $userContactGroups);
        }
    }

    /**
     * @param int $playlistId
     * @param ContactInterface $user
     * @param bool $hasAdminRole
     *
     * @throws \Throwable|PlaylistException
     */
    public function validatePlaylist(int $playlistId, ContactInterface $user, bool $hasAdminRole): void
    {
        $this->validatePlaylistExists($playlistId);
        if (! $hasAdminRole) {
            $this->validateUserCanEditPlaylist($playlistId, $user);
        }
    }

    /**
     * @param int[] $contactIds
     *
     * @throws PlaylistException
     */
    private function validateUniqueContacts(array $contactIds): void
    {
        if(count(array_flip($contactIds)) < count($contactIds)) {
            throw PlaylistException::contactForShareShouldBeUnique();
        }
    }

    /**
     * @param int[] $contactIds
     *
     * @throws \Throwable|PlaylistException
     */
    private function validateContactsExist(array $contactIds): void
    {
        if (
            ! empty(
                ($nonexistentUsers = array_diff($contactIds, $this->contactRepository->exist($contactIds)))
            )
        ) {
            throw PlaylistException::contactsDontExist($nonexistentUsers);
        }
    }

    /**
     * @param int[] $contactIds
     * @param int[] $contactIdsInUserContactGroups
     *
     * @throws PlaylistException
     */
    private function validateUserHasAccessToContacts(array $contactIds, array $contactIdsInUserContactGroups)
    {
        if ([] === $contactIds) {
            return;
        }
        $invalidContacts = [];
        foreach ($contactIds as $contactId) {
            if (! in_array($contactId, $contactIdsInUserContactGroups)) {
                $invalidContacts[] = $contactId;
            }
        }
        if ([] !== $invalidContacts) {
            throw PlaylistException::contactsAreNotInTheUserContactGroup($invalidContacts);
        }
    }

    /**
     * @param int[] $contactGroupIds
     *
     * @throws PlaylistException
     */
    private function validateUniqueContactGroups(array $contactGroupIds): void
    {
        if(count(array_flip($contactGroupIds)) < count($contactGroupIds)) {
            throw PlaylistException::contactGroupForShareShouldBeUnique();
        }
    }

    /**
     * @param int[] $contactGroupIds
     *
     * @throws \Throwable|PlaylistException
     */
    private function validateContactGroupsExists(array $contactGroupIds): void
    {
        if (
            ! empty(
                ($nonexistentContactGroups = array_diff(
                    $contactGroupIds, $this->contactGroupRepository->exist($contactGroupIds)
                ))
            )
        ) {
            throw PlaylistException::contactGroupsDontExist($nonexistentContactGroups);
        }
    }

    /**
     *
     * @param array $contactGroupIds
     * @param array $userContactGroupIds
     *
     * @throws PlaylistException
     */
    private function validateUserHasAccessToContactGroups(array $contactGroupIds, array $userContactGroupIds): void
    {
        if ([] === $contactGroupIds) {
            return;
        }
        $invalidContactGroups = [];
        foreach ($contactGroupIds as $contactGroupId) {
            if (! in_array($contactGroupId, $userContactGroupIds)) {
                $invalidContactGroups[] = $contactGroupId;
            }
        }
        if ([] !== $invalidContactGroups) {
            throw PlaylistException::userIsNotInContactGroups($invalidContactGroups);
        }
    }

    /**
     * @param int $playlistId
     *
     * @throws \Throwable|PlaylistException
     */
    private function validatePlaylistExists(int $playlistId): void
    {
        if (! $this->playlistRepository->exists($playlistId)) {
            throw PlaylistException::playlistDoesNotExists($playlistId);
        }
    }

    /**
     * @param int $playlistId
     * @param ContactInterface $user
     *
     * @throws \Throwable|PlaylistException
     */
    private function validateUserCanEditPlaylist(int $playlistId, ContactInterface $user): void
    {
        if(! $this->shareRepository->existsAsEditor($playlistId, $user)) {
            throw PlaylistException::playlistNotSharedAsEditor($playlistId);
        }
    }
}