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
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Contact\Application\Repository\ReadContactGroupRepositoryInterface;
use Core\Contact\Application\Repository\ReadContactRepositoryInterface;
use Core\Contact\Domain\Model\ContactGroup;
use Core\Dashboard\Domain\Model\DashboardRights;
use Core\Dashboard\Playlist\Application\Exception\PlaylistException;
use Core\Dashboard\Playlist\Application\Repository\ReadPlaylistRepositoryInterface;
use Core\Dashboard\Playlist\Application\Repository\ReadPlaylistShareRepositoryInterface;
use Core\Dashboard\Playlist\Application\Repository\WritePlaylistShareRepositoryInterface;
use Core\Dashboard\Playlist\Application\UseCase\SharePlaylist\SharePlaylist;
use Core\Dashboard\Playlist\Application\UseCase\SharePlaylist\SharePlaylistRequest;
use Core\Dashboard\Playlist\Application\UseCase\SharePlaylist\SharePlaylistValidator;

beforeEach(function() {
    $this->adminUser = (new Contact())->setAdmin(true)->setId(1)->setAlias('admin');
    $this->nonAdminUser = (new Contact())->setAdmin(false)->setId(1)->setAlias('non-admin');
    $this->rights = $this->createMock(DashboardRights::class);
    $this->readPlaylistRepository = $this->createMock(ReadPlaylistRepositoryInterface::class);
    $this->readPlaylistShareRepository = $this->createMock(ReadPlaylistShareRepositoryInterface::class);
    $this->writePlaylistShareRepository = $this->createMock(WritePlaylistShareRepositoryInterface::class);
    $this->contactGroupRepository = $this->createMock(ReadContactGroupRepositoryInterface::class);
    $this->contactRepository = $this->createMock(ReadContactRepositoryInterface::class);
    $this->validator = new SharePlaylistValidator(
        $this->readPlaylistRepository,
        $this->readPlaylistShareRepository,
        $this->contactGroupRepository,
        $this->contactRepository
    );
    $this->dataStorageEngine = $this->createMock(DataStorageEngineInterface::class);
});

it('should present a Forbidden Response when the user has no rights to shares playlist', function () {
    $useCase = new SharePlaylist(
        $this->rights,
        $this->validator,
        $this->nonAdminUser,
        $this->writePlaylistShareRepository,
        $this->dataStorageEngine,
        $this->contactGroupRepository,
        $this->contactRepository
    );
    $presenter = new SharePlaylistPresenterStub();
    $request = new SharePlaylistRequest();

    $this->rights
        ->expects($this->once())
        ->method('canCreate')
        ->willReturn(false);

    $useCase(1, $request, $presenter);


    expect($presenter->data)->toBeInstanceOf(ForbiddenResponse::class)
        ->and($presenter->data->getMessage())
        ->toBe(PlaylistException::accessNotAllowed()->getMessage());
});

it('should present a NotFound Response when the playlist does not exist', function () {
    $useCase = new SharePlaylist(
        $this->rights,
        $this->validator,
        $this->adminUser,
        $this->writePlaylistShareRepository,
        $this->dataStorageEngine,
        $this->contactGroupRepository,
        $this->contactRepository
    );
    $presenter = new SharePlaylistPresenterStub();
    $request = new SharePlaylistRequest();

    $this->rights
        ->expects($this->once())
        ->method('canCreate')
        ->willReturn(true);

    $this->readPlaylistRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(false);

    $useCase(2, $request, $presenter);

    expect($presenter->data)->toBeInstanceOf(NotFoundResponse::class)
        ->and($presenter->data->getMessage())
        ->toBe((new NotFoundResponse('Playlist'))->getMessage());
});

