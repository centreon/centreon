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

namespace Tests\Core\Dashboard\Playlist\Domain\Model;

use Centreon\Domain\Common\Assertion\AssertionException;
use Core\Dashboard\Playlist\Domain\Model\PlaylistContactGroupShare;
use Core\Dashboard\Playlist\Domain\Model\PlaylistShare;

it('should throw an exception when the playlist id is negative', function () {
    new PlaylistContactGroupShare(-1, 1,'admin', PlaylistShare::PLAYLIST_EDITOR_ROLE);
})->throws(AssertionException::min(-1, 1,'PlaylistContactGroupShare::playlistId')->getMessage());

it('should throw an exception when the contact id is negative', function () {
    new PlaylistContactGroupShare(1, -1,'admin', PlaylistShare::PLAYLIST_EDITOR_ROLE);
})->throws(AssertionException::min(-1, 1,'PlaylistContactGroupShare::contactGroupId')->getMessage());

it('should throw an exception when the contact name is empty', function () {
    new PlaylistContactGroupShare(1, 1, '', PlaylistShare::PLAYLIST_EDITOR_ROLE);
})->throws(AssertionException::notEmptyString('PlaylistContactGroupShare::contactGroupName')->getMessage());

it('should throw an exception when the role is not an authorized value', function () {
    new PlaylistContactGroupShare(1, 1, 'admin', 'invalid_role');
})->throws(AssertionException::inArray(
    'invalid_role',
    PlaylistShare::PLAYLIST_ROLES,
    'PlaylistContactGroupShare::role'
)->getMessage());