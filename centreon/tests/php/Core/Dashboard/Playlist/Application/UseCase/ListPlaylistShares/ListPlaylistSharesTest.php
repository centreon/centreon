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

namespace Tests\Core\Dashboard\Playlist\Application\UseCase\ListPlaylistShares;

use Centreon\Domain\Contact\Contact;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Contact\Application\Repository\ReadContactGroupRepositoryInterface;
use Core\Dashboard\Domain\Model\DashboardRights;
use Core\Dashboard\Playlist\Application\Exception\PlaylistException;
use Core\Dashboard\Playlist\Application\Repository\ReadPlaylistRepositoryInterface;
use Core\Dashboard\Playlist\Application\Repository\ReadPlaylistShareRepositoryInterface;
use Core\Dashboard\Playlist\Application\UseCase\ListPlaylistShares\ListPlaylistShares;
use Core\Dashboard\Playlist\Application\UseCase\ListPlaylistShares\ListPlaylistSharesResponse;
use Core\Dashboard\Playlist\Domain\Model\PlaylistContactGroupShare;
use Core\Dashboard\Playlist\Domain\Model\PlaylistContactShare;
use Core\Dashboard\Playlist\Domain\Model\PlaylistShare;

beforeEach(function () {
    $this->rights = $this->createMock(DashboardRights::class);
    $this->adminUser = (new Contact())->setAdmin(true)->setId(1)->setAlias('admin');
    $this->nonAdminUser = (new Contact())->setAdmin(false)->setId(1)->setAlias('non-admin');
    $this->readPlaylistRepository = $this->createMock(ReadPlaylistRepositoryInterface::class);
    $this->readPlaylistShareRepository = $this->createMock(ReadPlaylistShareRepositoryInterface::class);
    $this->contactGroupRepository = $this->createMock(ReadContactGroupRepositoryInterface::class);
});

it('should present a Forbidden Response when the user has no rights to view playlist', function () {
    $playlistId = 1;
    $useCase = new ListPlaylistShares(
        $this->rights,
        $this->nonAdminUser,
        $this->readPlaylistRepository,
        $this->readPlaylistShareRepository,
        $this->contactGroupRepository,
    );

    $presenter = new ListPlaylistSharesPresenterStub();

    $this->rights
        ->expects($this->once())
        ->method('canAccess')
        ->willReturn(false);

    $useCase($playlistId, $presenter);

    expect($presenter->data)->toBeInstanceOf(ForbiddenResponse::class)
        ->and($presenter->data->getMessage())
        ->toBe(PlaylistException::accessNotAllowed()->getMessage());
});

it('should present a NotFound Response when the playlist does not exist', function () {
    $playlistId = 1;
    $useCase = new ListPlaylistShares(
        $this->rights,
        $this->nonAdminUser,
        $this->readPlaylistRepository,
        $this->readPlaylistShareRepository,
        $this->contactGroupRepository,
    );

    $presenter = new ListPlaylistSharesPresenterStub();

    $this->rights
        ->expects($this->once())
        ->method('canAccess')
        ->willReturn(true);

    $this->readPlaylistRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(false);

    $useCase($playlistId, $presenter);

    expect($presenter->data)->toBeInstanceOf(NotFoundResponse::class)
        ->and($presenter->data->getMessage())
        ->toBe((new NotFoundResponse('Playlist'))->getMessage());
});

it('should present an InvalidArgument Response if the playlist is not shared with the user', function () {
    $playlistId = 1;
    $useCase = new ListPlaylistShares(
        $this->rights,
        $this->nonAdminUser,
        $this->readPlaylistRepository,
        $this->readPlaylistShareRepository,
        $this->contactGroupRepository,
    );

    $presenter = new ListPlaylistSharesPresenterStub();

    $this->rights
        ->expects($this->once())
        ->method('canAccess')
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
        ->method('exists')
        ->willReturn(false);

    $useCase($playlistId, $presenter);

    expect($presenter->data)->toBeInstanceOf(InvalidArgumentResponse::class)
        ->and($presenter->data->getMessage())
        ->toBe(PlaylistException::playlistNotShared($playlistId)->getMessage());
});


it('should present an ErrorResponse when an error occured whie listing shares', function () {
    $playlistId = 1;
    $useCase = new ListPlaylistShares(
        $this->rights,
        $this->nonAdminUser,
        $this->readPlaylistRepository,
        $this->readPlaylistShareRepository,
        $this->contactGroupRepository,
    );

    $presenter = new ListPlaylistSharesPresenterStub();

    $this->rights
        ->expects($this->once())
        ->method('canAccess')
        ->willReturn(true);

    $this->readPlaylistRepository
        ->expects($this->once())
        ->method('exists')
        ->willThrowException(new \Exception());

    $useCase($playlistId, $presenter);

    expect($presenter->data)->toBeInstanceOf(ErrorResponse::class)
        ->and($presenter->data->getMessage())
        ->toBe(PlaylistException::errorWhileListingShares()->getMessage());
});

it('should list playlist shares when no error occurs', function () {
    $playlistId = 1;

    $contactShares = [
        new PlaylistContactShare($playlistId, 1, 'admin', 'editor'),
        new PlaylistContactShare($playlistId, 2, 'non-admin', 'viewer'),
    ];

    $contactGroupShares = [
        new PlaylistContactGroupShare($playlistId, 5, 'CG1', 'editor'),
        new PlaylistContactGroupShare($playlistId, 6, 'CG2', 'viewer'),
    ];

    $playlistShare = new PlaylistShare($playlistId, $contactShares, $contactGroupShares);

    $useCase = new ListPlaylistShares(
        $this->rights,
        $this->nonAdminUser,
        $this->readPlaylistRepository,
        $this->readPlaylistShareRepository,
        $this->contactGroupRepository,
    );

    $presenter = new ListPlaylistSharesPresenterStub();

    $this->rights
        ->expects($this->once())
        ->method('canAccess')
        ->willReturn(true);

    $this->readPlaylistRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(true);

    $this->rights
        ->expects($this->any())
        ->method('hasAdminRole')
        ->willReturn(true);

    $this->readPlaylistShareRepository
        ->expects($this->once())
        ->method('findByPlaylistId')
        ->with($playlistId)
        ->willReturn($playlistShare);

    $useCase($playlistId, $presenter);

    $response = $presenter->data;
    expect($response)->toBeInstanceOf(ListPlaylistSharesResponse::class)
        ->and($response->contacts[0]['id'])->toBe(1)
        ->and($response->contacts[0]['name'])->toBe('admin')
        ->and($response->contacts[0]['role'])->toBe('editor')
        ->and($response->contacts[1]['id'])->toBe(2)
        ->and($response->contacts[1]['name'])->toBe('non-admin')
        ->and($response->contacts[1]['role'])->toBe('viewer')
        ->and($response->contactGroups[0]['id'])->toBe(5)
        ->and($response->contactGroups[0]['name'])->toBe('CG1')
        ->and($response->contactGroups[0]['role'])->toBe('editor')
        ->and($response->contactGroups[1]['id'])->toBe(6)
        ->and($response->contactGroups[1]['name'])->toBe('CG2')
        ->and($response->contactGroups[1]['role'])->toBe('viewer');
});
