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

namespace Tests\Core\Media\Application\UseCase\AddMedia;

use Centreon\Domain\Common\Assertion\AssertionException;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Media\Application\Exception\MediaException;
use Core\Media\Application\Repository\ReadMediaRepositoryInterface;
use Core\Media\Application\Repository\WriteMediaRepositoryInterface;
use Core\Media\Application\UseCase\AddMedia\AddMedia;
use Core\Media\Application\UseCase\AddMedia\AddMediaRequest;
use Core\Media\Application\UseCase\AddMedia\MediaDto;
use Core\Media\Domain\Model\NewMedia;
use Tests\Core\Media\Infrastructure\API\AddMedia\AddMediaPresenterStub;

beforeEach(function(): void {
    $this->writeMediaRepository = $this->createMock(WriteMediaRepositoryInterface::class);
    $this->readMediaRepository = $this->createMock(ReadMediaRepositoryInterface::class);
    $this->dataStorageEngine = $this->createMock(DataStorageEngineInterface::class);
    $this->user = $this->createMock(ContactInterface::class);
    $this->presenter = new AddMediaPresenterStub(
        $this->createMock(PresenterFormatterInterface::class)
    );
    $this->useCase = new AddMedia(
        $this->writeMediaRepository,
        $this->readMediaRepository,
        $this->dataStorageEngine,
        $this->user,
    );

    /** @var \Generator<NewMedia> mediaGenerator */
    $this->mediaGenerator = function(): \Generator {
        $imagePath = realpath(__DIR__ . '/../../../Infrastructure/API/AddMedia/logo.jpg');
        yield new MediaDto('logo.jpg', file_get_contents($imagePath));
    };
});

it('should present a ForbiddenResponse when a user has insufficient rights', function(): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(false);

    $request = new AddMediaRequest(($this->mediaGenerator)());
    ($this->useCase)($request, $this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(ForbiddenResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(MediaException::addNotAllowed()->getMessage());
});

it('should present an ErrorResponse when an exception is thrown', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $this->writeMediaRepository
        ->expects($this->once())
        ->method('add')
        ->willThrowException(new \Exception());

    $request = new AddMediaRequest(($this->mediaGenerator)());
    $request->directory = 'filepath';
    ($this->useCase)($request, $this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(MediaException::errorWhileAddingMedia()->getMessage());
});

it('should present an InvalidArgumentResponse when a field assert of NewMedia failed', function (): void {
    $this->user
        ->expects($this->once())
        ->method('hasTopologyRole')
        ->willReturn(true);

    $request = new AddMediaRequest(($this->mediaGenerator)());
    $request->directory = 'badfilepath^$';
    ($this->useCase)($request, $this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(InvalidArgumentResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(AssertionException::matchRegex(
            $request->directory,
            '/^[a-zA-Z0-9_-]+$/',
            'Media::filepath',
        )->getMessage());
});
