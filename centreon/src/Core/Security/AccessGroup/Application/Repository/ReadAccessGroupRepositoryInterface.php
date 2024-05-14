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

namespace Core\Security\AccessGroup\Application\Repository;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

interface ReadAccessGroupRepositoryInterface
{
    /**
     * Find all access groups.
     *
     * @throws \Throwable
     *
     * @return AccessGroup[]
     */
    public function findAllWithFilter(): array;

    /**
     * Find all access groups according to a contact.
     *
     * @param ContactInterface $contact contact for which we want to find the access groups
     *
     * @throws \Throwable
     *
     * @return AccessGroup[]
     */
    public function findByContact(ContactInterface $contact): array;

    /**
     * Find all access groups according to a contact with filter.
     *
     * @param ContactInterface $contact
     *
     * @throws \Throwable
     *
     * @return AccessGroup[]
     */
    public function findByContactWithFilter(ContactInterface $contact): array;

    /**
     * @param int[] $accessGroupIds
     *
     * @throws \Throwable
     *
     * @return AccessGroup[]
     */
    public function findByIds(array $accessGroupIds): array;

    /**
     * Deterimne if any of the accessGroup are linked to an ACLResourcesGroup.
     *
     * @param int[] $accessGroupIds
     *
     * @throws \Throwable
     *
     * @return bool
     */
    public function hasAccessToResources(array $accessGroupIds): bool;
}
