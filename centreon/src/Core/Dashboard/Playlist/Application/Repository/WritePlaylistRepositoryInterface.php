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

use Core\Dashboard\Playlist\Domain\Model\DashboardOrder;
use Core\Dashboard\Playlist\Domain\Model\NewPlaylist;
use Core\Dashboard\Playlist\Domain\Model\PlaylistAuthor;

interface WritePlaylistRepositoryInterface
{
    /**
     * Add a playlist and return its id.
     *
     * @param NewPlaylist $playlist
     *
     * @throws \Throwable
     *
     * @return int
     */
    public function add(NewPlaylist $playlist): int;

    /**
     * Add Dashboards to a playlist.
     *
     * @param int $playlistId
     * @param DashboardOrder[] $dashboardsOrder
     *
     * @throws \Throwable
     */
    public function addDashboardsToPlaylist(int $playlistId, array $dashboardsOrder): void;

    /**
     * Add Author to playlist's shared users.
     *
     * @param int $playlistId
     * @param PlaylistAuthor $author
     *
     * @throws \Throwable
     */
    public function addAuthorToPlaylistSharedUser(int $playlistId, PlaylistAuthor $author): void;
}
