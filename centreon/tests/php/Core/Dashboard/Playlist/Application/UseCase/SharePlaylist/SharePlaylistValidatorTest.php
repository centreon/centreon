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

namespace Tests\Core\Dashboard\Playlist\Application\UseCase\SharePlaylist;

use Centreon\Domain\Contact\Contact;
use Core\Contact\Application\Repository\ReadContactGroupRepositoryInterface;
use Core\Contact\Application\Repository\ReadContactRepositoryInterface;
use Core\Dashboard\Playlist\Application\Exception\PlaylistException;
use Core\Dashboard\Playlist\Application\Repository\ReadPlaylistRepositoryInterface;
use Core\Dashboard\Playlist\Application\Repository\ReadPlaylistShareRepositoryInterface;
use Core\Dashboard\Playlist\Application\UseCase\SharePlaylist\SharePlaylistValidator;

beforeEach(function () {
    $this->readPlaylistRepository = $this->createMock(ReadPlaylistRepositoryInterface::class);
    $this->readPlaylistShareRepository = $this->createMock(ReadPlaylistShareRepositoryInterface::class);
    $this->contactGroupRepository = $this->createMock(ReadContactGroupRepositoryInterface::class);
    $this->contactRepository = $this->createMock(ReadContactRepositoryInterface::class);
    $this->validator = new SharePlaylistValidator(
        $this->readPlaylistRepository,
        $this->readPlaylistShareRepository,
        $this->contactGroupRepository,
        $this->contactRepository
    );
});

it('should throw a PlaylistException when a contact does not exist', function () {
    $contactIds = [1,2];
    $hasAdminRole = true;

    $this->contactRepository
        ->expects($this->once())
        ->method('exist')
        ->willReturn([2]);

    $this->validator->validateContacts($contactIds, $hasAdminRole);
})->throws(PlaylistException::contactsDontExist([1])->getMessage());

it('should throw a PlaylistException when a contact is duplicated', function () {
    $contactIds = [1,1];
    $hasAdminRole = true;

    $this->contactRepository
        ->expects($this->once())
        ->method('exist')
        ->willReturn([1]);
    $this->validator->validateContacts($contactIds, $hasAdminRole);
})->throws(PlaylistException::contactForShareShouldBeUnique()->getMessage());

it('should throws a PlaylistException when a contact is not in the user contactgroups', function () {
    $contactIds = [1,2];
    $hasAdminRole = false;
    $contactIdsInUserContactGroups = [1,3,4];

    $this->contactRepository
        ->expects($this->once())
        ->method('exist')
        ->willReturn([1,2]);

    $this->validator->validateContacts($contactIds, $hasAdminRole, $contactIdsInUserContactGroups);
})->throws(PlaylistException::contactsAreNotInTheUserContactGroup([2])->getMessage());

it('should throw a PlaylistException when a contactgroup does not exist', function () {
    $contactGroupIds = [1,2];
    $hasAdminRole = true;

    $this->contactGroupRepository
        ->expects($this->once())
        ->method('exist')
        ->willReturn([2]);

    $this->validator->validateContactGroups($contactGroupIds, $hasAdminRole);
})->throws(PlaylistException::contactGroupsDontExist([1])->getMessage());

it('should throw a PlaylistException when a contactgroup is duplicated', function () {
    $contactGroupIds = [1,1];
    $hasAdminRole = true;

    $this->contactGroupRepository
        ->expects($this->once())
        ->method('exist')
        ->willReturn([1]);

    $this->validator->validateContactGroups($contactGroupIds, $hasAdminRole);
})->throws(PlaylistException::contactGroupForShareShouldBeUnique()->getMessage());

it('should throws a PlaylistException when the user is not in a request contactgroups', function () {
    $contactIds = [1,2];
    $hasAdminRole = false;
    $userContactGroups = [1,3,4];

    $this->contactGroupRepository
        ->expects($this->once())
        ->method('exist')
        ->willReturn([1,2]);

    $this->validator->validateContactGroups($contactIds, $hasAdminRole, $userContactGroups);
})->throws(PlaylistException::userIsNotInContactGroups([2])->getMessage());

it('should throws a PlaylistException when the Playlist does not exists', function () {
    $playlistId = 1;
    $user = (new Contact())->setId(1)->setAlias('admin');

    $this->readPlaylistRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(false);

    $this->validator->validatePlaylist($playlistId, $user, true);
})->throws(PlaylistException::playlistDoesNotExists(1)->getMessage());

it('should throws a PlaylistException when the user has no writing rights on the playlist', function () {
    $playlistId = 1;
    $user = (new Contact())->setId(1)->setAlias('non-admin');

    $this->readPlaylistRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(true);

    $this->readPlaylistShareRepository
        ->expects($this->once())
        ->method('existsAsEditor')
        ->willReturn(false);

    $this->validator->validatePlaylist($playlistId, $user, false);

})->throws(PlaylistException::playlistNotSharedAsEditor(1)->getMessage());
