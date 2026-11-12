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

use App\MonitoringConfiguration\Domain\Aggregate\ServiceSeverity\ServiceSeverityId;
use App\MonitoringConfiguration\Infrastructure\Validator\AccessibleServiceSeverity;
use App\MonitoringConfiguration\Infrastructure\Validator\AccessibleServiceSeverityValidator;
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
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeServiceSeverityRepository;
use Tests\App\Security\Infrastructure\Double\FakeResourceAccessRepository;

/**
 * @extends ConstraintValidatorTestCase<AccessibleServiceSeverityValidator>
 */
final class AccessibleServiceSeverityValidatorTest extends ConstraintValidatorTestCase
{
    private FakeServiceSeverityRepository $repository;

    private FakeResourceAccessRepository $resourceAccess;

    private bool $isAdmin = true;

    protected function setUp(): void
    {
        $this->repository = new FakeServiceSeverityRepository();
        $this->repository->names = [4 => 'critical'];

        $this->resourceAccess = new FakeResourceAccessRepository();
        parent::setUp();
    }

    public function testAnExistingSeverityRaisesNoViolation(): void
    {
        $this->validator->validate(4, new AccessibleServiceSeverity());

        $this->assertNoViolation();
    }

    /**
     * A category without level is no severity: the finder does not know it either.
     */
    public function testAnUnknownSeverityRaisesAViolation(): void
    {
        $constraint = new AccessibleServiceSeverity();
        $this->validator->validate(404, $constraint);

        $this->buildViolation($constraint->message)->assertRaised();
    }

    public function testASeverityOutsideTheScopeOfARestrictedActorRaisesAViolation(): void
    {
        $this->restrictTo(9);

        $constraint = new AccessibleServiceSeverity();
        $this->validator->validate(4, $constraint);

        $this->buildViolation($constraint->message)->assertRaised();
    }

    public function testASeverityInTheScopeOfARestrictedActorRaisesNoViolation(): void
    {
        $this->restrictTo(4);

        $this->validator->validate(4, new AccessibleServiceSeverity());

        $this->assertNoViolation();
    }

    protected function createValidator(): ConstraintValidatorInterface
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($this->actor($this->isAdmin));

        return new AccessibleServiceSeverityValidator($security, $this->repository, new AccessScopeResolver($this->resourceAccess));
    }

    private function restrictTo(int $id): void
    {
        $this->isAdmin = false;
        $this->resourceAccess->serviceSeverityScope = AccessScope::restrictedTo(ServiceSeverityId::class, [new ServiceSeverityId($id)]);
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
