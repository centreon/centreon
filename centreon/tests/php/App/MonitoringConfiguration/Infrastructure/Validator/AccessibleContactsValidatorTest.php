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

namespace Tests\App\MonitoringConfiguration\Infrastructure\Validator;

use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactId;
use App\MonitoringConfiguration\Domain\Aggregate\NotificationContact\NotificationContactName;
use App\MonitoringConfiguration\Domain\Repository\NotificationContactRepository;
use App\MonitoringConfiguration\Infrastructure\Validator\AccessibleContacts;
use App\MonitoringConfiguration\Infrastructure\Validator\AccessibleContactsValidator;
use App\Security\Domain\Aggregate\Credential;
use App\Security\Domain\Aggregate\CredentialIdentifier;
use App\Security\Domain\Aggregate\Role;
use App\Security\Domain\Aggregate\UserId;
use App\Security\Domain\Repository\ResourceAccessRepository;
use App\Security\Infrastructure\Security\CredentialUser;
use App\Shared\Domain\Collection;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<AccessibleContactsValidator>
 */
final class AccessibleContactsValidatorTest extends ConstraintValidatorTestCase
{
    private NotificationContactRepository&MockObject $contactRepository;

    private ResourceAccessRepository&MockObject $resourceAccessRepository;

    private Security&MockObject $security;

    protected function setUp(): void
    {
        $this->contactRepository = $this->createMock(NotificationContactRepository::class);
        $this->resourceAccessRepository = $this->createMock(ResourceAccessRepository::class);
        $this->security = $this->createMock(Security::class);
        parent::setUp();
    }

    public function testAnEmptyArrayRaisesNoViolation(): void
    {
        $this->security->expects(self::never())->method('getUser');

        $this->validator->validate([], new AccessibleContacts());

        $this->assertNoViolation();
    }

    public function testAnExistingContactRaisesNoViolationForAnAdmin(): void
    {
        $this->security->method('getUser')->willReturn($this->createCredentialUser(isAdmin: true));
        $this->contactRepository->method('findNamesByIds')->willReturn(
            new Collection([5 => new NotificationContactName('on-call')], NotificationContactName::class),
        );
        // an admin is never scoped, so the ACL lookup must not even be attempted
        $this->resourceAccessRepository->expects(self::never())->method('findAccessibleContactIds');

        $this->validator->validate([5], new AccessibleContacts());

        $this->assertNoViolation();
    }

    public function testAnUnknownContactRaisesAViolation(): void
    {
        $this->security->method('getUser')->willReturn($this->createCredentialUser(isAdmin: true));
        $this->contactRepository->method('findNamesByIds')->willReturn(new Collection([], NotificationContactName::class));

        $constraint = new AccessibleContacts();
        $this->validator->validate([404], $constraint);

        $this->buildViolation($constraint->message)->assertRaised();
    }

    /**
     * The whole point of the scoping: an existing contact the viewer cannot reach must be
     * rejected with the very same message as a missing one, so a restricted user cannot tell
     * the two apart and enumerate contacts they have no business seeing.
     */
    public function testARestrictedViewerCannotReferenceAContactOutsideTheirScope(): void
    {
        $this->security->method('getUser')->willReturn($this->createCredentialUser(isAdmin: false));
        $this->contactRepository->method('findNamesByIds')->willReturn(
            new Collection([5 => new NotificationContactName('on-call')], NotificationContactName::class),
        );
        $this->resourceAccessRepository->method('findAccessibleContactIds')->willReturn(
            new Collection([new NotificationContactId(9)], NotificationContactId::class),
        );

        $constraint = new AccessibleContacts();
        $this->validator->validate([5], $constraint);

        $this->buildViolation($constraint->message)->assertRaised();
    }

    public function testARestrictedViewerCanReferenceAnAccessibleContact(): void
    {
        $this->security->method('getUser')->willReturn($this->createCredentialUser(isAdmin: false));
        $this->contactRepository->method('findNamesByIds')->willReturn(
            new Collection([5 => new NotificationContactName('on-call')], NotificationContactName::class),
        );
        $this->resourceAccessRepository->method('findAccessibleContactIds')->willReturn(
            new Collection([new NotificationContactId(5)], NotificationContactId::class),
        );

        $this->validator->validate([5], new AccessibleContacts());

        $this->assertNoViolation();
    }

    /**
     * Contacts have no "unrestricted" case to fall back on (see ResourceAccessRepository): an
     * empty accessible set means the viewer reaches nothing, never everything.
     */
    public function testARestrictedViewerWithNoAccessibleContactIsRejected(): void
    {
        $this->security->method('getUser')->willReturn($this->createCredentialUser(isAdmin: false));
        $this->contactRepository->method('findNamesByIds')->willReturn(
            new Collection([5 => new NotificationContactName('on-call')], NotificationContactName::class),
        );
        $this->resourceAccessRepository->method('findAccessibleContactIds')->willReturn(
            new Collection([], NotificationContactId::class),
        );

        $constraint = new AccessibleContacts();
        $this->validator->validate([5], $constraint);

        $this->buildViolation($constraint->message)->assertRaised();
    }

    protected function createValidator(): ConstraintValidatorInterface
    {
        return new AccessibleContactsValidator($this->security, $this->contactRepository, $this->resourceAccessRepository);
    }

    private function createCredentialUser(bool $isAdmin): CredentialUser
    {
        $credential = new Credential(new CredentialIdentifier('user'), new UserId(7), active: true);
        if ($isAdmin) {
            $credential->assignRole(new Role('ROLE_SUPER_ADMIN'));
        }

        return new CredentialUser($credential);
    }
}
