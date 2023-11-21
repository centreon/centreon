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

namespace Core\Dashboard\Playlist\Application\UseCase\CreatePlaylist;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Dashboard\Application\Repository\ReadDashboardRepositoryInterface;
use Core\Dashboard\Playlist\Application\Exception\PlaylistException;
use Core\Dashboard\Playlist\Application\Repository\ReadPlaylistRepositoryInterface;

final class CreatePlaylistValidator
{
    /**
     * @param ReadDashboardRepositoryInterface $dashboardRepository
     * @param ReadPlaylistRepositoryInterface $playlistRepository
     */
    public function __construct(
        private ReadDashboardRepositoryInterface $dashboardRepository,
        private ReadPlaylistRepositoryInterface $playlistRepository
    ) {
    }

    /**
     * @param int[] $dashboardIds
     *
     * @throws PlaylistException|\Throwable
     */
    public function validateDashboardExists(array $dashboardIds): void
    {
        $nonexistentDashboards = [];
        foreach ($dashboardIds as $dashboardId) {
            if (! $this->dashboardRepository->existsOne($dashboardId)) {
                $nonexistentDashboards[] = $dashboardId;
            }
        }
        if ([] !== $nonexistentDashboards) {
            throw PlaylistException::dashboardsDoesNotExists($nonexistentDashboards);
        }
    }

    /**
     * @param string $playlistName
     *
     * @throws PlaylistException|\Throwable
     */
    public function validatePlaylistNameIsUnique(string $playlistName): void
    {
        if ($this->playlistRepository->existsByName($playlistName) === true) {
            throw PlaylistException::playlistAlreadyExists($playlistName);
        }
    }

    /**
     * @param int[] $dashboardIds
     *
     * @throws PlaylistException
     */
    public function validateDashboardIsUnique(array $dashboardIds): void
    {
        if (count(array_flip($dashboardIds)) < count($dashboardIds)) {
            throw PlaylistException::dashboardShouldBeUnique();
        }
    }

    /**
     * @param int[] $dashboardIds
     * @param ContactInterface $contact
     *
     * @throws PlaylistException|\Throwable
     */
    public function validateUserHasAccessToDashboards(array $dashboardIds, ContactInterface $contact): void
    {
        foreach ($dashboardIds as $dashboardId) {
            if (! $this->dashboardRepository->existsOneByContact($dashboardId, $contact)) {
                throw PlaylistException::dashboardNotShared($dashboardId);
            }
        }
    }
}
