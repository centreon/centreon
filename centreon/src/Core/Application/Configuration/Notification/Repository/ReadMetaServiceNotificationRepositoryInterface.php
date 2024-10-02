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

interface ReadMetaServiceNotificationRepositoryInterface
{
    /**
     * @param int $metaServiceId
     *
     * @throws \Throwable
     *
     * @return NotifiedContact[]
     */
    public function findNotifiedContactsById(int $metaServiceId): array;

    /**
     * @param int $metaServiceId
     * @param AccessGroup[] $accessGroups
     *
     * @throws \Throwable
     *
     * @return NotifiedContact[]
     */
    public function findNotifiedContactsByIdAndAccessGroups(int $metaServiceId, array $accessGroups): array;

    /**
     * @param int $metaServiceId
     *
     * @throws \Throwable
     *
     * @return NotifiedContactGroup[]
     */
    public function findNotifiedContactGroupsById(int $metaServiceId): array;

    /**
     * @param int $metaServiceId
     * @param AccessGroup[] $accessGroups
     *
     * @throws \Throwable
     *
     * @return NotifiedContactGroup[]
     */
    public function findNotifiedContactGroupsByIdAndAccessGroups(int $metaServiceId, array $accessGroups): array;
}
