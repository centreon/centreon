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

interface WritePlaylistShareRepositoryInterface
{

    /**
     * Delete the shared relation between contact, contactgroups and a given playlist.
     *
     * @param int $playlistId
     *
     * @throws \Throwable
     */
    public function deletePlaylistShares(int $playlistId): void;

    /**
     * Add shared relation between contacts and a playlist.
     *
     * @param int $playlistId
     * @param array{}|array<array{id: int, role: string}> $contacts
     *
     * @throws \Throwable
     */
    public function addPlaylistContactShares(int $playlistId, array $contacts): void;

    /**
     * Add shared relation between contactgroups and a playlist.
     *
     * @param int $playlistId
     * @param array{}|array<array{id: int, role: string}> $contactGroups
     *
     * @throws \Throwable
     */
    public function addPlaylistContactGroupShares(int $playlistId, array $contactGroups): void;

    /**
     * Delete a Playlist Shares of the given contact ids.
     *
     * @param int $playlistId
     * @param int[] $contactIds
     *
     * @throws \Throwable
     */
    public function deletePlaylistSharesByContactIds(int $playlistId, array $contactIds): void;

    /**
     * Delete a playlist Shares of the given contactgroup ids.
     *
     * @param int $playlistId
     * @param int[] $contactGroupIds
     *
     * @throws \Throwable
     */
    public function deletePlaylistSharesByContactGroupIds(int $playlistId, array $contactGroupIds): void;
}