<?php

/*
* Copyright 2005 - 2022 Centreon (https://www.centreon.com/)
*
* Licensed under the Apache License, Version 2.0 (the "License");
* you may not use this file except in compliance with the License.
* You may obtain a copy of the License at
*
* http://www.apache.org/licenses/LICENSE-2.0
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

namespace Core\HostCategory\Application\Repository;

use Core\HostCategory\Domain\Model\Host;
use Core\HostCategory\Domain\Model\HostCategory;
use Core\HostCategory\Domain\Model\HostTemplate;

interface ReadHostCategoryRepositoryInterface
{
    /**
     * Find all host categories
     *
     * @return HostCategory[]
     */
    public function findAll(): array;

    /**
     * Find all host categories by contactId
     *
     * @param int $contactId
     * @return HostCategory[]
     */
    public function findAllByContactId(int $contactId): array;

    /**
     * @param int[] $hostCategoryIds
     * @return array<int,Host[]>
     */
    public function findHostsByHostCategoryIds(array $hostCategoryIds): array;

    /**
     * @param int[] $hostCategoryIds
     * @param int $contactId
     * @return array<int,Host[]>
     */
    public function findHostsByHostCategoryIdsAndContactId(array $hostCategoryIds, int $contactId): array;

    /**
     * @param int[] $hostCategoryIds
     * @return array<int,HostTemplate[]>
     */
    public function findHostTemplatesByHostCategoryIds(array $hostCategoryIds): array;
}
