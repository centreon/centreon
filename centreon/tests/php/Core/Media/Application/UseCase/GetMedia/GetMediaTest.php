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

namespace Tests\Core\Media\Application\UseCase\GetMedia;



use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Media\Application\UseCase\GetMedia\GetMedia;
use Core\Media\Domain\Model\Media;
use Core\Media\Application\Repository\ReadMediaRepositoryInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Media\Application\Exception\MediaException;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Contact\Domain\AdminResolver;
use Core\Media\Application\UseCase\GetMedia\GetMediaResponse;



beforeEach(function (): void {
    $this->useCase = new GetMedia(
        $this->readAccessGroupRepository =  $this->createMock(ReadAccessGroupRepositoryInterface::class),
        $this->readMediaRepository = $this->createMock(ReadMediaRepositoryInterface::class),
        $this->user = $this->createMock(ContactInterface::class),
        $this->adminResolver = $this->createMock(AdminResolver::class),
    );



    $this->media = new Media(
      id: 1,
      filename: "test.jpg",
      directory: "test",
      comment: "A test image",
      data: null,
    );

});

it('should present an ErrorResponse when an exception is thrown', function (): void {

    $this->adminResolver
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->readMediaRepository
        ->expects($this->once())
        ->method('findById')
        ->willThrowException(new \Exception());

    $response = ($this->useCase)($this->media->getId());

    expect($response)
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($response->getMessage())
        ->toBe(MediaException::errorWhileRetrieving()->getMessage());
});

it('should present an NotFoundResponse; when media is not found', function (): void {

    $this->adminResolver
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->readMediaRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn(null);

    $response = ($this->useCase)($this->media->getId());

    expect($response)
        ->toBeInstanceOf(NotFoundResponse::class)
        ->and($response->getMessage())
        ->toBe("Media not found");
});

it('should present a GetMediaResponse as admin', function (): void {

    $this->adminResolver
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $this->readMediaRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($this->media);

    $response = ($this->useCase)($this->media->getId());

    expect($response)
        ->toBeInstanceOf(GetMediaResponse::class)
        ->and($response->id)
        ->toEqual($this->media->getId())
        ->and($response->filename)
        ->toEqual($this->media->getFilename())
        ->and($response->comment)
        ->toEqual($this->media->getComment())
        ->and($response->directory)
        ->toEqual($this->media->getDirectory())
        ->and($response->md5)
        ->toEqual($this->media->getEqualityHash());
});

it('should present a GetMediaResponse as non-admin user', function (): void {

    $this->adminResolver
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(false);

    $this->readAccessGroupRepository
        ->expects($this->once())
        ->method('findByContact')
        ->willReturn([]);

    $this->readMediaRepository
        ->expects($this->once())
        ->method('existsByAccessGroups')
        ->willReturn(true);

    $this->readMediaRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn($this->media);

    $response = ($this->useCase)($this->media->getId());

    expect($response)
        ->toBeInstanceOf(GetMediaResponse::class)
        ->and($response->id)
        ->toEqual($this->media->getId())
        ->and($response->filename)
        ->toEqual($this->media->getFilename())
        ->and($response->comment)
        ->toEqual($this->media->getComment())
        ->and($response->directory)
        ->toEqual($this->media->getDirectory())
        ->and($response->md5)
        ->toEqual($this->media->getEqualityHash());

});