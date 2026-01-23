<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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

namespace App\Security\Domain\Aggregate;

use App\Shared\Domain\Collection;

final readonly class Credential
{
    /** @var Collection<Role> */
    public Collection $roles;

    /** @var Collection<Permission> */
    public Collection $permissions;

    public function __construct(
        public CredentialIdentifier $identifier,
        public UserId $userId,
        public bool $active,
    ) {
        $this->roles = new Collection([], Role::class, static fn (Role $self, Role $other): bool => $self->value === $other->value);
        $this->permissions = new Collection([], Permission::class, static fn (Permission $self, Permission $other): bool => $self->value === $other->value);
    }

    public function assignRole(Role $role): void
    {
        if (! $this->roles->contains($role)) {
            $this->roles->add($role);
        }
    }

    public function revokeRole(Role $role): void
    {
        $this->roles->removeElement($role);
    }

    public function grantPermission(Permission $permission): void
    {
        if (! $this->permissions->contains($permission)) {
            $this->permissions->add($permission);
        }
    }

    public function removePermission(Permission $permission): void
    {
        $this->permissions->removeElement($permission);
    }

    public function isPermissionGranted(Permission $permission): bool
    {
        return $this->permissions->contains($permission);
    }
}
