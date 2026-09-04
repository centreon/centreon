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
use App\Security\Domain\Aggregate\Role;
use App\Security\Domain\Aggregate\UserId;
use PHPUnit\Framework\TestCase;

final class CredentialTest extends TestCase
{
    public function testIsAdminReturnsFalseByDefault(): void
    {
        $credential = new Credential(new CredentialIdentifier('user'), new UserId(1), active: true);

        self::assertFalse($credential->isAdmin());
    }

    public function testIsAdminReturnsTrueAfterRoleAdminIsAssigned(): void
    {
        $credential = new Credential(new CredentialIdentifier('user'), new UserId(1), active: true);
        $credential->assignRole(new Role('ROLE_ADMIN'));

        self::assertTrue($credential->isAdmin());
    }

    public function testIsAdminReturnsFalseForAnyOtherRole(): void
    {
        $credential = new Credential(new CredentialIdentifier('user'), new UserId(1), active: true);
        $credential->assignRole(new Role('ROLE_USER'));

        self::assertFalse($credential->isAdmin());
    }
}
