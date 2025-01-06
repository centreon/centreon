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

namespace Core\Media\Application\UseCase\UpdateMedia;

use Assert\AssertionFailedException;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Media\Application\Exception\MediaException;
use Core\Media\Application\Repository\ReadMediaRepositoryInterface;
use Core\Media\Application\Repository\WriteMediaRepositoryInterface;
use Core\Media\Domain\Model\Media;
use Symfony\Component\Mime\MimeTypes;

/**
 * @phpstan-import-type _UpdatedMedia from UpdateMediaResponse
 */
final class UpdateMedia
{
    use LoggerTrait;

    /** @var list<string> */
    private array $fileExtensionsAllowed;

    public function __construct(
        private readonly WriteMediaRepositoryInterface $writeMediaRepository,
        private readonly ReadMediaRepositoryInterface $readMediaRepository,
        private readonly DataStorageEngineInterface $dataStorageEngine,
    ) {
    }

    /**
     * @param UpdateMediaRequest $request
     * @param UpdateMediaPresenterInterface $presenter
     * @param int $mediaId
     */
    public function __invoke(int $mediaId, UpdateMediaRequest $request, UpdateMediaPresenterInterface $presenter): void
    {
        try {
            $media = $this->readMediaRepository->findById($mediaId);

            if ($media === null) {
                $this->error('Media not found', ['media_id' => $mediaId]);
                $presenter->presentResponse(new NotFoundResponse('Media'));

                return;
            }

            $this->addMimeTypeFilter('image/png', 'image/gif', 'image/jpeg', 'image/svg+xml');
            $this->updateExistingMediaContent($media, $request);

            $updateResult = $this->updateMedia($media);

            $presenter->presentResponse($this->createResponse($updateResult));
        } catch (AssertionFailedException $ex) {
            $presenter->presentResponse(new InvalidArgumentResponse($ex));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (\Throwable $ex) {
            $presenter->presentResponse(
                new ErrorResponse(MediaException::errorWhileUpdatingMedia())
            );
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        }
    }

    /**
     * @param string $fileName
     *
     * @return bool
     */
    private function isExtensionAllowed(string $fileName): bool
    {
        $fileInformation = pathinfo($fileName);

        return in_array($fileInformation['extension'] ?? '', $this->fileExtensionsAllowed, true);
    }

    /**
     * @param Media $existingMedia
     * @param UpdateMediaRequest $request
     *
     * @throws MediaException
     *
     * @return Media
     */
    private function updateExistingMediaContent(Media $existingMedia, UpdateMediaRequest $request): Media
    {
        if (! $this->isExtensionAllowed($request->fileName)) {
            throw MediaException::fileExtensionNotAuthorized();
        }

        $existingMedia->setData($request->data);

        return $existingMedia;
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
     * @param Media $media
     *
     * @throws \Throwable
     *
     * @return _UpdatedMedia
     */
    private function updateMedia(Media $media): array
    {
        try {
            $this->dataStorageEngine->startTransaction();

            $hash = $media->hash();

            $this->writeMediaRepository->update($media);

            $this->info(
                'Updating media',
                [
                    'id' => $media->getId(),
                    'filename' => $media->getFilename(),
                    'directory' => $media->getDirectory(),
                    'md5' => $hash,
                ]
            );

            $this->dataStorageEngine->commitTransaction();
        } catch (\Throwable $ex) {
            $this->dataStorageEngine->rollbackTransaction();

            throw $ex;
        }

        return [
            'id' => $media->getId(),
            'filename' => $media->getFilename(),
            'directory' => $media->getDirectory(),
            'md5' => $hash,
        ];
    }

    /**
     * @param _UpdatedMedia $updatedMedia
     *
     * @return UpdateMediaResponse
     */
    private function createResponse(array $updatedMedia): UpdateMediaResponse
    {
        $response = new UpdateMediaResponse();
        $response->updatedMedia = $updatedMedia;

        return $response;
    }
}

