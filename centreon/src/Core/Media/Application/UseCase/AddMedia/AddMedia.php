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

namespace Core\Media\Application\UseCase\AddMedia;

use Assert\AssertionFailedException;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Media\Application\Exception\MediaException;
use Core\Media\Application\Repository\ReadMediaRepositoryInterface;
use Core\Media\Application\Repository\WriteMediaRepositoryInterface;
use Core\Media\Domain\Model\NewMedia;
use enshrined\svgSanitize\Sanitizer;
use Symfony\Component\Mime\MimeTypes;

/**
 * @phpstan-import-type _MediaRecorded from AddMediaResponse
 * @phpstan-import-type _Errors from AddMediaResponse
 */
final class AddMedia
{
    use LoggerTrait;

    /** @var list<string> */
    private array $fileExtensionsAllowed;

    /**
     * @param WriteMediaRepositoryInterface $writeMediaRepository
     * @param ReadMediaRepositoryInterface $readMediaRepository
     * @param DataStorageEngineInterface $dataStorageEngine
     * @param Sanitizer $svgSanitizer
     */
    public function __construct(
        private readonly WriteMediaRepositoryInterface $writeMediaRepository,
        private readonly ReadMediaRepositoryInterface $readMediaRepository,
        private readonly DataStorageEngineInterface $dataStorageEngine,
        private readonly Sanitizer $svgSanitizer,
    ) {
    }

    /**
     * @param AddMediaRequest $request
     * @param AddMediaPresenterInterface $presenter
     */
    public function __invoke(AddMediaRequest $request, AddMediaPresenterInterface $presenter): void
    {
        try {
            $this->addMimeTypeFilter('image/png', 'image/gif', 'image/jpeg', 'image/svg+xml');
            [$mediasRecorded, $errors] = $this->addMedias($this->createMedias($request));
            $presenter->presentResponse($this->createResponse($mediasRecorded, $errors));
        } catch (AssertionFailedException $ex) {
            $presenter->presentResponse(new InvalidArgumentResponse($ex));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (\Throwable $ex) {
            $presenter->presentResponse(
                new ErrorResponse(MediaException::errorWhileAddingMedia())
            );
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        }
    }

    /**
     * @param string $mimeType
     */
    private function addFileExtensions(string $mimeType): void
    {
        foreach (MimeTypes::getDefault()->getExtensions($mimeType) as $oneMimeType) {
            $this->fileExtensionsAllowed[] = $oneMimeType;
        }
    }

    private function addMimeTypeFilter(string ...$mimeTypes): void
    {
        foreach ($mimeTypes as $mimeType) {
            $this->addFileExtensions($mimeType);
        }
    }

    /**
     * @param \Iterator<int, NewMedia> $medias
     *
     * @throws \Throwable
     *
     * @return array{0: list<_MediaRecorded>, 1: list<_Errors>}
     */
    private function addMedias(\Iterator $medias): array
    {
        $mediaRecorded = [];
        $errors = [];
        try {
            $this->dataStorageEngine->startTransaction();
            foreach ($medias as $media) {

                $fileInfo = pathinfo($media->getFilename());
                if (! in_array($fileInfo['extension'] ?? '', $this->fileExtensionsAllowed, true)) {
                    $errors[] = $this->createMediaError($media, MediaException::fileExtensionNotAuthorized()->getMessage());
                    continue;
                }

                /** @var NewMedia $media */
                if (! $this->readMediaRepository->existsByPath(
                        $media->getDirectory() . DIRECTORY_SEPARATOR . $media->getFilename()
                    )
                ) {
                    $fileContent = $media->getData();
                    if (array_key_exists('extension',$fileInfo) && $fileInfo['extension'] === 'svg') {
                        $this->svgSanitizer->minify(true);
                        $fileContent = $this->svgSanitizer->sanitize($fileContent);
                    }
                    $media->setData($fileContent);
                    $hash = $media->hash();
                    $this->info('Add media', [
                        'filename' => $media->getFilename(),
                        'directory' => $media->getDirectory(),
                        'md5' => $hash,
                    ]);
                    $mediaRecorded[] = [
                        'id' => $this->writeMediaRepository->add(
                            new NewMedia($media->getFilename(),$media->getDirectory(), $fileContent)
                        ),
                        'filename' => $media->getFilename(),
                        'directory' => $media->getDirectory(),
                        'md5' => $hash,
                    ];

                } else {
                    $errors[] = $this->createMediaError($media, MediaException::mediaAlreadyExists()->getMessage());
                }
            }
            $this->dataStorageEngine->commitTransaction();
        } catch (\Throwable $ex) {
            $this->dataStorageEngine->rollbackTransaction();

            throw $ex;
        }

        return [$mediaRecorded, $errors];
    }

    /**
     * @param NewMedia $newMedia
     * @param string $reason
     *
     * @return _Errors
     */
    private function createMediaError(NewMedia $newMedia, string $reason): array
    {
        $this->info('Media already exists', [
            'filename' => $newMedia->getFilename(),
            'directory' => $newMedia->getDirectory(),
        ]);

        return [
            'filename' => $newMedia->getFilename(),
            'directory' => $newMedia->getDirectory(),
            'reason' => $reason,
        ];
    }

    /**
     * @param AddMediaRequest $request
     *
     * @return \Iterator<int, NewMedia>
     */
    private function createMedias(AddMediaRequest $request): \Iterator
    {
        return new class($request->medias, $request->directory) implements \Iterator {
            private int $position = 0;

            /**
             * @param \Iterator<string, string> $medias
             * @param string $directory
             */
            public function __construct(readonly private \Iterator $medias, readonly private string $directory)
            {
            }

            public function current(): NewMedia
            {
                $data = $this->medias->current(); // 'current' method must be called before the 'key' method

                return new NewMedia(
                    $this->medias->key(),
                    $this->directory,
                    $data
                );
            }

            public function next(): void
            {
                $this->position++;
                $this->medias->next();
            }

            public function key(): int
            {
                return $this->position;
            }

            public function valid(): bool
            {
                return $this->medias->valid();
            }

            public function rewind(): void
            {
                $this->position = 0;
                $this->medias->rewind();
            }
        };
    }

    /**
     * @param list<_MediaRecorded> $mediasRecorded
     * @param list<_Errors> $errors
     *
     * @return AddMediaResponse
     */
    private function createResponse(array $mediasRecorded, array $errors): AddMediaResponse
    {
        $response = new AddMediaResponse();
        $response->mediasRecorded = $mediasRecorded;
        $response->errors = $errors;

        return $response;
    }
}
