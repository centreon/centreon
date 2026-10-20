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

use App\MonitoringConfiguration\Domain\Aggregate\HostCategory\HostCategoryId;
use App\MonitoringConfiguration\Domain\Aggregate\HostCategory\HostCategoryName;
use App\MonitoringConfiguration\Domain\Repository\HostCategoryRepository;
use App\MonitoringConfiguration\Infrastructure\Validator\AccessibleHostCategories;
use App\MonitoringConfiguration\Infrastructure\Validator\AccessibleHostCategoriesValidator;
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
 * @extends ConstraintValidatorTestCase<AccessibleHostCategoriesValidator>
 */
final class AccessibleHostCategoriesValidatorTest extends ConstraintValidatorTestCase
{
    private HostCategoryRepository&MockObject $hostCategoryRepository;

    private ResourceAccessRepository&MockObject $resourceAccessRepository;

    private Security&MockObject $security;

    protected function setUp(): void
    {
        $this->hostCategoryRepository = $this->createMock(HostCategoryRepository::class);
        $this->resourceAccessRepository = $this->createMock(ResourceAccessRepository::class);
        $this->security = $this->createMock(Security::class);
        parent::setUp();
    }

    public function testAnEmptyListRaisesNoViolation(): void
    {
        $this->validator->validate([], new AccessibleHostCategories());

        $this->assertNoViolation();
    }

    public function testExistingCategoriesRaiseNoViolationForAnAdmin(): void
    {
        $this->security->method('getUser')->willReturn($this->createCredentialUser(isAdmin: true));
        $this->hostCategoryRepository->method('findNamesByIds')->willReturn(new Collection([5 => new HostCategoryName('Production')], HostCategoryName::class));

        $this->validator->validate([5], new AccessibleHostCategories());

        $this->assertNoViolation();
    }

    public function testAnUnknownCategoryRaisesAViolation(): void
    {
        $this->security->method('getUser')->willReturn($this->createCredentialUser(isAdmin: true));
        $this->hostCategoryRepository->method('findNamesByIds')->willReturn(new Collection([], HostCategoryName::class));

        $constraint = new AccessibleHostCategories();
        $this->validator->validate([404], $constraint);

        $this->buildViolation($constraint->message)->assertRaised();
    }

    public function testARestrictedViewerCannotReferenceACategoryOutsideTheirScope(): void
    {
        $this->security->method('getUser')->willReturn($this->createCredentialUser(isAdmin: false));
        $this->hostCategoryRepository->method('findNamesByIds')->willReturn(new Collection([5 => new HostCategoryName('Production')], HostCategoryName::class));
        $this->resourceAccessRepository->method('findAccessibleHostCategoryIds')->willReturn(new Collection([new HostCategoryId(9)], HostCategoryId::class));

        $constraint = new AccessibleHostCategories();
        $this->validator->validate([5], $constraint);

        $this->buildViolation($constraint->message)->assertRaised();
    }

    public function testARestrictedViewerWithNoAclRestrictionCanReferenceAnyExistingCategory(): void
    {
        $this->security->method('getUser')->willReturn($this->createCredentialUser(isAdmin: false));
        $this->hostCategoryRepository->method('findNamesByIds')->willReturn(new Collection([5 => new HostCategoryName('Production')], HostCategoryName::class));
        $this->resourceAccessRepository->method('findAccessibleHostCategoryIds')->willReturn(null);

        $this->validator->validate([5], new AccessibleHostCategories());

        $this->assertNoViolation();
    }

    protected function createValidator(): ConstraintValidatorInterface
    {
        return new AccessibleHostCategoriesValidator($this->security, $this->hostCategoryRepository, $this->resourceAccessRepository);
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
