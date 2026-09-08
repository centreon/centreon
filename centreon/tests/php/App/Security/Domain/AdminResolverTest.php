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

namespace Tests\App\Security\Domain;

use App\Security\Domain\AdminResolver;
use App\Security\Domain\Aggregate\Credential;
use App\Security\Domain\Aggregate\CredentialIdentifier;
use App\Security\Domain\Aggregate\Role;
use App\Security\Domain\Aggregate\UserId;
use PHPUnit\Framework\TestCase;
use Tests\App\Security\Infrastructure\Double\FakeAccessGroupRepository;

final class AdminResolverTest extends TestCase
{
    public function testRealAdminIsUnrestrictedOnPrem(): void
    {
        $credential = $this->createCredential(isAdmin: true);
        $resolver = new AdminResolver(new FakeAccessGroupRepository(), isCloudPlatform: false);

        self::assertTrue($resolver->resolve($credential));
    }

    public function testRealAdminIsUnrestrictedOnCloud(): void
    {
        $credential = $this->createCredential(isAdmin: true);
        $resolver = new AdminResolver(new FakeAccessGroupRepository(), isCloudPlatform: true);

        self::assertTrue($resolver->resolve($credential));
    }

    public function testNonAdminInCustomerAdminAclGroupIsNotUnrestrictedOnPrem(): void
    {
        $credential = $this->createCredential(isAdmin: false);
        $accessGroupRepository = new FakeAccessGroupRepository();
        $accessGroupRepository->groupNamesByUserId[$credential->userId->value] = ['customer_admin_acl'];

        $resolver = new AdminResolver($accessGroupRepository, isCloudPlatform: false);

        self::assertFalse($resolver->resolve($credential));
    }

    public function testNonAdminInCustomerAdminAclGroupIsUnrestrictedOnCloud(): void
    {
        $credential = $this->createCredential(isAdmin: false);
        $accessGroupRepository = new FakeAccessGroupRepository();
        $accessGroupRepository->groupNamesByUserId[$credential->userId->value] = ['customer_admin_acl'];

        $resolver = new AdminResolver($accessGroupRepository, isCloudPlatform: true);

        self::assertTrue($resolver->resolve($credential));
    }

    public function testNonAdminNotInCustomerAdminAclGroupIsNotUnrestrictedOnCloud(): void
    {
        $credential = $this->createCredential(isAdmin: false);
        $resolver = new AdminResolver(new FakeAccessGroupRepository(), isCloudPlatform: true);

        self::assertFalse($resolver->resolve($credential));
    }

    private function createCredential(bool $isAdmin): Credential
    {
        $credential = new Credential(
            identifier: new CredentialIdentifier('jdoe'),
            userId: new UserId(random_int(1, 100_000)),
            active: true,
        );

        if ($isAdmin) {
            $credential->assignRole(new Role('ROLE_ADMIN'));
        }

        return $credential;
    }
}
