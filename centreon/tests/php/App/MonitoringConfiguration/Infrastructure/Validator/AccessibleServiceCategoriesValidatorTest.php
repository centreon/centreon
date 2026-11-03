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

use App\MonitoringConfiguration\Domain\Aggregate\ServiceCategory\ServiceCategoryId;
use App\MonitoringConfiguration\Infrastructure\Validator\AccessibleServiceCategories;
use App\MonitoringConfiguration\Infrastructure\Validator\AccessibleServiceCategoriesValidator;
use App\Security\Domain\Aggregate\AccessScope;
use App\Security\Domain\Aggregate\Credential;
use App\Security\Domain\Aggregate\CredentialIdentifier;
use App\Security\Domain\Aggregate\Role;
use App\Security\Domain\Aggregate\UserId;
use App\Security\Domain\Service\AccessScopeResolver;
use App\Security\Infrastructure\Security\CredentialUser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeServiceCategoryRepository;
use Tests\App\Security\Infrastructure\Double\FakeResourceAccessRepository;

/**
 * @extends ConstraintValidatorTestCase<AccessibleServiceCategoriesValidator>
 */
final class AccessibleServiceCategoriesValidatorTest extends ConstraintValidatorTestCase
{
    private FakeServiceCategoryRepository $repository;

    private FakeResourceAccessRepository $resourceAccess;

    private bool $isAdmin = true;

    protected function setUp(): void
    {
        $this->repository = new FakeServiceCategoryRepository();
        $this->repository->names = [3 => 'category-3', 4 => 'category-4'];

        $this->resourceAccess = new FakeResourceAccessRepository();
        parent::setUp();
    }

    public function testExistingIdsRaiseNoViolation(): void
    {
        $this->validator->validate([3, 4], new AccessibleServiceCategories());

        $this->assertNoViolation();
    }

    public function testAnUnknownIdRaisesAViolation(): void
    {
        $constraint = new AccessibleServiceCategories();
        $this->validator->validate([3, 999], $constraint);

        $this->buildViolation($constraint->message)->assertRaised();
    }

    public function testAnIdOutsideTheScopeOfARestrictedActorRaisesAViolation(): void
    {
        $this->restrictTo(3);

        $constraint = new AccessibleServiceCategories();
        $this->validator->validate([3, 4], $constraint);

        $this->buildViolation($constraint->message)->assertRaised();
    }

    public function testIdsInTheScopeOfARestrictedActorRaiseNoViolation(): void
    {
        $this->restrictTo(3, 4);

        $this->validator->validate([3, 4], new AccessibleServiceCategories());

        $this->assertNoViolation();
    }

    public function testNoIdRaisesNoViolation(): void
    {
        $this->validator->validate([], new AccessibleServiceCategories());

        $this->assertNoViolation();
    }

    protected function createValidator(): ConstraintValidatorInterface
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($this->actor($this->isAdmin));

        return new AccessibleServiceCategoriesValidator($security, $this->repository, new AccessScopeResolver($this->resourceAccess));
    }

    private function restrictTo(int ...$ids): void
    {
        $this->isAdmin = false;
        $this->resourceAccess->serviceCategoryScope = AccessScope::restrictedTo(ServiceCategoryId::class, array_map(static fn (int $id): ServiceCategoryId => new ServiceCategoryId($id), $ids));
        $this->validator = $this->createValidator();
        $this->validator->initialize($this->context);
    }

    private function actor(bool $isAdmin): CredentialUser
    {
        $credential = new Credential(new CredentialIdentifier('user'), new UserId(7), active: true);
        if ($isAdmin) {
            $credential->assignRole(new Role('ROLE_SUPER_ADMIN'));
        }

        return new CredentialUser($credential);
    }
}
