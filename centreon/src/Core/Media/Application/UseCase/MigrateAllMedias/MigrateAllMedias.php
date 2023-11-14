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

namespace Core\Media\Application\UseCase\MigrateAllMedias;

use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Media\Application\Exception\MediaException;
use Core\Media\Application\Repository\ReadMediaRepositoryInterface;
use Core\Media\Application\Repository\WriteMediaRepositoryInterface;
use Core\Media\Domain\Model\Media;
use Core\Media\Domain\Model\NewMedia;

final class MigrateAllMedias
{
    use LoggerTrait;

    private MigrationAllMediasResponse $response;

    public function __construct(
        readonly private ReadMediaRepositoryInterface $readMediaRepository,
        readonly private WriteMediaRepositoryInterface $writeMediaRepository,
     ) {
        $this->response = new MigrationAllMediasResponse();
    }

    public function __invoke(MigrateAllMediasRequest $request, MigrateAllMediasPresenterInterface $presenter): void
    {
        try {
            if ($request->contact !== null && ! $request->contact->isAdmin()) {
                throw MediaException::operationRequiresAdminUser();
            }
            $medias = $this->readMediaRepository->findAll();
            $this->migrateMedias($medias, $this->response);
            $presenter->presentResponse($this->response);
        } catch (MediaException $ex) {
            $this->error('Media migration requires an admin user', ['user_name' => $request->contact?->getName()]);
            $presenter->presentResponse(new ErrorResponse($ex->getMessage()));
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->presentResponse(new ErrorResponse($ex->getMessage()));
        }
    }

    /**
     * @param \Iterator<int, Media>&\Countable $medias
     * @param MigrationAllMediasResponse $response
     */
    private function migrateMedias(\Iterator&\Countable $medias, MigrationAllMediasResponse $response): void
    {
        $response->results = new class($medias, $this->writeMediaRepository) implements \Iterator, \Countable {
            /**
             * @param \Iterator<int, Media>&\Countable $medias
             * @param WriteMediaRepositoryInterface $writeMediaRepository
             */
            public function __construct(
                readonly private \Iterator&\Countable $medias,
                readonly private WriteMediaRepositoryInterface $writeMediaRepository,
            ) {
            }

            /**
             * @return MediaRecordedDto|MigrationErrorDto
             */
            public function current(): MediaRecordedDto|MigrationErrorDto
            {
                $media = $this->medias->current();
                try {
                    if ($media->getData() === null) {
                        throw new \Exception(sprintf('The file %s does not exist', $media->getRelativePath()));
                    }
                    $destinationNewMediaId = $this->writeMediaRepository->add(NewMedia::createFromMedia($media));
                    $status = new MediaRecordedDto();
                    $status->id = $destinationNewMediaId;
                    $status->filename = $media->getFilename();
                    $status->directory = $media->getDirectory();
                    $status->md5 = md5($media->getData());

                    return $status;
                } catch (\Throwable $ex) {
                    $status = new MigrationErrorDto();
                    $status->filename = $media->getFilename();
                    $status->directory = $media->getDirectory();
                    $status->reason = $ex->getMessage();

                    return $status;
                }
            }

            public function next(): void
            {
                $this->medias->next();
            }

            public function key(): int
            {
                return $this->medias->key();
            }

            public function valid(): bool
            {
                return $this->medias->key() < $this->medias->count();
            }

            public function rewind(): void
            {
                 $this->medias->rewind();
            }

            public function count(): int
            {
                return $this->medias->count();
            }
        };
    }
}
