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

namespace Core\Media\Application\UseCase\FindMedias;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Common\Domain\Exception\RepositoryException;
use Core\Media\Application\Exception\MediaException;
use Core\Media\Application\Repository\ReadImageFolderRepositoryInterface;
use Core\Media\Application\Repository\ReadMediaRepositoryInterface;
use Core\Media\Domain\Model\Media;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

final readonly class FindMedias
{
    public function __construct(
        private RequestParametersInterface $requestParameters,
        private ReadMediaRepositoryInterface $mediaReader,
        private ReadAccessGroupRepositoryInterface $accessGroupReader,
        private ReadImageFolderRepositoryInterface $mediaFolderReader,
        private ContactInterface $user,
    ) {
    }

    /**
     * @param FindMediasPresenterInterface $presenter
     */
    public function __invoke(FindMediasPresenterInterface $presenter): void
    {
        try {
            $medias = $this->user->isAdmin() ? $this->findAsAdmin() : $this->findAsUser();
            $presenter->presentResponse($this->createResponse($medias));
        } catch (\Exception $ex) {
            $presenter->presentResponse(
                new ErrorResponse(
                    message: MediaException::errorWhileSearchingForMedias(),
                    context: [
                        'user_id' => $this->user->getId(),
                        'request_parameters' => $this->requestParameters->toArray(),
                    ],
                    exception: $ex
                )
            );
        }
    }

    /**
     * @throws RepositoryException
     *
     * @return \Traversable<int, Media>
     */
    private function findAsAdmin(): \Traversable
    {
        return $this->mediaReader->findByRequestParameters($this->requestParameters);
    }

    /**
     * @throws RepositoryException
     *
     * @return \Traversable<int, Media>
     */
    private function findAsUser(): \Traversable
    {
        $accessGroups = $this->accessGroupReader->findByContact($this->user);

        if ($this->mediaFolderReader->hasAccessToAllImageFolders($accessGroups)) {
            return $this->mediaReader->findByRequestParameters($this->requestParameters);
        }

        return $this->mediaReader->findByRequestParametersAndAccessGroups($this->requestParameters, $accessGroups);
    }

    /**
     * @param \Traversable<int, Media> $medias
     *
     * @return FindMediasResponse
     */
    private function createResponse(\Traversable $medias): FindMediasResponse
    {
        $response = new FindMediasResponse();
        foreach ($medias as $media) {
            $dto = new MediaDto();
            $dto->id = $media->getId();
            $dto->filename = $media->getFilename();
            $dto->directory = $media->getDirectory();
            $dto->md5 = $media->hash();
            $response->medias[] = $dto;
        }

        return $response;
    }
}
