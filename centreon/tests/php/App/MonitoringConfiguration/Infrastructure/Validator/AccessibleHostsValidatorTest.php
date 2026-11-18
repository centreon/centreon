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

use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostName;
use App\MonitoringConfiguration\Domain\Repository\HostRepository;
use App\MonitoringConfiguration\Infrastructure\Validator\AccessibleHosts;
use App\MonitoringConfiguration\Infrastructure\Validator\AccessibleHostsValidator;
use App\Security\Domain\Aggregate\Credential;
use App\Security\Domain\Aggregate\CredentialIdentifier;
use App\Security\Domain\Aggregate\Role;
use App\Security\Domain\Aggregate\UserId;
use App\Security\Domain\Service\AccessScopeResolver;
use App\Security\Infrastructure\Security\CredentialUser;
use App\Shared\Domain\Collection;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;
use Tests\App\Security\Infrastructure\Double\FakeResourceAccessRepository;

/**
 * @extends ConstraintValidatorTestCase<AccessibleHostsValidator>
 */
final class AccessibleHostsValidatorTest extends ConstraintValidatorTestCase
{
    private HostRepository&MockObject $hostRepository;

    private Security&MockObject $security;

    private FakeResourceAccessRepository $resourceAccessRepository;

    protected function setUp(): void
    {
        $this->hostRepository = $this->createMock(HostRepository::class);
        $this->security = $this->createMock(Security::class);
        $this->resourceAccessRepository = new FakeResourceAccessRepository();
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
        $this->hostRepository->method('findNamesByIds')->willReturn(new Collection([5 => new HostName('server-01')], HostName::class));

        $this->validator->validate([5], new AccessibleHosts());

        $this->assertNoViolation();
        self::assertSame([], $this->resourceAccessRepository->lookups, 'An admin is not scoped.');
    }

    public function testAnUnknownHostRaisesAViolation(): void
    {
        $this->security->method('getUser')->willReturn($this->createCredentialUser(isAdmin: true));
        $this->hostRepository->method('findNamesByIds')->willReturn(new Collection([], HostName::class));

        $constraint = new AccessibleHosts();
        $this->validator->validate([404], $constraint);

        $this->buildViolation($constraint->message)->assertRaised();
    }

    public function testAHostOutsideTheRestrictedActorScopeIsMissing(): void
    {
        $this->security->method('getUser')->willReturn($this->createCredentialUser(isAdmin: false));
        $this->hostRepository->method('findNamesByIds')->willReturn(new Collection([5 => new HostName('server-01')], HostName::class));

        $constraint = new AccessibleHosts();
        $this->validator->validate([5], $constraint);

        $this->buildViolation($constraint->message)->assertRaised();
    }

    public function testAHostInTheRestrictedActorScopeRaisesNoViolation(): void
    {
        $this->security->method('getUser')->willReturn($this->createCredentialUser(isAdmin: false));
        $this->hostRepository->method('findNamesByIds')->willReturn(new Collection([5 => new HostName('server-01')], HostName::class));
        $this->resourceAccessRepository->accessibleHostIds = new Collection([new HostId(5)], HostId::class);

        $this->validator->validate([5], new AccessibleHosts());

        $this->assertNoViolation();
    }

    protected function createValidator(): ConstraintValidatorInterface
    {
        return new AccessibleHostsValidator($this->security, $this->hostRepository, new AccessScopeResolver($this->resourceAccessRepository));
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
