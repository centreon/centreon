<?php

/*
 * Copyright 2005 - 2022 Centreon (https://www.centreon.com/)
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

interface WriteAccessGroupRepositoryInterface
{
    /**
     * Delete all access groups for a given user.
     *
     * @param ContactInterface $user
     */
    public function deleteAccessGroupsForUser(ContactInterface $user): void;

    /**
     * Insert access groups for a given user.
     *
     * @param ContactInterface $user
     * @param AccessGroup[] $accessGroups
     */
    public function insertAccessGroupsForUser(ContactInterface $user, array $accessGroups): void;

    /**
     * Add links between a "Host Group" and multiple "Acl Groups".
     *
     * @param int $hostGroupId
     * @param AccessGroup[] $accessGroups
     */
    public function addLinksBetweenHostGroupAndAccessGroups(int $hostGroupId, array $accessGroups): void;

    /**
     * Add links between a "Service Group" and multiple "Acl Groups".
     *
     * @param int $serviceGroupId
     * @param AccessGroup[] $accessGroups
     */
    public function addLinksBetweenServiceGroupAndAccessGroups(int $serviceGroupId, array $accessGroups): void;
}
