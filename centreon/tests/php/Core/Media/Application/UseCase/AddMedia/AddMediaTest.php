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
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Media\Application\Exception\MediaException;
use Core\Media\Application\Repository\ReadMediaRepositoryInterface;
use Core\Media\Application\Repository\WriteMediaRepositoryInterface;
use Core\Media\Application\UseCase\AddMedia\AddMedia;
use Core\Media\Application\UseCase\AddMedia\AddMediaRequest;
use Core\Media\Application\UseCase\AddMedia\AddMediaResponse;
use enshrined\svgSanitize\Sanitizer;
use Tests\Core\Media\Infrastructure\API\AddMedia\AddMediaPresenterStub;

beforeEach(function (): void {
    $this->writeMediaRepository = $this->createMock(WriteMediaRepositoryInterface::class);
    $this->readMediaRepository = $this->createMock(ReadMediaRepositoryInterface::class);
    $this->dataStorageEngine = $this->createMock(DataStorageEngineInterface::class);
    $this->svgSanitizer = $this->createMock(Sanitizer::class);
    $this->presenter = new AddMediaPresenterStub(
        $this->createMock(PresenterFormatterInterface::class)
    );
    $this->useCase = new AddMedia(
        $this->writeMediaRepository,
        $this->readMediaRepository,
        $this->dataStorageEngine,
        $this->svgSanitizer,
    );

    $this->imagePath = realpath(__DIR__ . '/../../../Infrastructure/API/AddMedia/logo.jpg');

    $this->mediaGenerator = new class($this->imagePath) implements \Iterator {
        private int $counter = 0;

        public function __construct(readonly private string $imagePath)
        {
        }

        public function current(): mixed
        {
            return file_get_contents($this->imagePath);
        }

        public function next(): void
        {
            $this->counter++;
        }

        public function key(): mixed
        {
            return 'logo.jpg';
        }

        public function valid(): bool
        {
            return $this->counter < 1;
        }

        public function rewind(): void
        {
            $this->counter = 0;
        }
    };
});

it('should present an ErrorResponse when an exception is thrown', function (): void {
    $this->writeMediaRepository
        ->expects($this->once())
        ->method('add')
        ->willThrowException(new \Exception());

    $request = new AddMediaRequest($this->mediaGenerator);
    $request->directory = 'filepath';
    ($this->useCase)($request, $this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(ErrorResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(MediaException::errorWhileAddingMedia()->getMessage());
});

it('should present an InvalidArgumentResponse when a field assert of NewMedia fails', function (): void {
    $request = new AddMediaRequest($this->mediaGenerator);
    $request->directory = 'badfilepath^$';
    ($this->useCase)($request, $this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(InvalidArgumentResponse::class)
        ->and($this->presenter->response->getMessage())
        ->toBe(AssertionException::matchRegex(
            $request->directory,
            '/^[a-zA-Z0-9_-]+$/',
            'NewMedia::directory',
        )->getMessage());
});

it('should present an AddMediaResponse with an empty response when the media already exists', function (): void {
    $this->readMediaRepository
        ->expects($this->once())
        ->method('existsByPath')
        ->willReturn(true);

    $request = new AddMediaRequest($this->mediaGenerator);
    $request->directory = 'filepath';

    ($this->useCase)($request, $this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(AddMediaResponse::class)
        ->and($this->presenter->response->mediasRecorded)
        ->toBeEmpty();
});

it('should present an AddMediaResponse when the media does not exist', function (): void {
    $this->readMediaRepository
        ->expects($this->once())
        ->method('existsByPath')
        ->willReturn(false);

    $this->writeMediaRepository
        ->expects($this->once())
        ->method('add')
        ->willReturn(1);

    $request = new AddMediaRequest($this->mediaGenerator);
    $request->directory = 'filepath';

    ($this->useCase)($request, $this->presenter);

    expect($this->presenter->response)
        ->toBeInstanceOf(AddMediaResponse::class)
        ->and($this->presenter->response->mediasRecorded)
        ->toHaveCount(1)
        ->and($this->presenter->response->mediasRecorded[0]['id'])->toEqual(1)
        ->and($this->presenter->response->mediasRecorded[0]['filename'])->toEqual('logo.jpg')
        ->and($this->presenter->response->mediasRecorded[0]['directory'])->toEqual('filepath')
        ->and($this->presenter->response->mediasRecorded[0]['md5'])->toEqual(md5(file_get_contents($this->imagePath)));
});
