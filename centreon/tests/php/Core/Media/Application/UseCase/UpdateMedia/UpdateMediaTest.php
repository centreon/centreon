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

declare(strict_types = 1);

namespace Tests\Core\Media\Application\UseCase\UpdateMedia;

use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Media\Application\Exception\MediaException;
use Core\Media\Application\Repository\ReadMediaRepositoryInterface;
use Core\Media\Application\Repository\WriteMediaRepositoryInterface;
use Core\Media\Application\UseCase\UpdateMedia\UpdateMedia;
use Core\Media\Application\UseCase\UpdateMedia\UpdateMediaRequest;
use Core\Media\Application\UseCase\UpdateMedia\UpdateMediaResponse;
use Core\Media\Domain\Model\Media;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Tests\Core\Media\Infrastructure\API\UpdateMedia\UpdateMediaPresenterStub;

beforeEach(function (): void {
    $this->writeMediaRepository = $this->createMock(WriteMediaRepositoryInterface::class);
    $this->readMediaRepository = $this->createMock(ReadMediaRepositoryInterface::class);
    $this->dataStorageEngine = $this->createMock(DataStorageEngineInterface::class);
    $this->presenter = new UpdateMediaPresenterStub(
        $this->createMock(PresenterFormatterInterface::class)
    );
    $this->useCase = new UpdateMedia(
        $this->writeMediaRepository,
        $this->readMediaRepository,
        $this->dataStorageEngine,
    );

    $this->imagePath = __DIR__ . '/../../../Infrastructure/API/UpdateMedia/logo.jpg';

    $this->uploadedFile = new UploadedFile(
        $this->imagePath,
        'logo.jpg',
        'image/jpg',
    );
});

it('should present an NotFoundResponse when the media to update does not exist', function (): void {
    $this->readMediaRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn(null);

    $request = new UpdateMediaRequest(
        $this->uploadedFile->getClientOriginalName(),
        $this->uploadedFile->getContent()
    );

    ($this->useCase)(1, $request, $this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(NotFoundResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe('Media not found');
});

it('should present an ErrorResponse when an exception is thrown', function (): void {
    $this->readMediaRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn(new Media( 1, 'filename', 'directory', null, null));

    $this->writeMediaRepository
        ->expects($this->once())
        ->method('update')
        ->willThrowException(new \Exception());

    $request = new UpdateMediaRequest(
        $this->uploadedFile->getClientOriginalName(),
        $this->uploadedFile->getContent()
    );
    ($this->useCase)(1, $request, $this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(MediaException::errorWhileUpdatingMedia()->getMessage());
});

it('should present an UpdateMediaResponse when the media has been updated', function (): void {
    $this->readMediaRepository
        ->expects($this->once())
        ->method('findById')
        ->willReturn(new Media(1, 'logo.jpg', 'directory', null, null));

    $this->writeMediaRepository
        ->expects($this->once())
        ->method('update');

    $request = new UpdateMediaRequest(
        $this->uploadedFile->getClientOriginalName(),
        $this->uploadedFile->getContent()
    );

    ($this->useCase)(1, $request, $this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(UpdateMediaResponse::class)
        ->and($this->presenter->response->updatedMedia)
        ->and($this->presenter->response->updatedMedia['id'])->toEqual(1)
        ->and($this->presenter->response->updatedMedia['filename'])->toEqual('logo.jpg')
        ->and($this->presenter->response->updatedMedia['directory'])->toEqual('directory')
        ->and($this->presenter->response->updatedMedia['md5'])->toEqual(md5(file_get_contents($this->imagePath)));
});
