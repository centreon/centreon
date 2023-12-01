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

namespace Core\Dashboard\Playlist\Application\Exception;

class PlaylistException extends \Exception
{
    public const CODE_CONFLICT = 1;
    public const CODE_NOT_FOUND = 2;

    /**
     * @param int[] $dashboardIds
     *
     * @return self
     */
    public static function dashboardsDoesNotExists(array $dashboardIds): self
    {
        return new self (sprintf(_('The following dashboards does not exists: [%s].'), implode(', ', $dashboardIds)));
    }

    /**
     * @param string $playlistName
     *
     * @return self
     */
    public static function playlistAlreadyExists(string $playlistName): self
    {
        return new self (sprintf(_('The following playlist already exists: %s.'), $playlistName), self::CODE_CONFLICT);
    }

    /**
     * @return self
     */
    public static function errorWhileRetrieving(): self
    {
        return new self(_('Error while retrieving a playlist.'));
    }

    /**
     * @return self
     */
    public static function errorWhileCreating(): self
    {
        return new self(_('Error while creating a playlist.'));
    }

    /**
     * @return self
     */
    public static function errorWhileDeleting(): self
    {
        return new self(_('Error while deleting a playlist'));
    }

    /**
     * @return self
     */
    public static function errorWhileUpdatingShares(): self
    {
        return new self(_('Error while updating playlist shares'));
    }

    /**
     * @return self
     */
    public static function dashboardShouldBeUnique(): self
    {
        return new self(_('You cannot add the same dashboard to a playlist several times.'));
    }

    /**
     * @return self
     */
    public static function accessNotAllowed(): self
    {
        return new self(_('Access not allowed'));
    }

    /**
     * @param int $dashboardId
     *
     * @return self
     */
    public static function dashboardNotShared(int $dashboardId): self
    {
        return new self(sprintf(_('The following dashboard is not shared with you: [%d].'), $dashboardId));
    }

    /**
     * @return self
     */
    public static function contactForShareShouldBeUnique(): self
    {
        return new self(_('You cannot share the same playlist to a contact several times'));
    }

    /**
     * @return self
     */
    public static function contactGroupForShareShouldBeUnique(): self
    {
        return new self(_('You cannot share the same playlist to a contact group several times'));
    }

    /**
     * @param int $playlistId
     *
     * @return self
     */
    public static function playlistDoesNotExists(int $playlistId): self
    {
        return new self(sprintf(_('The following playlist does not exist: [%d]'), $playlistId), self::CODE_NOT_FOUND);
    }

    /**
     * @param int $playlistId
     *
     * @return self
     */
    public static function playlistNotSharedAsEditor(int $playlistId): self
    {
        return new self(sprintf(_('You do not have editing rights on the following playlist: [%d].'), $playlistId));
    }

    /**
     * @param int[] $userIds
     *
     * @return self
     */
    public static function contactsDontExist(array $userIds): self
    {
        return new self(sprintf(_('The following contact does not exist: [%s]'), implode(', ', $userIds)));
    }

    /**
     * @param int[] $contactGroupIds
     *
     * @return self
     */
    public static function contactGroupsDontExist(array $contactGroupIds): self
    {
        return new self(sprintf(_('The following contact group does not exist: [%s]'), implode(', ', $contactGroupIds)));
    }

    /**
     * @param int[] $userIds
     *
     * @return self
     */
    public static function contactsAreNotInTheUserContactGroup(array $userIds): self
    {
        return new self(sprintf(
            _('The following contact is not in your contact groups: [%s]'),
            implode(', ', $userIds)
        ));
    }

    /**
     * @param int[] $contactGroupIds
     *
     * @return self
     */
    public static function userIsNotInContactGroups(array $contactGroupIds): self
    {
        return new self(sprintf(
            _('You are not in the following contact group: [%s]'),
            implode(', ', $contactGroupIds)
        ));
    }
}
