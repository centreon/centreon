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

namespace Tests\Core\Dashboard\Playlist\Application\UseCase\DeletePlaylist;

use Centreon\Domain\Contact\Contact;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Dashboard\Domain\Model\DashboardRights;
use Core\Dashboard\Playlist\Application\Exception\PlaylistException;
use Core\Dashboard\Playlist\Application\Repository\ReadPlaylistRepositoryInterface;
use Core\Dashboard\Playlist\Application\Repository\ReadPlaylistShareRepositoryInterface;
use Core\Dashboard\Playlist\Application\Repository\WritePlaylistRepositoryInterface;
use Core\Dashboard\Playlist\Application\UseCase\DeletePlaylist\DeletePlaylist;

beforeEach(function () {
    $this->readPlaylistRepository = $this->createMock(ReadPlaylistRepositoryInterface::class);
    $this->writePlaylistRepository = $this->createMock(WritePlaylistRepositoryInterface::class);
    $this->readPlaylistShareRepository = $this->createMock(ReadPlaylistShareRepositoryInterface::class);
    $this->rights = $this->createMock(DashboardRights::class);
    $this->adminUser = (new Contact())->setAdmin(true)->setId(1);
    $this->nonAdminUser = (new Contact())->setAdmin(false)->setId(1);
});

it('should present a Forbidden Response when the user has no rights to shares playlist', function () {
    $useCase = new DeletePlaylist(
        $this->readPlaylistRepository,
        $this->writePlaylistRepository,
        $this->readPlaylistShareRepository,
        $this->rights,
        $this->nonAdminUser
    );

    $presenter = new DeletePlaylistPresenterStub();

    $this->rights
        ->expects($this->once())
        ->method('canCreate')
        ->willReturn(false);

    $useCase(1, $presenter);

    expect($presenter->data)->toBeInstanceOf(ForbiddenResponse::class)
        ->and($presenter->data->getMessage())
        ->toBe(PlaylistException::accessNotAllowed()->getMessage());
});

it('should present a not found response when the playlist does not exist', function () {
    $useCase = new DeletePlaylist(
        $this->readPlaylistRepository,
        $this->writePlaylistRepository,
        $this->readPlaylistShareRepository,
        $this->rights,
        $this->nonAdminUser
    );

    $presenter = new DeletePlaylistPresenterStub();

    $this->rights
        ->expects($this->once())
        ->method('canCreate')
        ->willReturn(true);

    $this->readPlaylistRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(false);

    $useCase(1, $presenter);

    expect($presenter->data)->toBeInstanceOf(NotFoundResponse::class)
        ->and($presenter->data->getMessage())
        ->toBe((new NotFoundResponse('Playlist'))->getMessage());
});


it('should present an InvalidArgument Response when the user is not allowed to edit the playlist', function () {
    $useCase = new DeletePlaylist(
        $this->readPlaylistRepository,
        $this->writePlaylistRepository,
        $this->readPlaylistShareRepository,
        $this->rights,
        $this->nonAdminUser
    );

    $presenter = new DeletePlaylistPresenterStub();

    $this->rights
        ->expects($this->once())
        ->method('canCreate')
        ->willReturn(true);

    $this->readPlaylistRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(true);

    $this->rights
        ->expects($this->once())
        ->method('hasAdminRole')
        ->willReturn(false);

    $this->readPlaylistShareRepository
        ->expects($this->once())
        ->method('existsAsEditor')
        ->willReturn(false);

    $useCase(1, $presenter);

    expect($presenter->data)->toBeInstanceOf(InvalidArgumentResponse::class)
        ->and($presenter->data->getMessage())
        ->toBe(PlaylistException::playlistNotSharedAsEditor(1)->getMessage());
});

it('should present an ErrorResponse when an error occurs', function () {
    $useCase = new DeletePlaylist(
        $this->readPlaylistRepository,
        $this->writePlaylistRepository,
        $this->readPlaylistShareRepository,
        $this->rights,
        $this->adminUser
    );

    $presenter = new DeletePlaylistPresenterStub();

    $this->rights
        ->expects($this->once())
        ->method('canCreate')
        ->willReturn(true);

    $this->readPlaylistRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(true);

    $this->rights
        ->expects($this->once())
        ->method('hasAdminRole')
        ->willReturn(true);

    $this->writePlaylistRepository
        ->expects($this->once())
        ->method('delete')
        ->willThrowException(new \Exception());

    $useCase(1, $presenter);

    expect($presenter->data)->toBeInstanceOf(ErrorResponse::class)
        ->and($presenter->data->getMessage())
        ->toBe(PlaylistException::errorWhileDeleting()->getMessage());
});

it('should present a NoContent Response when no error occurs', function () {
    $useCase = new DeletePlaylist(
        $this->readPlaylistRepository,
        $this->writePlaylistRepository,
        $this->readPlaylistShareRepository,
        $this->rights,
        $this->adminUser
    );

    $presenter = new DeletePlaylistPresenterStub();

    $this->rights
        ->expects($this->once())
        ->method('canCreate')
        ->willReturn(true);

    $this->readPlaylistRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(true);

    $this->rights
        ->expects($this->once())
        ->method('hasAdminRole')
        ->willReturn(true);

    $this->writePlaylistRepository
        ->expects($this->once())
        ->method('delete')
        ->with(1);

    $useCase(1, $presenter);

    expect($presenter->data)->toBeInstanceOf(NoContentResponse::class);
});
