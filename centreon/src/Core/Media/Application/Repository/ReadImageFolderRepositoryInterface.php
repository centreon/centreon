<?php

/*
 * Copyright 2005 - 2026 Centreon (https://www.centreon.com/)
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

namespace Core\Media\Application\Repository;

use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Common\Domain\Exception\RepositoryException;
use Core\Media\Domain\Model\ImageFolder\ImageFolder;
use Core\ResourceAccess\Domain\Model\DatasetFilter\ResourceNamesById;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

interface ReadImageFolderRepositoryInterface
{
    /**
     * @param int[] $folderIds
     *
     * @throws RepositoryException
     *
     * @return int[]
     */
    public function findExistingFolderIds(array $folderIds): array;

    /**
     * Method dedicated and specific to Resource Access Management
     *
     * @param int[] $folderIds
     *
     * @throws RepositoryException
     *
     * @return ResourceNamesById
     */
    public function findFolderNames(array $folderIds): ResourceNamesById;

    /**
     * @param RequestParametersInterface $requestParameters
     *
     * @throws RepositoryException
     *
     * @return ImageFolder[]
     */
    public function findByRequestParameters(RequestParametersInterface $requestParameters): array;

    /**
     * @param RequestParametersInterface $requestParameters
     * @param AccessGroup[] $accessGroups
     *
     * @throws RepositoryException
     *
     * @return ImageFolder[]
     */
    public function findByRequestParametersAndAccessGroups(
        RequestParametersInterface $requestParameters,
        array $accessGroups,
    ): array;

    /**
     *  Checks if the user through the AccessGroups has at least one Resource Access
     *  giving access to all media folders
     *
     * @param AccessGroup[] $accessGroups
     *
     * @throws RepositoryException
     *
     * @return bool
     */
    public function hasAccessToAllImageFolders(array $accessGroups): bool;
}
