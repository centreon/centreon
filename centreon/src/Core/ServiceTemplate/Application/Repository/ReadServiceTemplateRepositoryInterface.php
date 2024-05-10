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

declare(strict_types=1);

namespace Core\ServiceTemplate\Application\Repository;

use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Common\Domain\TrimmedString;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;
use Core\ServiceTemplate\Domain\Model\ServiceTemplate;
use Core\ServiceTemplate\Domain\Model\ServiceTemplateInheritance;

interface ReadServiceTemplateRepositoryInterface
{
    /**
     * Find one service template.
     *
     * @param int $serviceTemplateId
     *
     * @throws \Throwable
     *
     * @return ServiceTemplate|null
     */
    public function findById(int $serviceTemplateId): ?ServiceTemplate;

    /**
     * Find one service template by id and access group ids.
     *
     * @param int $serviceTemplateId
     * @param AccessGroup[] $accessGroups
     *
     * @return ServiceTemplate|null
     */
    public function findByIdAndAccessGroups(int $serviceTemplateId, array $accessGroups): ?ServiceTemplate;

    /**
     * Find all service templates.
     *
     * @param RequestParametersInterface $requestParameters
     *
     * @throws \Throwable
     *
     * @return ServiceTemplate[]
     */
    public function findByRequestParameter(RequestParametersInterface $requestParameters): array;

    /**
     * Find all service tempalte by request parameters and access groups.
     *
     * @param RequestParametersInterface $requestParameters
     * @param AccessGroup[] $accessGroups
     *
     * @throws \Throwable
     *
     * @return ServiceTemplate[]
     */
    public function findByRequestParametersAndAccessGroups(
        RequestParametersInterface $requestParameters,
        array $accessGroups
    ): array;

    /**
     * Indicates whether the service template already exists.
     *
     * @param int $serviceTemplateId
     *
     * @throws \Throwable
     *
     * @return bool
     */
    public function exists(int $serviceTemplateId): bool;

    /**
     * Indicates whether the service template name already exists.
     *
     * @param TrimmedString $serviceTemplateName
     *
     * @throws \Throwable
     *
     * @return bool
     */
    public function existsByName(TrimmedString $serviceTemplateName): bool;

    /**
     * Retrieves all service inheritances from a service template.
     *
     * @param int $serviceTemplateId
     *
     * @throws \Throwable
     *
     * @return ServiceTemplateInheritance[]
     */
    public function findParents(int $serviceTemplateId): array;
}
