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
use Core\Dashboard\Playlist\Application\Exception\PlaylistException;
use Core\Dashboard\Playlist\Application\Repository\ReadPlaylistRepositoryInterface;

class SharePlaylistValidator
{
    public function __construct(private readonly ReadPlaylistRepositoryInterface $playlistRepository)
    {
    }

    /**
     * @param array{}|array{id: int, role: string} $contacts
     *
     * @throws PlaylistException
     */
    public function validateUniqueContacts(array $contacts): void
    {
        $contactIds = array_map(fn (array $contact): int => $contact['id'], $contacts);
        if(count(array_flip($contactIds)) < count($contactIds)) {
            throw PlaylistException::contactForShareShouldBeUnique();
        }
    }

    public function validateUniqueContactGroups(array $contactGroups): void
    {
        $contactGroupIds = array_map(fn (array $contactGroup): int => $contactGroup['id'], $contactGroups);
        if(count(array_flip($contactGroupIds)) < count($contactGroupIds)) {
            throw PlaylistException::contactGroupForShareShouldBeUnique();
        }
    }

    public function validatePlaylistExists(int $playlistId): void
    {
        if (! $this->playlistRepository->exists($playlistId)) {
            PlaylistException::playlistDoesNotExists($playlistId);
        }
    }

    public function validateUserHasAccessToPlaylist(int $playlistId, ContactInterface $user): void
    {
        if(! $this->playlistRepository->existsByUser($playlistId, $user)) {
            PlaylistException::playlistNotShared($playlistId);
        }
    }
}