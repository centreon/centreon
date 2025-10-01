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
use Core\Common\Domain\Exception\RepositoryException;
use Core\Media\Application\Repository\ReadImageFolderRepositoryInterface;
use Core\Media\Domain\Model\ImageFolder\ImageFolder;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

final readonly class FindImageFolders
{
    public function __construct(
        private ContactInterface $user,
        private RequestParametersInterface $requestParameters,
        private ReadAccessGroupRepositoryInterface $accessGroupReader,
        private ReadImageFolderRepositoryInterface $imageFolderReader,
    ) {
    }

    /**
     * @throws RepositoryException
     * @return FindImageFoldersResponse
     */
    public function __invoke(): FindImageFoldersResponse
    {
        return new FindImageFoldersResponse(
            $this->user->isAdmin() ? $this->findAsAdmin() : $this->findAsUser()
        );
    }

    /**
     * @throws RepositoryException
     *
     * @return array<ImageFolder>
     */
    private function findAsAdmin(): array
    {
        return $this->imageFolderReader->findByRequestParameters($this->requestParameters);
    }

    /**
     * @throws RepositoryException
     *
     * @return array<ImageFolder>
     */
    private function findAsUser(): array
    {
        $accessGroups = $this->accessGroupReader->findByContact($this->user);

        return $this->imageFolderReader->hasAccessToAllImageFolders($accessGroups)
            ? $this->imageFolderReader->findByRequestParameters($this->requestParameters)
            : $this->imageFolderReader->findByRequestParametersAndAccessGroups(
                $this->requestParameters,
                $accessGroups
            );
    }
}
