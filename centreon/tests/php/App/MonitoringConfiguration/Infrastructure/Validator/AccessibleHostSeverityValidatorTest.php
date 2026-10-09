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

use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityId;
use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityName;
use App\MonitoringConfiguration\Domain\Repository\HostSeverityRepository;
use App\MonitoringConfiguration\Infrastructure\Validator\AccessibleHostSeverity;
use App\MonitoringConfiguration\Infrastructure\Validator\AccessibleHostSeverityValidator;
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
 * @extends ConstraintValidatorTestCase<AccessibleHostSeverityValidator>
 */
final class AccessibleHostSeverityValidatorTest extends ConstraintValidatorTestCase
{
    private HostSeverityRepository&MockObject $hostSeverityRepository;

    private ResourceAccessRepository&MockObject $resourceAccessRepository;

    private Security&MockObject $security;

    protected function setUp(): void
    {
        $this->hostSeverityRepository = $this->createMock(HostSeverityRepository::class);
        $this->resourceAccessRepository = $this->createMock(ResourceAccessRepository::class);
        $this->security = $this->createMock(Security::class);
        parent::setUp();
    }

    public function testAnExistingSeverityRaisesNoViolationForAnAdmin(): void
    {
        $this->security->method('getUser')->willReturn($this->createCredentialUser(isAdmin: true));
        $this->hostSeverityRepository->method('findNameById')->willReturn(new HostSeverityName('critical'));

        $this->validator->validate(4, new AccessibleHostSeverity());

        $this->assertNoViolation();
    }

    public function testAnUnknownSeverityRaisesAViolation(): void
    {
        $this->security->method('getUser')->willReturn($this->createCredentialUser(isAdmin: true));
        $this->hostSeverityRepository->method('findNameById')->willReturn(null);

        $constraint = new AccessibleHostSeverity();
        $this->validator->validate(404, $constraint);

        $this->buildViolation($constraint->message)->assertRaised();
    }

    public function testARestrictedViewerCannotReferenceASeverityOutsideTheirScope(): void
    {
        $this->security->method('getUser')->willReturn($this->createCredentialUser(isAdmin: false));
        $this->hostSeverityRepository->method('findNameById')->willReturn(new HostSeverityName('critical'));
        $this->resourceAccessRepository->method('findAccessibleHostSeverityIds')
            ->willReturn(new Collection([new HostSeverityId(9)], HostSeverityId::class));

        $constraint = new AccessibleHostSeverity();
        $this->validator->validate(4, $constraint);

        $this->buildViolation($constraint->message)->assertRaised();
    }

    public function testARestrictedViewerCanReferenceAnAccessibleSeverity(): void
    {
        $this->security->method('getUser')->willReturn($this->createCredentialUser(isAdmin: false));
        $this->hostSeverityRepository->method('findNameById')->willReturn(new HostSeverityName('critical'));
        $this->resourceAccessRepository->method('findAccessibleHostSeverityIds')
            ->willReturn(new Collection([new HostSeverityId(4)], HostSeverityId::class));

        $this->validator->validate(4, new AccessibleHostSeverity());

        $this->assertNoViolation();
    }

    public function testAViewerWithoutSeverityRestrictionCanReferenceAnyExistingSeverity(): void
    {
        $this->security->method('getUser')->willReturn($this->createCredentialUser(isAdmin: false));
        $this->hostSeverityRepository->method('findNameById')->willReturn(new HostSeverityName('critical'));
        $this->resourceAccessRepository->method('findAccessibleHostSeverityIds')->willReturn(null);

        $this->validator->validate(4, new AccessibleHostSeverity());

        $this->assertNoViolation();
    }

    protected function createValidator(): ConstraintValidatorInterface
    {
        return new AccessibleHostSeverityValidator($this->security, $this->hostSeverityRepository, $this->resourceAccessRepository);
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
