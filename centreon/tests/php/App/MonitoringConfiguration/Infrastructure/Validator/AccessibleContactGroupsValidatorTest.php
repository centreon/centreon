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

use App\MonitoringConfiguration\Domain\Aggregate\ContactGroup\ContactGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\ContactGroup\ContactGroupName;
use App\MonitoringConfiguration\Domain\Repository\ContactGroupRepository;
use App\MonitoringConfiguration\Infrastructure\Validator\AccessibleContactGroups;
use App\MonitoringConfiguration\Infrastructure\Validator\AccessibleContactGroupsValidator;
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
 * @extends ConstraintValidatorTestCase<AccessibleContactGroupsValidator>
 */
final class AccessibleContactGroupsValidatorTest extends ConstraintValidatorTestCase
{
    private ContactGroupRepository&MockObject $contactGroupRepository;

    private ResourceAccessRepository&MockObject $resourceAccessRepository;

    private Security&MockObject $security;

    protected function setUp(): void
    {
        $this->contactGroupRepository = $this->createMock(ContactGroupRepository::class);
        $this->resourceAccessRepository = $this->createMock(ResourceAccessRepository::class);
        $this->security = $this->createMock(Security::class);
        parent::setUp();
    }

    public function testAnEmptyArrayRaisesNoViolation(): void
    {
        $this->security->expects(self::never())->method('getUser');

        $this->validator->validate([], new AccessibleContactGroups());

        $this->assertNoViolation();
    }

    public function testAnExistingContactRaisesNoViolationForAnAdmin(): void
    {
        $this->security->method('getUser')->willReturn($this->createCredentialUser(isAdmin: true));
        $this->contactGroupRepository->method('findNamesByIds')->willReturn(
            new Collection([5 => new ContactGroupName('supervisors')], ContactGroupName::class),
        );
        // an admin is never scoped, so the ACL lookup must not even be attempted
        $this->resourceAccessRepository->expects(self::never())->method('findAccessibleContactGroupIds');

        $this->validator->validate([5], new AccessibleContactGroups());

        $this->assertNoViolation();
    }

    public function testAnUnknownContactRaisesAViolation(): void
    {
        $this->security->method('getUser')->willReturn($this->createCredentialUser(isAdmin: true));
        $this->contactGroupRepository->method('findNamesByIds')->willReturn(new Collection([], ContactGroupName::class));

        $constraint = new AccessibleContactGroups();
        $this->validator->validate([404], $constraint);

        $this->buildViolation($constraint->message)->assertRaised();
    }

    /**
     * The whole point of the scoping: an existing contact the viewer cannot reach must be
     * rejected with the very same message as a missing one, so a restricted user cannot tell
     * the two apart and enumerate contacts they have no business seeing.
     */
    public function testARestrictedViewerCannotReferenceAContactGroupOutsideTheirScope(): void
    {
        $this->security->method('getUser')->willReturn($this->createCredentialUser(isAdmin: false));
        $this->contactGroupRepository->method('findNamesByIds')->willReturn(
            new Collection([5 => new ContactGroupName('supervisors')], ContactGroupName::class),
        );
        $this->resourceAccessRepository->method('findAccessibleContactGroupIds')->willReturn(
            new Collection([new ContactGroupId(9)], ContactGroupId::class),
        );

        $constraint = new AccessibleContactGroups();
        $this->validator->validate([5], $constraint);

        $this->buildViolation($constraint->message)->assertRaised();
    }

    public function testARestrictedViewerCanReferenceAnAccessibleContact(): void
    {
        $this->security->method('getUser')->willReturn($this->createCredentialUser(isAdmin: false));
        $this->contactGroupRepository->method('findNamesByIds')->willReturn(
            new Collection([5 => new ContactGroupName('supervisors')], ContactGroupName::class),
        );
        $this->resourceAccessRepository->method('findAccessibleContactGroupIds')->willReturn(
            new Collection([new ContactGroupId(5)], ContactGroupId::class),
        );

        $this->validator->validate([5], new AccessibleContactGroups());

        $this->assertNoViolation();
    }

    /**
     * Contacts have no "unrestricted" case to fall back on (see ResourceAccessRepository): an
     * empty accessible set means the viewer reaches nothing, never everything.
     */
    public function testARestrictedViewerWithNoAccessibleContactIsRejected(): void
    {
        $this->security->method('getUser')->willReturn($this->createCredentialUser(isAdmin: false));
        $this->contactGroupRepository->method('findNamesByIds')->willReturn(
            new Collection([5 => new ContactGroupName('supervisors')], ContactGroupName::class),
        );
        $this->resourceAccessRepository->method('findAccessibleContactGroupIds')->willReturn(
            new Collection([], ContactGroupId::class),
        );

        $constraint = new AccessibleContactGroups();
        $this->validator->validate([5], $constraint);

        $this->buildViolation($constraint->message)->assertRaised();
    }

    protected function createValidator(): ConstraintValidatorInterface
    {
        return new AccessibleContactGroupsValidator($this->security, $this->contactGroupRepository, $this->resourceAccessRepository);
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
