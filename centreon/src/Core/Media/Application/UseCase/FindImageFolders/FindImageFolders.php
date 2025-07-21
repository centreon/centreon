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

namespace Core\Media\Application\UseCase\FindImageFolders;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Media\Application\Repository\ReadImageFolderRepositoryInterface;
use Core\Media\Domain\Model\ImageFolder\ImageFolder;
use Core\Media\Infrastructure\API\FindImageFolders\FindImageFoldersResponse;
use Core\Media\Infrastructure\API\FindImageFolders\ImageFolderDto;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

final readonly class FindImageFolders
{
    public function __construct(
        private ContactInterface $user,
        private RequestParametersInterface $requestParameters,
        private ReadAccessGroupRepositoryInterface $accessGroupReader,
        private ReadImageFolderRepositoryInterface $imageFolderReader
    ) {
    }

    public function __invoke(): FindImageFoldersResponse
    {
        return $this->user->isAdmin() ? $this->findAsAdmin() : $this->findAsUser();
    }

    private function findAsAdmin(): FindImageFoldersResponse
    {
        $folders = $this->imageFolderReader->findByRequestParameters($this->requestParameters);

        return $this->createResponse($folders);
    }

    private function findAsUser(): FindImageFoldersResponse
    {
        $accessGroups = $this->accessGroupReader->findByContact($this->user);

        $folders = $this->imageFolderReader->hasAccessToAllImageFolders($accessGroups)
            ? $this->imageFolderReader->findByRequestParameters($this->requestParameters)
            : $this->imageFolderReader->findByRequestParametersAndAccessGroups(
                $this->requestParameters,
                $accessGroups
            );

        return $this->createResponse($folders);
    }

    /**
     * @param ImageFolder[] $folders
     * @return FindImageFoldersResponse
     */
    private function createResponse(array $folders): FindImageFoldersResponse
    {
        $response = new FindImageFoldersResponse();
        foreach ($folders as $folder) {
            $dto = new ImageFolderDto();
            $dto->id = $folder->id()->value;
            $dto->name = $folder->name()->value;
            $dto->alias = $folder->alias()?->value;
            $dto->comment = $folder->description()?->value;

            $response->folders[] = $dto;
        }

        return $response;
    }
}
