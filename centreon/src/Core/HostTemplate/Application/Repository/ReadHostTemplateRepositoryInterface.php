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

namespace Core\HostTemplate\Application\Repository;

use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\HostTemplate\Domain\Model\HostTemplate;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

interface ReadHostTemplateRepositoryInterface
{
    /**
     * Find all host templates.
     *
     * @param RequestParametersInterface $requestParameters
     *
     * @throws \Throwable
     *
     * @return HostTemplate[]
     */
    public function findByRequestParameter(RequestParametersInterface $requestParameters): array;

    /**
     * Find all host templates by access groups.
     *
     * @param RequestParametersInterface $requestParameters
     * @param AccessGroup[] $accessGroups
     *
     * @throws \Throwable
     *
     * @return HostTemplate[]
     */
    public function findByRequestParametersAndAccessGroups(RequestParametersInterface $requestParameters, array $accessGroups): array;

    /**
     * Find a host template by its id.
     *
     * @param int $hostTemplateId
     *
     * @throws \Throwable
     *
     * @return ?HostTemplate
     */
    public function findById(int $hostTemplateId): ?HostTemplate;

    /**
     * Find a host template by id and access groups.
     *
     * @param int $hostTemplateId
     * @param AccessGroup[] $accessGroups
     *
     * @throws \Throwable
     *
     * @return HostTemplate|null
     */
    public function findByIdAndAccessGroups(int $hostTemplateId, array $accessGroups): ?HostTemplate;

    /**
     * Find a host template by its id.
     *
     * @param int ...$hostTemplateIds
     *
     * @throws \Throwable
     *
     * @return list<HostTemplate>
     */
    public function findByIds(int ...$hostTemplateIds): array;

    /**
     * Retrieve all parent template ids of a host template.
     *
     * @param int $hostTemplateId
     *
     * @throws \Throwable
     *
     * @return array<array{parent_id:int,child_id:int,order:int}>
     */
    public function findParents(int $hostTemplateId): array;

    /**
     * Find all existing host templates ids.
     *
     * @param list<int> $hostTemplateIds
     *
     * @return list<int>
     */
    public function findAllExistingIds(array $hostTemplateIds): array;

    /**
     * Determine if a host template exists by its ID.
     *
     * @param int $hostTemplateId
     *
     * @throws \Throwable
     *
     * @return bool
     */
    public function exists(int $hostTemplateId): bool;

    /**
     * Check existence of a list of host templates.
     * Return the ids of the existing templates.
     *
     * @param int[] $hostTemplateIds
     *
     * @throws \Throwable
     *
     * @return int[]
     */
    public function exist(array $hostTemplateIds): array;

    /**
     * Determine if a host template exists by its name.
     * (include both host templates and hosts names).
     *
     * @param string $hostTemplateName
     *
     * @throws \Throwable
     *
     * @return bool
     */
    public function existsByName(string $hostTemplateName): bool;

    /**
     * Determine if a host template is_locked properties is set at true.
     * It means edition and suppression is not allowed for the host template.
     *
     * @param int $hostTemplateId
     *
     * @throws \Throwable
     *
     * @return bool
     */
    public function isLocked(int $hostTemplateId): bool;

    /**
     * Retrieve the name of a list of template ids.
     * Return an array with ids as keys.
     *
     * @param int[] $templateIds
     *
     * @throws \Throwable
     *
     * @return array<int,string>
     */
    public function findNamesByIds(array $templateIds): array;
}