it('should present an InvalidArgument Response when a contact does not exists', function () {
    $useCase = new SharePlaylist(
        $this->rights,
        $this->validator,
        $this->adminUser,
        $this->writePlaylistShareRepository,
        $this->dataStorageEngine,
        $this->contactGroupRepository,
        $this->contactRepository
    );
    $presenter = new SharePlaylistPresenterStub();
    $request = new SharePlaylistRequest();
    $request->contacts = [['id' => 12, 'role' => 'editor'], ['id' => 13, 'role' => 'editor']];

    $this->rights
        ->expects($this->once())
        ->method('canCreate')
        ->willReturn(true);

    $this->rights
        ->expects($this->any())
        ->method('hasAdminRole')
        ->willReturn(true);

    $this->readPlaylistRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(true);

    $this->contactRepository
        ->expects($this->once())
        ->method('exist')
        ->with([12,13])
        ->willReturn([12]);

    $useCase(1, $request, $presenter);

    expect($presenter->data)->toBeInstanceOf(InvalidArgumentResponse::class)
        ->and($presenter->data->getMessage())
        ->toBe(PlaylistException::contactsDontExist([13])->getMessage());
});

it('should present an InvalidArgument Response when a contact is duplicate in the request', function () {
    $useCase = new SharePlaylist(
        $this->rights,
        $this->validator,
        $this->adminUser,
        $this->writePlaylistShareRepository,
        $this->dataStorageEngine,
        $this->contactGroupRepository,
        $this->contactRepository
    );
    $presenter = new SharePlaylistPresenterStub();
    $request = new SharePlaylistRequest();
    $request->contacts = [['id' => 12, 'role' => 'editor'],['id' => 12, 'role' => 'editor']];

    $this->rights
        ->expects($this->once())
        ->method('canCreate')
        ->willReturn(true);

    $this->rights
        ->expects($this->any())
        ->method('hasAdminRole')
        ->willReturn(true);

    $this->readPlaylistRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(true);

    $this->contactRepository
        ->expects($this->once())
        ->method('exist')
        ->with([12,12])
        ->willReturn([12]);

    $useCase(1, $request, $presenter);

    expect($presenter->data)->toBeInstanceOf(InvalidArgumentResponse::class)
        ->and($presenter->data->getMessage())
        ->toBe(PlaylistException::contactForShareShouldBeUnique()->getMessage());
});

it('should present an InvalidArgument Response when a contactgroup does not exist', function () {
    $useCase = new SharePlaylist(
        $this->rights,
        $this->validator,
        $this->adminUser,
        $this->writePlaylistShareRepository,
        $this->dataStorageEngine,
        $this->contactGroupRepository,
        $this->contactRepository
    );
    $presenter = new SharePlaylistPresenterStub();
    $request = new SharePlaylistRequest();
    $request->contactGroups = [['id' => 12, 'role' => 'editor'], ['id' => 13, 'role' => 'editor']];

    $this->rights
        ->expects($this->once())
        ->method('canCreate')
        ->willReturn(true);

    $this->rights
        ->expects($this->any())
        ->method('hasAdminRole')
        ->willReturn(true);

    $this->readPlaylistRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(true);

    $this->contactGroupRepository
        ->expects($this->once())
        ->method('exist')
        ->with([12,13])
        ->willReturn([12]);

    $useCase(1, $request, $presenter);

    expect($presenter->data)->toBeInstanceOf(InvalidArgumentResponse::class)
        ->and($presenter->data->getMessage())
        ->toBe(PlaylistException::contactGroupsDontExist([13])->getMessage());
});

it('should present an InvalidArgument Response when a contactgroup is duplicate in the request', function () {
    $useCase = new SharePlaylist(
        $this->rights,
        $this->validator,
        $this->adminUser,
        $this->writePlaylistShareRepository,
        $this->dataStorageEngine,
        $this->contactGroupRepository,
        $this->contactRepository
    );
    $presenter = new SharePlaylistPresenterStub();
    $request = new SharePlaylistRequest();
    $request->contactGroups = [['id' => 12, 'role' => 'editor'],['id' => 12, 'role' => 'editor']];

    $this->rights
        ->expects($this->once())
        ->method('canCreate')
        ->willReturn(true);

    $this->rights
        ->expects($this->any())
        ->method('hasAdminRole')
        ->willReturn(true);

    $this->readPlaylistRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(true);

    $this->contactGroupRepository
        ->expects($this->once())
        ->method('exist')
        ->with([12,12])
        ->willReturn([12]);

    $useCase(1, $request, $presenter);

    expect($presenter->data)->toBeInstanceOf(InvalidArgumentResponse::class)
        ->and($presenter->data->getMessage())
        ->toBe(PlaylistException::contactGroupForShareShouldBeUnique()->getMessage());
});

