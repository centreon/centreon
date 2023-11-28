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

namespace Tests\Core\Dashboard\Playlist\Application\UseCase\CreatePlaylist;

use Centreon\Domain\Common\Assertion\AssertionException;
use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\Application\Common\UseCase\ConflictResponse;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Dashboard\Application\Repository\ReadDashboardRepositoryInterface;
use Core\Dashboard\Domain\Model\DashboardRights;
use Core\Dashboard\Playlist\Application\Exception\PlaylistException;
use Core\Dashboard\Playlist\Application\Repository\ReadPlaylistRepositoryInterface;
use Core\Dashboard\Playlist\Application\Repository\WritePlaylistRepositoryInterface;
use Core\Dashboard\Playlist\Application\UseCase\CreatePlaylist\CreatePlaylist;
use Core\Dashboard\Playlist\Application\UseCase\CreatePlaylist\CreatePlaylistRequest;
use Core\Dashboard\Playlist\Application\UseCase\CreatePlaylist\CreatePlaylistResponse;
use Core\Dashboard\Playlist\Application\UseCase\CreatePlaylist\CreatePlaylistValidator;
use Core\Dashboard\Playlist\Domain\Model\DashboardOrder;
use Core\Dashboard\Playlist\Domain\Model\NewPlaylist;
use Core\Dashboard\Playlist\Domain\Model\Playlist;
use Core\Dashboard\Playlist\Domain\Model\PlaylistAuthor;

beforeEach(function() {
    $this->adminUser = (new Contact())->setAdmin(true)->setId(1)->setAlias('admin');
    $this->nonAdminUser = (new Contact())->setAdmin(false)->setId(1)->setAlias('non-admin');
    $this->rights = $this->createMock(DashboardRights::class);
    $this->writePlaylistRepository = $this->createMock(WritePlaylistRepositoryInterface::class);
    $this->readPlaylistRepository = $this->createMock(ReadPlaylistRepositoryInterface::class);
    $this->readDashboardRepository = $this->createMock(ReadDashboardRepositoryInterface::class);
    $this->validator = new CreatePlaylistValidator(
        $this->readDashboardRepository,
        $this->readPlaylistRepository
    );
    $this->dataStorageEngine = $this->createMock(DataStorageEngineInterface::class);
});

it('should present a Forbidden Response when the user has no rights to create playlist', function () {
    $useCase = new CreatePlaylist(
        $this->nonAdminUser,
        $this->validator,
        $this->writePlaylistRepository,
        $this->readPlaylistRepository,
        $this->dataStorageEngine,
        $this->rights
    );
    $presenter = new CreatePlaylistPresenterStub();
    $request= new CreatePlaylistRequest();

    $this->rights
        ->expects($this->once())
        ->method('canCreate')
        ->willReturn(false);

    $useCase($presenter, $request);


    expect($presenter->data)->toBeInstanceOf(ForbiddenResponse::class)
        ->and($presenter->data->getMessage())
        ->toBe(PlaylistException::accessNotAllowed()->getMessage());
});

it('should present a ConflictResponse When a playlist with same name already exists', function() {
    $existingPlaylistName = 'Playlist Name';
    $useCase = new CreatePlaylist(
        $this->adminUser,
        $this->validator,
        $this->writePlaylistRepository,
        $this->readPlaylistRepository,
        $this->dataStorageEngine,
        $this->rights
    );
    $presenter = new CreatePlaylistPresenterStub();
    $request= new CreatePlaylistRequest();
    $request->name = $existingPlaylistName;

    $this->rights
        ->expects($this->once())
        ->method('canCreate')
        ->willReturn(true);

    $this->readPlaylistRepository
        ->expects($this->once())
        ->method('existsByName')
        ->with($existingPlaylistName)
        ->willReturn(true);

    $useCase($presenter, $request);

    expect($presenter->data)->toBeInstanceOf(ConflictResponse::class)
        ->and($presenter->data->getMessage())
        ->toBe(PlaylistException::playlistAlreadyExists($existingPlaylistName)->getMessage());
});

