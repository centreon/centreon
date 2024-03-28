<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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

namespace Core\Severity\RealTime\Application\Repository;

use Core\Security\AccessGroup\Domain\Model\AccessGroup;
use Core\Severity\RealTime\Domain\Model\Severity;

interface ReadSeverityRepositoryInterface
{
    /**
     * Returns all the severities from the RealTime of provided type id (without ACls).
     *
     * @param int $typeId
     *
     * @throws \Throwable
     *
     * @return Severity[]
     */
    public function findAllByTypeId(int $typeId): array;

    /**
     * Returns all the severities from the RealTime of provided type id (with ACLs).
     *
     * @param int $typeId
     * @param AccessGroup[] $accessGroups
     *
     * @throws \Throwable
     *
     * @return Severity[]
     */
    public function findAllByTypeIdAndAccessGroups(int $typeId, array $accessGroups): array;

    /**
     * Finds a Severity by id, parentId and typeId.
     *
     * @param int $resourceId
     * @param int $parentResourceId
     * @param int $typeId
     *
     * @throws \Throwable
     *
     * @return Severity|null
     */
    public function findByResourceAndTypeId(int $resourceId, int $parentResourceId, int $typeId): ?Severity;
}
