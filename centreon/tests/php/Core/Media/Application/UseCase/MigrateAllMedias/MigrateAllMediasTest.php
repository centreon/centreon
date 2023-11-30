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

namespace Tests\Core\Media\Application\UseCase\MigrateAllMedias;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Media\Application\Exception\MediaException;
use Core\Media\Application\Repository\ReadMediaRepositoryInterface;
use Core\Media\Application\Repository\WriteMediaRepositoryInterface;
use Core\Media\Application\UseCase\MigrateAllMedias\MigrateAllMedias;
use Core\Media\Application\UseCase\MigrateAllMedias\MigrateAllMediasRequest;
use Core\Media\Application\UseCase\MigrateAllMedias\MigrationAllMediasResponse;
use Core\Media\Domain\Model\Media;
use Tests\Core\Media\Infrastructure\Command\MigrateAllMedias\MigrateAllMediasPresenterStub;

beforeEach(function (): void {
    $this->readMediaRepository = $this->createMock(ReadMediaRepositoryInterface::class);
    $this->writeMediaRepository = $this->createMock(WriteMediaRepositoryInterface::class);

    $this->useCase = new MigrateAllMedias($this->readMediaRepository, $this->writeMediaRepository);
    $this->presenter = new MigrateAllMediasPresenterStub();
    $this->contact = $this->createMock(ContactInterface::class);
    $this->request = new MigrateAllMediasRequest();
    $this->request->contact = $this->contact;
});

it('should present an Exception if the user is not an administrator', function (): void{
    $this->request->contact
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(false);
    ($this->useCase)($this->request, $this->presenter);
    expect($this->presenter->response)
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(MediaException::operationRequiresAdminUser()->getMessage());
});

it('should present a MigrateAllMediasResponse when the user is an admin', function (): void{
    $this->request->contact
        ->expects($this->once())
        ->method('isAdmin')
        ->willReturn(true);

    $mediaWithoutData = new Media(1, 'media1.png', 'img', null, null);
    $mediaWithData = new Media(2, 'media2.png', 'img', null, 'fake data');
    $medias = new \ArrayIterator([$mediaWithoutData, $mediaWithData]);
    $this->readMediaRepository
        ->expects($this->once())
        ->method('findAll')
        ->willReturn($medias);

    ($this->useCase)($this->request, $this->presenter);
    expect($this->presenter->response)->toBeInstanceOf(MigrationAllMediasResponse::class);

    $check = [
        $mediaWithoutData->getFilename() => 0,
        $mediaWithData->getFilename() => 0,
    ];
    foreach ($this->presenter->response->results as $responseMedia) {
        if ($responseMedia->filename === $mediaWithoutData->getFilename()) {
            $check[$mediaWithoutData->getFilename()]++;
            expect($responseMedia->directory)
                ->tobe('img')
                ->and($responseMedia->reason)
                ->tobe('The file img/media1.png does not exist');
        }
        if ($responseMedia->filename === $mediaWithData->getFilename()) {
            $check[$mediaWithData->getFilename()]++;
            expect($responseMedia->directory)
                ->tobe('img')
                ->and($responseMedia->md5)
                ->tobe(md5($mediaWithData->getData() ?? ''));
        }
    }
    expect($check[$mediaWithoutData->getFilename()])->toBe(1);
    expect($check[$mediaWithData->getFilename()])->toBe(1);
});