it('should present a InvalidArgumentResponse when a dahsboard in the playlist does not exists', function () {
    $unexistentDashboardId = 50;
    $useCase = new CreatePlaylist(
        $this->adminUser,
        $this->validator,
        $this->writePlaylistRepository,
        $this->readPlaylistRepository,
        $this->dataStorageEngine,
        $this->rights
    );
    $presenter = new CreatePlaylistPresenterStub();
    $request= new CreatePlaylistRequest();
    $request->name = 'playlist';
    $request->dashboards = [['id' => $unexistentDashboardId, 'order' => 1]];

    $this->rights
        ->expects($this->once())
        ->method('canCreate')
        ->willReturn(true);

    $this->readDashboardRepository
        ->expects($this->once())
        ->method('existsOne')
        ->willReturn(false);

    $useCase($presenter, $request);

    expect($presenter->data)->toBeInstanceOf(InvalidArgumentResponse::class)
        ->and($presenter->data->getMessage())
        ->toBe(PlaylistException::dashboardsDoesNotExists([$unexistentDashboardId])->getMessage());
});

it('should present an InvalidArgumentResponse when a dashboard is more than one time into the request', function() {
    $useCase = new CreatePlaylist(
        $this->adminUser,
        $this->validator,
        $this->writePlaylistRepository,
        $this->readPlaylistRepository,
        $this->dataStorageEngine,
        $this->rights
    );
    $presenter = new CreatePlaylistPresenterStub();
    $request= new CreatePlaylistRequest();
    $request->name = 'playlist';
    $request->dashboards = [['id' => 1, 'order' => 1],['id' => 1, 'order' => 2]];

    $this->rights
        ->expects($this->once())
        ->method('canCreate')
        ->willReturn(true);

    $this->readDashboardRepository
        ->expects($this->any())
        ->method('existsOne')
        ->willReturnOnConsecutiveCalls(true,true);

    $useCase($presenter, $request);

    expect($presenter->data)->toBeInstanceOf(InvalidArgumentResponse::class)
        ->and($presenter->data->getMessage())
        ->toBe(PlaylistException::dashboardShouldBeUnique()->getMessage());
});

it(
    'should present an InvalidArgumentRepsonse when a non admin user try to '
        . 'add a dashboard not shared with him in the playlist',
    function () {
        $useCase = new CreatePlaylist(
            $this->nonAdminUser,
            $this->validator,
            $this->writePlaylistRepository,
            $this->readPlaylistRepository,
            $this->dataStorageEngine,
            $this->rights
        );
        $presenter = new CreatePlaylistPresenterStub();
        $request= new CreatePlaylistRequest();
        $request->name = 'playlist';
        $request->dashboards = [['id' => 1, 'order' => 1]];
        $request->rotationTime = 10;

        $this->rights
            ->expects($this->once())
            ->method('canCreate')
            ->willReturn(true);

        $this->readDashboardRepository
            ->expects($this->any())
            ->method('existsOne')
            ->willReturnOnConsecutiveCalls(true);

        $this->rights
            ->expects($this->once())
            ->method('hasAdminRole')
            ->willReturn(false);

        $this->readDashboardRepository
            ->expects($this->once())
            ->method('existsOneByContact')
            ->willReturn(false);

        $useCase($presenter, $request);

        expect($presenter->data)->toBeInstanceOf(InvalidArgumentResponse::class)
            ->and($presenter->data->getMessage())
            ->toBe(PlaylistException::dashboardNotShared(1)->getMessage());
    }
);

