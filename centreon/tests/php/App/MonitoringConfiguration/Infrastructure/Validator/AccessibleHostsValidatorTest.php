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

use App\MonitoringConfiguration\Domain\Aggregate\Host\HostName;
use App\MonitoringConfiguration\Domain\Repository\HostRepository;
use App\MonitoringConfiguration\Infrastructure\Validator\AccessibleHosts;
use App\MonitoringConfiguration\Infrastructure\Validator\AccessibleHostsValidator;
use App\Security\Domain\Aggregate\Credential;
use App\Security\Domain\Aggregate\CredentialIdentifier;
use App\Security\Domain\Aggregate\Role;
use App\Security\Domain\Aggregate\UserId;
use App\Security\Infrastructure\Security\CredentialUser;
use App\Shared\Domain\Collection;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<AccessibleHostsValidator>
 */
final class AccessibleHostsValidatorTest extends ConstraintValidatorTestCase
{
    private HostRepository&MockObject $hostRepository;

    private Security&MockObject $security;

    protected function setUp(): void
    {
        $this->hostRepository = $this->createMock(HostRepository::class);
        $this->security = $this->createMock(Security::class);
        parent::setUp();
    }

    public function testAnEmptyListRaisesNoViolation(): void
    {
        $this->validator->validate([], new AccessibleHosts());

        $this->assertNoViolation();
    }

    public function testExistingHostsRaiseNoViolationForAnAdmin(): void
    {
        $this->security->method('getUser')->willReturn($this->createCredentialUser(isAdmin: true));
        $this->hostRepository->expects(self::once())->method('findNamesByIds')->with(self::anything(), null)->willReturn(new Collection([5 => new HostName('server-01')], HostName::class));

        $this->validator->validate([5], new AccessibleHosts());

        $this->assertNoViolation();
    }

    public function testAnUnknownHostRaisesAViolation(): void
    {
        $this->security->method('getUser')->willReturn($this->createCredentialUser(isAdmin: true));
        $this->hostRepository->method('findNamesByIds')->willReturn(new Collection([], HostName::class));

        $constraint = new AccessibleHosts();
        $this->validator->validate([404], $constraint);

        $this->buildViolation($constraint->message)->assertRaised();
    }

    public function testARestrictedViewerIsPassedToTheLookupSoAHostOutsideTheirScopeIsMissing(): void
    {
        $this->security->method('getUser')->willReturn($this->createCredentialUser(isAdmin: false));
        $this->hostRepository->expects(self::once())->method('findNamesByIds')
            ->with(self::anything(), self::callback(static fn (?UserId $viewerId): bool => $viewerId?->value === 7))
            ->willReturn(new Collection([], HostName::class));

        $constraint = new AccessibleHosts();
        $this->validator->validate([5], $constraint);

        $this->buildViolation($constraint->message)->assertRaised();
    }

    protected function createValidator(): ConstraintValidatorInterface
    {
        return new AccessibleHostsValidator($this->security, $this->hostRepository);
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
