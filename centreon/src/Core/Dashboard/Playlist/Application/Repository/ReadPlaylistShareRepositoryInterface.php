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

namespace Core\Dashboard\Playlist\Application\Repository;

use Assert\AssertionFailedException;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Dashboard\Playlist\Domain\Model\PlaylistShare;

interface ReadPlaylistShareRepositoryInterface
{
    /**
     * Check if a user is editor on a playlist.
     *
     * @param int $playlistId
     * @param ContactInterface $contact
     *
     * @throws \Throwable
     *
     * @return bool
     */
    public function existsAsEditor(int $playlistId, ContactInterface $contact): bool;

    /**
     * Check if a user has a given playlist shared with him.
     *
     * @param int $playlistId
     * @param ContactInterface $contact
     *
     * @throws \Throwable
     *
     * @return bool
     */
    public function exists(int $playlistId, ContactInterface $contact): bool;

    /**
     * Find all the shares contact and contactgroups of a playlist.
     *
     * @param int $playlistId
     *
     * @throws \Throwable|AssertionFailedException
     *
     * @return PlaylistShare
     */
    public function findByPlaylistId(int $playlistId): PlaylistShare;

    /**
     * Find contact and contactgroups shares of a playlist, based on given contactgroup ids.
     *
     * @param int $playlistId
     * @param int[] $contactGroupIds
     *
     * @throws \Throwable|AssertionFailedException
     *
     * @return PlaylistShare
     */
    public function findByPlaylistIdAndContactGroupIds(int $playlistId, array $contactGroupIds): PlaylistShare;
}
