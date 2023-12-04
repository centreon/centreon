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

namespace Core\Dashboard\Playlist\Domain\Model;

use Centreon\Domain\Common\Assertion\Assertion;

class PlaylistContactGroupShare
{
    /**
     * @param int $playlistId
     * @param int $contactGroupId
     * @param string $contactGroupName
     * @param string $role
     *
     * @throws \Assert\AssertionFailedException
     */
    public function __construct(
        private readonly int $playlistId,
        private readonly int $contactGroupId,
        private readonly string $contactGroupName,
        private readonly string $role
    ) {
        Assertion::positiveInt($playlistId, 'PlaylistContactGroupShare::playlistId');
        Assertion::positiveInt($contactGroupId, 'PlaylistContactGroupShare::contactGroupId');
        Assertion::notEmptyString($contactGroupName, 'PlaylistContactGroupShare::contactGroupName');
        Assertion::inArray($role, PlaylistShare::PLAYLIST_ROLES, 'PlaylistContactGroupShare::role');
    }

    public function getPlaylistId(): int
    {
        return $this->playlistId;
    }

    public function getContactGroupId(): int
    {
        return $this->contactGroupId;
    }

    public function getContactGroupName(): string
    {
        return $this->contactGroupName;
    }

    public function getRole(): string
    {
        return $this->role;
    }
}