it('should present an ErrorResponse when an error occured while updating shares', function () {
    $useCase = new SharePlaylist(
        $this->rights,
        $this->validator,
        $this->adminUser,
        $this->writePlaylistShareRepository,
        $this->dataStorageEngine,
        $this->contactGroupRepository,
        $this->contactRepository
    );
    $presenter = new SharePlaylistPresenterStub();
    $request = new SharePlaylistRequest();
    $request->contactGroups = [['id' => 12, 'role' => 'editor']];

    $this->rights
        ->expects($this->once())
        ->method('canCreate')
        ->willReturn(true);

    $this->rights
        ->expects($this->any())
        ->method('hasAdminRole')
        ->willReturn(true);

    $this->readPlaylistRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(true);

    $this->contactGroupRepository
        ->expects($this->once())
        ->method('exist')
        ->with([12])
        ->willReturn([12]);

    $this->writePlaylistShareRepository
        ->expects($this->once())
        ->method('deletePlaylistShares')
        ->willThrowException(new \Exception());

    $useCase(1, $request, $presenter);

    expect($presenter->data)->toBeInstanceOf(ErrorResponse::class)
        ->and($presenter->data->getMessage())
        ->toBe(PlaylistException::errorWhileUpdatingShares()->getMessage());
});

it(
    'should present an InvalidArgument Response when a user is non-admin and the playlist'
        . 'is not shared with him as editor',
    function () {
        $useCase = new SharePlaylist(
            $this->rights,
            $this->validator,
            $this->nonAdminUser,
            $this->writePlaylistShareRepository,
            $this->dataStorageEngine,
            $this->contactGroupRepository,
            $this->contactRepository
        );
        $presenter = new SharePlaylistPresenterStub();
        $request = new SharePlaylistRequest();
        $request->contacts = [['id' => 12, 'role' => 'editor'], ['id' => 13, 'role' => 'editor']];

        $this->rights
            ->expects($this->once())
            ->method('canCreate')
            ->willReturn(true);

        $this->rights
            ->expects($this->any())
            ->method('hasAdminRole')
            ->willReturn(false);

        $this->readPlaylistRepository
            ->expects($this->once())
            ->method('exists')
            ->willReturn(true);

        $this->readPlaylistShareRepository
            ->expects($this->once())
            ->method('existsAsEditor')
            ->willReturn(false);

        $useCase(1, $request, $presenter);

        expect($presenter->data)->toBeInstanceOf(InvalidArgumentResponse::class)
            ->and($presenter->data->getMessage())
            ->toBe(PlaylistException::playlistNotSharedAsEditor(1)->getMessage());
    }
);

