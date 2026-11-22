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

namespace Tests\App\AiConfiguration\Infrastructure\Security;

use App\AiConfiguration\Domain\Security\AiProviderPermissionEnum;
use App\AiConfiguration\Infrastructure\Security\AiProviderPermissionVoter;
use App\Security\Domain\Aggregate\Credential;
use App\Security\Domain\Aggregate\CredentialIdentifier;
use App\Security\Domain\Aggregate\Permission;
use App\Security\Domain\Aggregate\Role;
use App\Security\Domain\Aggregate\UserId;
use App\Security\Infrastructure\Security\CredentialUser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class AiProviderPermissionVoterTest extends TestCase
{
    private const READ = AiProviderPermissionEnum::CanRead->value;
    private const READ_WRITE = AiProviderPermissionEnum::CanReadAndWrite->value;

    public function testAbstainsOnAnUnrelatedAttribute(): void
    {
        self::assertSame(
            VoterInterface::ACCESS_ABSTAIN,
            (new AiProviderPermissionVoter())->vote($this->tokenFor($this->credential()), null, ['some_other_attribute'])
        );
    }

    public function testGrantsTheReadPermission(): void
    {
        $credential = $this->credential();
        $credential->grantPermission(new Permission(self::READ));

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            (new AiProviderPermissionVoter())->vote($this->tokenFor($credential), null, [self::READ])
        );
    }

    public function testGrantsTheReadWritePermission(): void
    {
        $credential = $this->credential();
        $credential->grantPermission(new Permission(self::READ_WRITE));

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            (new AiProviderPermissionVoter())->vote($this->tokenFor($credential), null, [self::READ_WRITE])
        );
    }

    public function testDeniesWithoutThePermission(): void
    {
        // read-only is a distinct permission: it does not satisfy the read-write attribute
        $credential = $this->credential();
        $credential->grantPermission(new Permission(self::READ));

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            (new AiProviderPermissionVoter())->vote($this->tokenFor($credential), null, [self::READ_WRITE])
        );
    }

    public function testDeniesWhenNotAuthenticatedAsACredentialUser(): void
    {
        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            (new AiProviderPermissionVoter())->vote(new NullToken(), null, [self::READ])
        );
    }

    public function testGrantsASuperAdmin(): void
    {
        $credential = $this->credential();
        $credential->assignRole(new Role('ROLE_SUPER_ADMIN'));

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            (new AiProviderPermissionVoter())->vote($this->tokenFor($credential), null, [self::READ_WRITE])
        );
    }

    private function credential(): Credential
    {
        return new Credential(
            identifier: new CredentialIdentifier('editor'),
            userId: new UserId(1),
            active: true,
        );
    }

    private function tokenFor(Credential $credential): UsernamePasswordToken
    {
        return new UsernamePasswordToken(new CredentialUser($credential), 'main');
    }
}
