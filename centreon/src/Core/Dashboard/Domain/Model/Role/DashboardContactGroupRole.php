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

namespace Core\Dashboard\Domain\Model\Role;

class DashboardContactGroupRole
{
    /**
     * @param int $contactGroupId
     * @param string $contactGroupName
     * @param DashboardGlobalRole[] $roles
     */
    public function __construct(
        private readonly int $contactGroupId,
        private readonly string $contactGroupName,
        private readonly array $roles
    ) {
    }

    public function getContactGroupId(): int
    {
        return $this->contactGroupId;
    }

    public function getContactGroupName(): string
    {
        return $this->contactGroupName;
    }

    /**
     * @return DashboardGlobalRole[]
     */
    public function getRoles(): array
    {
        return $this->roles;
    }

    /**
     * Compute the most permissive role,
     * Administrator | Creator are most permissive than Viewer.
     *
     * @return DashboardGlobalRole
     */
    public function getMostPermissiveRole(): DashboardGlobalRole
    {
        return in_array(DashboardGlobalRole::Administrator, $this->roles, true)
            || in_array(DashboardGlobalRole::Creator, $this->roles, true)
                ? DashboardGlobalRole::Creator
                : DashboardGlobalRole::Viewer;
    }
}
