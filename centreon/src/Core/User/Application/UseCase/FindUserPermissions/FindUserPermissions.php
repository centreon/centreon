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

declare(strict_types = 1);

namespace Core\User\Application\UseCase\FindUserPermissions;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Common\Domain\NotEmptyString;
use Core\User\Domain\Model\Permission;

final class FindUserPermissions
{
    /** @var array<string, string> */
    private array $permissions = [
        'top_counter' => Contact::ROLE_DISPLAY_TOP_COUNTER,
        'poller_statistics' => Contact::ROLE_DISPLAY_TOP_COUNTER_POLLERS_STATISTICS,
        'configuration_host_group_write' => Contact::ROLE_CONFIGURATION_HOSTS_HOST_GROUPS_READ_WRITE,
    ];

    public function __invoke(ContactInterface $user): FindUserPermissionsResponse|ResponseStatusInterface
    {
        return new FindUserPermissionsResponse($this->createPermissionsToReturn($user));
    }

    /**
     * @param ContactInterface $user
     *
     * @return Permission[]
     */
    private function createPermissionsToReturn(ContactInterface $user): array
    {
        $permissions = [];
        foreach ($this->permissions as $permissionName => $role) {
            if ($user->isAdmin() || $user->hasRole($role) || $user->hasTopologyRole($role)) {
                $permissions[] = new Permission(new NotEmptyString($permissionName), true);
            }
        }

        return $permissions;
    }
}
