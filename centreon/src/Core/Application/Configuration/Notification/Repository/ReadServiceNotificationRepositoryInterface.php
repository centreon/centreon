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

namespace Core\Application\Configuration\Notification\Repository;

use Core\Domain\Configuration\Notification\Model\NotifiedContact;
use Core\Domain\Configuration\Notification\Model\NotifiedContactGroup;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

interface ReadServiceNotificationRepositoryInterface
{
    /**
     * @param int $hostId
     * @param int $serviceId
     *
     * @return NotifiedContact[]
     */
    public function findNotifiedContactsById(int $hostId, int $serviceId): array;

    /**
     * @param int $hostId
     * @param int $serviceId
     * @param AccessGroup[] $accessGroups
     *
     * @return NotifiedContact[]
     */
    public function findNotifiedContactsByIdAndAccessGroups(int $hostId, int $serviceId, array $accessGroups): array;

    /**
     * @param int $hostId
     * @param int $serviceId
     *
     * @return NotifiedContactGroup[]
     */
    public function findNotifiedContactGroupsById(int $hostId, int $serviceId): array;

    /**
     * @param int $hostId
     * @param int $serviceId
     * @param AccessGroup[] $accessGroups
     *
     * @return NotifiedContactGroup[]
     */
    public function findNotifiedContactGroupsByIdAndAccessGroups(int $hostId, int $serviceId, array $accessGroups): array;
}
