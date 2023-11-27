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

use Core\Dashboard\Playlist\Domain\Model\Playlist;

interface ReadPlaylistRepositoryInterface
{
    /**
     * Find a playlist.
     *
     * @param int $playlistId
     *
     * @return Playlist|null
     */
    public function find(int $playlistId): ?Playlist;

    /**
     * Check if a playlist exists with given name.
     *
     * @param string $name
     *
     * @return bool
     */
    public function existsByName(string $name): bool;

    /**
     * Check if a playlist exists with given id.
     *
     * @param int $playlistId
     *
     * @return bool
     */
    public function exists(int $playlistId): bool;
}
