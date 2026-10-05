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

use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupId;
use App\MonitoringConfiguration\Domain\Aggregate\HostGroup\HostGroupName;
use App\MonitoringConfiguration\Domain\Repository\HostGroupRepository;
use App\MonitoringConfiguration\Infrastructure\Validator\AccessibleHostGroups;
use App\MonitoringConfiguration\Infrastructure\Validator\AccessibleHostGroupsValidator;
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
 * @extends ConstraintValidatorTestCase<AccessibleHostGroupsValidator>
 */
final class AccessibleHostGroupsValidatorTest extends ConstraintValidatorTestCase
{
    private HostGroupRepository&MockObject $hostGroupRepository;

    private ResourceAccessRepository&MockObject $resourceAccessRepository;

    private Security&MockObject $security;

    protected function setUp(): void
    {
        $this->hostGroupRepository = $this->createMock(HostGroupRepository::class);
        $this->resourceAccessRepository = $this->createMock(ResourceAccessRepository::class);
        $this->security = $this->createMock(Security::class);
        parent::setUp();
    }

    public function testAnEmptyArrayRaisesNoViolation(): void
    {
        $this->security->expects(self::never())->method('getUser');

        $this->validator->validate([], new AccessibleHostGroups());

        $this->assertNoViolation();
    }

    public function testExistingGroupsRaiseNoViolationForAnAdmin(): void
    {
        $this->security->method('getUser')->willReturn($this->createCredentialUser(isAdmin: true));
        $this->hostGroupRepository->method('findNamesByIds')->willReturn(
            new Collection([5 => new HostGroupName('Linux servers')], HostGroupName::class),
        );

        $this->validator->validate([5], new AccessibleHostGroups());

        $this->assertNoViolation();
    }

    public function testAnUnknownGroupRaisesAViolation(): void
    {
        $this->security->method('getUser')->willReturn($this->createCredentialUser(isAdmin: true));
        $this->hostGroupRepository->method('findNamesByIds')->willReturn(new Collection([], HostGroupName::class));

        $constraint = new AccessibleHostGroups();
        $this->validator->validate([404], $constraint);

        $this->buildViolation($constraint->message)->assertRaised();
    }

    public function testARestrictedViewerCannotReferenceAGroupOutsideTheirScope(): void
    {
        $this->security->method('getUser')->willReturn($this->createCredentialUser(isAdmin: false));
        $this->hostGroupRepository->method('findNamesByIds')->willReturn(
            new Collection([5 => new HostGroupName('Linux servers')], HostGroupName::class),
        );
        $this->resourceAccessRepository->method('findAccessibleHostGroupIds')->willReturn(
            new Collection([new HostGroupId(9)], HostGroupId::class),
        );

        $constraint = new AccessibleHostGroups();
        $this->validator->validate([5], $constraint);

        $this->buildViolation($constraint->message)->assertRaised();
    }

    public function testARestrictedViewerCanReferenceAnAccessibleGroup(): void
    {
        $this->security->method('getUser')->willReturn($this->createCredentialUser(isAdmin: false));
        $this->hostGroupRepository->method('findNamesByIds')->willReturn(
            new Collection([5 => new HostGroupName('Linux servers')], HostGroupName::class),
        );
        $this->resourceAccessRepository->method('findAccessibleHostGroupIds')->willReturn(
            new Collection([new HostGroupId(5)], HostGroupId::class),
        );

        $this->validator->validate([5], new AccessibleHostGroups());

        $this->assertNoViolation();
    }

    public function testARestrictedViewerWithNoAclRestrictionCanReferenceAnyExistingGroup(): void
    {
        $this->security->method('getUser')->willReturn($this->createCredentialUser(isAdmin: false));
        $this->hostGroupRepository->method('findNamesByIds')->willReturn(
            new Collection([5 => new HostGroupName('Linux servers')], HostGroupName::class),
        );
        $this->resourceAccessRepository->method('findAccessibleHostGroupIds')->willReturn(null);

        $this->validator->validate([5], new AccessibleHostGroups());

        $this->assertNoViolation();
    }

    protected function createValidator(): ConstraintValidatorInterface
    {
        return new AccessibleHostGroupsValidator($this->security, $this->hostGroupRepository, $this->resourceAccessRepository);
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
