<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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

namespace Tests\Core\Media\Application\UseCase\DeleteMedia;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Contact\Domain\AdminResolver;
use Core\Infrastructure\Common\Api\DefaultPresenter;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Media\Application\Exception\MediaException;
use Core\Media\Application\Repository\ReadImageFolderRepositoryInterface;
use Core\Media\Application\Repository\ReadMediaRepositoryInterface;
use Core\Media\Application\Repository\WriteMediaRepositoryInterface;
use Core\Media\Application\UseCase\DeleteMedia\DeleteMedia;
use Core\Media\Domain\Model\Media;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

beforeEach(function (): void {
    $this->useCase = new DeleteMedia(
        $this->readMediaRepository = $this->createMock(ReadMediaRepositoryInterface::class),
        $this->writeMediaRepository = $this->createMock(WriteMediaRepositoryInterface::class),
        $this->readAccessGroupRepository = $this->createMock(ReadAccessGroupRepositoryInterface::class),
        $this->readImageFolderRepository = $this->createMock(ReadImageFolderRepositoryInterface::class),
        $this->user = $this->createMock(ContactInterface::class),
        $this->adminResolver = $this->createMock(AdminResolver::class),
    );

    $this->presenter = new DefaultPresenter($this->createMock(PresenterFormatterInterface::class));

    $this->media = new Media(
        id: 1,
        filename: 'test.jpg',
        directory: 'test',
        comment: 'A test image',
        data: null,
    );
});

it('should present an ErrorResponse when an exception is thrown', function (): void {
    $this->adminResolver
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->writeMediaRepository
        ->expects($this->once())
        ->method('delete')
        ->willThrowException(new \Exception());

    $this->readMediaRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($this->media);

    ($this->useCase)($this->media->getId(), $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->getResponseStatus()?->getMessage())
        ->toBe(MediaException::errorWhileDeletingMedia()->getMessage());
});

it('should present a NotFoundResponse when media is not found as admin', function (): void {
    $this->adminResolver
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->readMediaRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn(null);

    ($this->useCase)($this->media->getId(), $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(NotFoundResponse::class)
        ->and($this->presenter->getResponseStatus()?->getMessage())
        ->toBe('Media not found');
});

it('should present a NoContentResponse when media is successfully deleted as admin', function (): void {
    $this->adminResolver
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->writeMediaRepository
        ->expects($this->once())
        ->method('delete');

    $this->readMediaRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($this->media);

    ($this->useCase)($this->media->getId(), $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(NoContentResponse::class);
});

it('should present a NoContentResponse when media is successfully deleted as non-admin with access groups', function (): void {
    $accessGroup = new AccessGroup(1, 'group1', 'group1_alias');

    $this->adminResolver
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(false);

    $this->readAccessGroupRepository
        ->expects($this->once())
        ->method('findByContact')
        ->willReturn([$accessGroup]);

    $this->readImageFolderRepository
        ->expects($this->once())
        ->method('hasAccessToAllImageFolders')
        ->willReturn(false);

    $this->readMediaRepository
        ->expects($this->once())
        ->method('existsByAccessGroups')
        ->willReturn(true);

    $this->readMediaRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($this->media);

    $this->writeMediaRepository
        ->expects($this->once())
        ->method('delete');

    ($this->useCase)($this->media->getId(), $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(NoContentResponse::class);
});

it('should present a NoContentResponse when media is successfully deleted as non-admin with access to all image folders', function (): void {
    $accessGroup = new AccessGroup(1, 'group1', 'group1_alias');

    $this->adminResolver
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(false);

    $this->readAccessGroupRepository
        ->expects($this->once())
        ->method('findByContact')
        ->willReturn([$accessGroup]);

    $this->readImageFolderRepository
        ->expects($this->once())
        ->method('hasAccessToAllImageFolders')
        ->willReturn(true);

    $this->readMediaRepository
        ->expects($this->never())
        ->method('existsByAccessGroups');

    $this->readMediaRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($this->media);

    $this->writeMediaRepository
        ->expects($this->once())
        ->method('delete');

    ($this->useCase)($this->media->getId(), $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(NoContentResponse::class);
});

it('should present a NotFoundResponse as non-admin when media is not accessible via access groups', function (): void {
    $accessGroup = new AccessGroup(1, 'group1', 'group1_alias');

    $this->adminResolver
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(false);

    $this->readAccessGroupRepository
        ->expects($this->once())
        ->method('findByContact')
        ->willReturn([$accessGroup]);

    $this->readImageFolderRepository
        ->expects($this->once())
        ->method('hasAccessToAllImageFolders')
        ->willReturn(false);

    $this->readMediaRepository
        ->expects($this->once())
        ->method('existsByAccessGroups')
        ->willReturn(false);

    $this->readMediaRepository
        ->expects($this->never())
        ->method('findById');

    $this->writeMediaRepository
        ->expects($this->never())
        ->method('delete');

    ($this->useCase)($this->media->getId(), $this->presenter);

    expect($this->presenter->getResponseStatus())
        ->toBeInstanceOf(NotFoundResponse::class)
        ->and($this->presenter->getResponseStatus()?->getMessage())
        ->toBe('Media not found');
});
