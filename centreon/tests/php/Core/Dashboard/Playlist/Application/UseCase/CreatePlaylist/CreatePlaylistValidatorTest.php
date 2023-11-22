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

use Centreon\Domain\Contact\Contact;
use Core\Dashboard\Application\Repository\ReadDashboardRepositoryInterface;
use Core\Dashboard\Playlist\Application\Exception\PlaylistException;
use Core\Dashboard\Playlist\Application\Repository\ReadPlaylistRepositoryInterface;
use Core\Dashboard\Playlist\Application\UseCase\CreatePlaylist\CreatePlaylistValidator;

beforeEach(function () {
    $this->dashboardRepository = $this->createMock(ReadDashboardRepositoryInterface::class);
    $this->playlistRepository = $this->createMock(ReadPlaylistRepositoryInterface::class);
    $this->validator = new CreatePlaylistValidator($this->dashboardRepository, $this->playlistRepository);
});

it('should throw an exception when a dashboard does not exists', function () {

    $this->dashboardRepository
        ->expects($this->once())
        ->method('existsOne')
        ->willReturn(false);

    $this->validator->validateDashboardExists([1]);
})->throws(PlaylistException::dashboardsDoesNotExists([1])->getMessage());

it('should throw an exception when a playlist already exists with the same name', function () {
    $this->playlistRepository
        ->expects($this->once())
        ->method('existsByName')
        ->willReturn(true);

    $this->validator->validatePlaylistNameIsUnique('My Playlist');
})->throws(PlaylistException::playlistAlreadyExists('My Playlist')->getMessage());

it('should throw an exception when dashboards are not unique', function () {
    $this->validator->validateDashboardIsUnique([1,2,2,3,4,6]);
})->throws(PlaylistException::dashboardShouldBeUnique()->getMessage());

it('should throw an exception when a user does not have access to a dashboard in the playlist', function () {
    $contact = (new Contact())->setId(1)->setAdmin(false)->setAlias('user-test');

    $this->dashboardRepository
        ->expects($this->once())
        ->method('existsOneByContact')
        ->willReturn(false);

    $this->validator->validateUserHasAccessToDashboards([1], $contact);
})->throws(PlaylistException::dashboardNotShared(1)->getMessage());
