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

enum DashboardSharingRole
{
    case Viewer;
    case Editor;

    /**
     * Simple Role comparison to check the role which has the most permissions.
     *
     * @param DashboardSharingRole|null $role
     *
     * @return bool
     */
    public function hasMorePermissionsThan(?self $role): bool
    {
        if (null === $role) {
            return true;
        }

        return $this === self::Editor
            && $role === self::Viewer;
    }

    /**
     * Helper to get the most permissive role between self and the argument.
     *
     * @param DashboardSharingRole|null $role
     *
     * @return self
     */
    public function getTheMostPermissiveOfBoth(?self $role): self
    {
        if (null === $role) {
            return $this;
        }

        return $this->hasMorePermissionsThan($role) ? $this : $role;
    }
}
