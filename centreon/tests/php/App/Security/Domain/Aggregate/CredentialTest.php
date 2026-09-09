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

namespace Tests\App\Security\Domain\Aggregate;

use App\Security\Domain\Aggregate\Credential;
use App\Security\Domain\Aggregate\CredentialIdentifier;
use App\Security\Domain\Aggregate\Permission;
use App\Security\Domain\Aggregate\Role;
use App\Security\Domain\Aggregate\UserId;
use PHPUnit\Framework\TestCase;

final class CredentialTest extends TestCase
{
    public function testNoAdminRoleByDefault(): void
    {
        $credential = $this->createCredential();

        self::assertFalse($credential->isSuperAdmin());
        self::assertFalse($credential->isCloudAdmin());
        self::assertFalse($credential->hasUnrestrictedResourceAccess());
    }

    public function testSuperAdminIsAdminButNotCloudAdmin(): void
    {
        $credential = $this->createCredential();
        $credential->assignRole(new Role('ROLE_SUPER_ADMIN'));

        self::assertTrue($credential->isSuperAdmin());
        self::assertFalse($credential->isCloudAdmin());
        self::assertTrue($credential->hasUnrestrictedResourceAccess());
    }

    public function testCloudAdminIsAdminButNotSuperAdmin(): void
    {
        $credential = $this->createCredential();
        $credential->assignRole(new Role('ROLE_CLOUD_ADMIN'));

        self::assertFalse($credential->isSuperAdmin());
        self::assertTrue($credential->isCloudAdmin());
        self::assertTrue($credential->hasUnrestrictedResourceAccess());
    }

    public function testSuperAdminAndCloudAdminCanBeHeldTogether(): void
    {
        $credential = $this->createCredential();
        $credential->assignRole(new Role('ROLE_SUPER_ADMIN'));
        $credential->assignRole(new Role('ROLE_CLOUD_ADMIN'));

        self::assertTrue($credential->isSuperAdmin());
        self::assertTrue($credential->isCloudAdmin());
        self::assertTrue($credential->hasUnrestrictedResourceAccess());
    }

    public function testAnyOtherRoleGrantsNoAdminship(): void
    {
        $credential = $this->createCredential();
        $credential->assignRole(new Role('ROLE_USER'));

        self::assertFalse($credential->isSuperAdmin());
        self::assertFalse($credential->isCloudAdmin());
        self::assertFalse($credential->hasUnrestrictedResourceAccess());
    }

    public function testRevokingAnAdminRoleTakesAdminshipAway(): void
    {
        $credential = $this->createCredential();
        $credential->assignRole(new Role('ROLE_CLOUD_ADMIN'));
        $credential->revokeRole(new Role('ROLE_CLOUD_ADMIN'));

        self::assertFalse($credential->isCloudAdmin());
        self::assertFalse($credential->hasUnrestrictedResourceAccess());
    }

    public function testOnlyExplicitlyGrantedPermissionsAreGrantedToARestrictedUser(): void
    {
        $credential = $this->createCredential();
        $credential->grantPermission(new Permission('can_read_host_groups'));

        self::assertTrue($credential->isPermissionGranted(new Permission('can_read_host_groups')));
        self::assertFalse($credential->isPermissionGranted(new Permission('can_write_host_groups')));
    }

    public function testARestrictedUserLosesARemovedPermission(): void
    {
        $credential = $this->createCredential();
        $credential->grantPermission(new Permission('can_read_host_groups'));
        $credential->removePermission(new Permission('can_read_host_groups'));

        self::assertFalse($credential->isPermissionGranted(new Permission('can_read_host_groups')));
    }

    /**
     * A super admin bypasses every voter, so this must hold for permissions never granted.
     */
    public function testASuperAdminIsGrantedEveryPermission(): void
    {
        $credential = $this->createCredential();
        $credential->assignRole(new Role('ROLE_SUPER_ADMIN'));

        self::assertTrue($credential->isPermissionGranted(new Permission('can_read_host_groups')));
        self::assertTrue($credential->isPermissionGranted(new Permission('any_permission_never_granted')));
    }

    /**
     * Removing a permission cannot restrict a super admin: adminship wins.
     */
    public function testRemovingAPermissionDoesNotRestrictASuperAdmin(): void
    {
        $credential = $this->createCredential();
        $credential->assignRole(new Role('ROLE_SUPER_ADMIN'));
        $credential->grantPermission(new Permission('can_read_host_groups'));
        $credential->removePermission(new Permission('can_read_host_groups'));

        self::assertTrue($credential->isPermissionGranted(new Permission('can_read_host_groups')));
    }

    /**
     * A Cloud admin escapes resource scoping but keeps ACL-governed menu access — it is
     * granted exactly what it was granted, like any other user.
     */
    public function testACloudAdminIsGrantedOnlyItsOwnPermissions(): void
    {
        $credential = $this->createCredential();
        $credential->assignRole(new Role('ROLE_CLOUD_ADMIN'));
        $credential->grantPermission(new Permission('can_read_host_groups'));

        self::assertTrue($credential->hasUnrestrictedResourceAccess());
        self::assertTrue($credential->isPermissionGranted(new Permission('can_read_host_groups')));
        self::assertFalse($credential->isPermissionGranted(new Permission('can_write_host_groups')));
    }

    public function testLosingAdminshipRestoresPermissionScoping(): void
    {
        $credential = $this->createCredential();
        $credential->assignRole(new Role('ROLE_SUPER_ADMIN'));
        $credential->revokeRole(new Role('ROLE_SUPER_ADMIN'));

        self::assertFalse($credential->isPermissionGranted(new Permission('can_read_host_groups')));
    }

    private function createCredential(): Credential
    {
        return new Credential(new CredentialIdentifier('user'), new UserId(1), active: true);
    }
}