it(
    'should present an InvalidArgument Response when the contacts in the request '
    . 'are not part of user contact groups',
    function () {
        $useCase = new SharePlaylist(
            $this->rights,
            $this->validator,
            $this->nonAdminUser,
            $this->writePlaylistShareRepository,
            $this->dataStorageEngine,
            $this->contactGroupRepository,
            $this->contactRepository
        );
        $presenter = new SharePlaylistPresenterStub();
        $request = new SharePlaylistRequest();
        $request->contacts = [['id' => 12, 'role' => 'editor'], ['id' => 13, 'role' => 'editor']];

        $this->rights
            ->expects($this->once())
            ->method('canCreate')
            ->willReturn(true);

        $this->rights
            ->expects($this->any())
            ->method('hasAdminRole')
            ->willReturn(false);

        $this->readPlaylistRepository
            ->expects($this->once())
            ->method('exists')
            ->willReturn(true);

        $this->readPlaylistShareRepository
            ->expects($this->once())
            ->method('existsAsEditor')
            ->willReturn(true);

        $this->contactRepository
            ->expects($this->once())
            ->method('findContactIdsByContactGroups')
            ->willReturn([12,14,15]);

        $this->contactRepository
            ->expects($this->once())
            ->method('exist')
            ->with([12,13])
            ->willReturn([12,13]);

        $useCase(1, $request, $presenter);

        expect($presenter->data)->toBeInstanceOf(InvalidArgumentResponse::class)
            ->and($presenter->data->getMessage())
            ->toBe(PlaylistException::contactsAreNotInTheUserContactGroup([13])->getMessage());
    }
);


it(
    'should present an InvalidArgument Response when the user is not in the contactgroups in the request', function () {
        $useCase = new SharePlaylist(
            $this->rights,
            $this->validator,
            $this->nonAdminUser,
            $this->writePlaylistShareRepository,
            $this->dataStorageEngine,
            $this->contactGroupRepository,
            $this->contactRepository
        );
        $presenter = new SharePlaylistPresenterStub();
        $request = new SharePlaylistRequest();
        $request->contactGroups = [['id' => 12, 'role' => 'editor'], ['id' => 13, 'role' => 'editor']];

        $this->rights
            ->expects($this->once())
            ->method('canCreate')
            ->willReturn(true);

        $this->rights
            ->expects($this->any())
            ->method('hasAdminRole')
            ->willReturn(false);

        $this->readPlaylistRepository
            ->expects($this->once())
            ->method('exists')
            ->willReturn(true);

        $this->readPlaylistShareRepository
            ->expects($this->once())
            ->method('existsAsEditor')
            ->willReturn(true);

        $this->contactGroupRepository
            ->expects($this->once())
            ->method('findAllByUserId')
            ->willReturn([
                new ContactGroup(12,'cg1'),
                new ContactGroup(14,'cg2'),
                new ContactGroup(15,'cg3'),
            ]);

        $this->contactGroupRepository
            ->expects($this->once())
            ->method('exist')
            ->with([12,13])
            ->willReturn([12,13]);


        $useCase(1, $request, $presenter);

        expect($presenter->data)->toBeInstanceOf(InvalidArgumentResponse::class)
            ->and($presenter->data->getMessage())
            ->toBe(PlaylistException::userIsNotInContactGroups([13])->getMessage());
    }
);

it('should present a NoContent Resposne when no errors occured', function () {
    $useCase = new SharePlaylist(
        $this->rights,
        $this->validator,
        $this->adminUser,
        $this->writePlaylistShareRepository,
        $this->dataStorageEngine,
        $this->contactGroupRepository,
        $this->contactRepository
    );
    $presenter = new SharePlaylistPresenterStub();
    $request = new SharePlaylistRequest();
    $request->contactGroups = [['id' => 12, 'role' => 'editor']];

    $this->rights
        ->expects($this->once())
        ->method('canCreate')
        ->willReturn(true);

    $this->rights
        ->expects($this->any())
        ->method('hasAdminRole')
        ->willReturn(true);

    $this->readPlaylistRepository
        ->expects($this->once())
        ->method('exists')
        ->willReturn(true);

    $this->contactGroupRepository
        ->expects($this->once())
        ->method('exist')
        ->with([12])
        ->willReturn([12]);

    $this->writePlaylistShareRepository
        ->expects($this->once())
        ->method('deletePlaylistShares');

    $this->writePlaylistShareRepository
        ->expects($this->once())
        ->method('addPlaylistContactGroupShares')
        ->with(1, $request->contactGroups);

    $useCase(1, $request, $presenter);

    expect($presenter->data)->toBeInstanceOf(NoContentResponse::class);
});
