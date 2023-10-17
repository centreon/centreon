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
use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Media\Application\Exception\MediaException;
use Core\Media\Application\Repository\ReadMediaRepositoryInterface;
use Core\Media\Application\Repository\WriteMediaRepositoryInterface;
use Core\Media\Domain\Model\NewMedia;

/**
 * @phpstan-type _MediaRecorded array<array{
 *      id: int,
 *      filename: string,
 *      directory: string,
 *      md5: string,
 *  }>
 */
class AddMedia
{
    use LoggerTrait;

    public function __construct(
        readonly private WriteMediaRepositoryInterface $writeMediaRepository,
        readonly private ReadMediaRepositoryInterface $readMediaRepository,
        readonly private DataStorageEngineInterface $dataStorageEngine,
        readonly private ContactInterface $user,
    ) {
    }

    /**
     * @param AddMediaRequest $request
     * @param AddMediaPresenterInterface $presenter
     */
    public function __invoke(AddMediaRequest $request, AddMediaPresenterInterface $presenter): void
    {
        try {
            if (! $this->user->hasTopologyRole(Contact::ROLE_ADMINISTRATION_PARAMETERS_IMAGES_RW)) {
                $this->error(
                    "User doesn't have sufficient rights to add a media", ['user_id' => $this->user->getId()]
                );
                $presenter->presentResponse(
                    new ForbiddenResponse(MediaException::addNotAllowed()->getMessage())
                );

                return;
            }

            $mediasRecorded = $this->addMedias($this->createMedias($request));
            $presenter->presentResponse($this->createResponse($mediasRecorded));
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
     * @param \Generator<NewMedia> $medias
     *
     * @throws \Throwable
     *
     * @return _MediaRecorded
     */
    private function addMedias(\Generator $medias): array
    {
        /**
         * @var _MediaRecorded $mediaRecorded
         */
        $mediaRecorded = [];
        try {
            $this->dataStorageEngine->startTransaction();
            foreach ($medias as $media) {
                /** @var NewMedia $media */
                if (! $this->readMediaRepository->existsByPath(
                        $media->getFilepath() . DIRECTORY_SEPARATOR . $media->getFilename()
                    )
                ) {
                    $md5 = md5($media->getData());
                    $this->info('Add media', [
                        'filename' => $media->getFilename(),
                        'directory' => $media->getFilepath(),
                        'md5' => $md5,
                    ]);
                    $mediaRecorded[] = [
                        'id' => $this->writeMediaRepository->add($media),
                        'filename' => $media->getFilename(),
                        'directory' => $media->getFilepath(),
                        'md5' => $md5,
                    ];

                } else {
                    $this->info('Media already exists', [
                        'filename' => $media->getFilename(),
                        'directory' => $media->getFilepath(),
                    ]);
                }
            }
            $this->dataStorageEngine->commitTransaction();
        } catch (\Throwable $ex) {
            $this->dataStorageEngine->rollbackTransaction();

            throw $ex;
        }

        return $mediaRecorded;
    }

    /**
     * @param AddMediaRequest $request
     *
     * @throws AssertionFailedException
     *
     * @return \Generator<NewMedia>
     */
    private function createMedias(AddMediaRequest $request): \Generator
    {
        foreach ($request->medias as $dto) {
            yield new NewMedia($dto->filename, $request->directory, $dto->data);
        }
    }

    /**
     * @param _MediaRecorded $mediasRecorded
     *
     * @return AddMediaResponse
     */
    private function createResponse(array $mediasRecorded): AddMediaResponse
    {
        $response = new AddMediaResponse();
        $response->mediasRecorded = $mediasRecorded;

        return $response;
    }
}
