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

namespace Tests\App\MonitoringConfiguration\Infrastructure\Security\ContactGroup;

use App\MonitoringConfiguration\Domain\Security\ContactGroup\ContactGroupPermissionEnum;
use App\MonitoringConfiguration\Infrastructure\Security\ContactGroup\ContactGroupPermissionVoter;
use App\Security\Domain\Aggregate\Credential;
use App\Security\Domain\Aggregate\CredentialIdentifier;
use App\Security\Domain\Aggregate\Permission;
use App\Security\Domain\Aggregate\UserId;
use App\Security\Infrastructure\Security\CredentialUser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class ContactGroupPermissionVoterTest extends TestCase
{
    private const READ = ContactGroupPermissionEnum::CanRead->value;

    public function testAbstainsOnAnUnrelatedAttribute(): void
    {
        $voter = new ContactGroupPermissionVoter();

        self::assertSame(
            VoterInterface::ACCESS_ABSTAIN,
            $voter->vote($this->tokenFor($this->credentialWithoutPermission()), null, ['some_other_attribute'])
        );
    }

    public function testDeniesWhenNotAuthenticatedAsACredentialUser(): void
    {
        $voter = new ContactGroupPermissionVoter();

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote(new NullToken(), null, [self::READ])
        );
    }

    public function testOnPremDeniesWithoutThePermission(): void
    {
        $voter = new ContactGroupPermissionVoter(isCloudPlatform: false);

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($this->tokenFor($this->credentialWithoutPermission()), null, [self::READ])
        );
    }

    public function testOnPremGrantsWithThePermission(): void
    {
        $voter = new ContactGroupPermissionVoter(isCloudPlatform: false);
        $credential = $this->credentialWithoutPermission();
        $credential->grantPermission(new Permission(self::READ));

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->tokenFor($credential), null, [self::READ])
        );
    }

    public function testCloudGrantsToAnyAuthenticatedUserWithoutThePermission(): void
    {
        // On Cloud the contact-group topology permission is never carried, yet any authenticated
        // user may read (row-level scoping still happens in the provider).
        $voter = new ContactGroupPermissionVoter(isCloudPlatform: true);

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->tokenFor($this->credentialWithoutPermission()), null, [self::READ])
        );
    }

    private function credentialWithoutPermission(): Credential
    {
        return new Credential(
            identifier: new CredentialIdentifier('viewer'),
            userId: new UserId(1),
            active: true,
        );
    }

    private function tokenFor(Credential $credential): UsernamePasswordToken
    {
        return new UsernamePasswordToken(new CredentialUser($credential), 'main');
    }
}