it('should present an InvalidArgumentResponse when the playlist has invalid values', function () {
    $useCase = new CreatePlaylist(
        $this->adminUser,
        $this->validator,
        $this->writePlaylistRepository,
        $this->readPlaylistRepository,
        $this->dataStorageEngine,
        $this->rights
    );
    $presenter = new CreatePlaylistPresenterStub();
    $request= new CreatePlaylistRequest();
    $request->name = 'playlist';
    $request->dashboards = [['id' => 1, 'order' => 1]];
    $request->rotationTime = 1;

    $this->rights
        ->expects($this->once())
        ->method('canCreate')
        ->willReturn(true);

    $this->readDashboardRepository
        ->expects($this->any())
        ->method('existsOne')
        ->willReturnOnConsecutiveCalls(true);

    $this->rights
        ->expects($this->once())
        ->method('hasAdminRole')
        ->willReturn(true);

    $useCase($presenter, $request);

    expect($presenter->data)->toBeInstanceOf(InvalidArgumentResponse::class)
        ->and($presenter->data->getMessage())
        ->toBe(AssertionException::range(
            1,
            NewPlaylist::MINIMUM_ROTATION_TIME,
            NewPlaylist::MAXIMUM_ROTATION_TIME,
            'NewPlaylist::rotationTime')->getMessage()
        );
});

it('should present an ErrorResponse when an error occured while writing playlist in data storage', function() {
    $useCase = new CreatePlaylist(
        $this->adminUser,
        $this->validator,
        $this->writePlaylistRepository,
        $this->readPlaylistRepository,
        $this->dataStorageEngine,
        $this->rights
    );
    $presenter = new CreatePlaylistPresenterStub();
    $request= new CreatePlaylistRequest();
    $request->name = 'playlist';
    $request->dashboards = [['id' => 1, 'order' => 1]];
    $request->rotationTime = 10;

    $this->rights
        ->expects($this->once())
        ->method('canCreate')
        ->willReturn(true);

    $this->readDashboardRepository
        ->expects($this->any())
        ->method('existsOne')
        ->willReturnOnConsecutiveCalls(true);

    $this->rights
        ->expects($this->once())
        ->method('hasAdminRole')
        ->willReturn(true);

    $this->writePlaylistRepository
        ->expects($this->once())
        ->method('add')
        ->willThrowException(new \Exception());

    $useCase($presenter, $request);

    expect($presenter->data)->toBeInstanceOf(ErrorResponse::class)
        ->and($presenter->data->getMessage())
        ->toBe(PlaylistException::errorWhileCreating()->getMessage());
});


it('should present a CreatePlaylistResponse when a playlist is correctly created', function () {
    $playlistName = 'playlist';
    $rotationTime = 15;
    $isPublic = false;
    $description = 'A playlist description';
    $useCase = new CreatePlaylist(
        $this->adminUser,
        $this->validator,
        $this->writePlaylistRepository,
        $this->readPlaylistRepository,
        $this->dataStorageEngine,
        $this->rights
    );
    $presenter = new CreatePlaylistPresenterStub();
    $request= new CreatePlaylistRequest();
    $request->name = $playlistName;
    $request->dashboards = [['id' => 1, 'order' => 1]];
    $request->rotationTime = $rotationTime;
    $request->description = $description;
    $request->isPublic = $isPublic;

    $this->rights
        ->expects($this->once())
        ->method('canCreate')
        ->willReturn(true);

    $this->readDashboardRepository
        ->expects($this->any())
        ->method('existsOne')
        ->willReturnOnConsecutiveCalls(true);

    $this->rights
        ->expects($this->once())
        ->method('hasAdminRole')
        ->willReturn(true);

    $this->readPlaylistRepository
        ->expects($this->once())
        ->method('find')
        ->willReturn(
            (new Playlist(1, $playlistName, $rotationTime, $isPublic))
                ->setDescription($description)
                ->setDashboardsOrder([new DashboardOrder(1,1)])
                ->setAuthor(new PlaylistAuthor(1, 'admin'))
        );

    $useCase($presenter, $request);
    $response = $presenter->data;
    expect($response)->toBeInstanceOf(CreatePlaylistResponse::class)
        ->and($response->id)->toBe(1)
        ->and($response->name)->toBe($playlistName)
        ->and($response->rotationTime)->toBe($rotationTime)
        ->and($response->description)->toBe($description)
        ->and($response->author)->toBe(['id' => 1, 'name' => 'admin'])
        ->and($response->dashboards)->toBe([['id' => 1, 'order' => 1]])
        ->and($response->isPublic)->toBe($isPublic);
});
