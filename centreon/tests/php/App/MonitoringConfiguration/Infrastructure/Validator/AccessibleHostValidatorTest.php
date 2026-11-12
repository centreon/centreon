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
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostState;
use App\MonitoringConfiguration\Domain\Aggregate\Poller\PollerId;
use App\MonitoringConfiguration\Infrastructure\Validator\AccessibleHost;
use App\MonitoringConfiguration\Infrastructure\Validator\AccessibleHostValidator;
use App\Security\Domain\Aggregate\Credential;
use App\Security\Domain\Aggregate\CredentialIdentifier;
use App\Security\Domain\Aggregate\Role;
use App\Security\Domain\Aggregate\UserId;
use App\Security\Infrastructure\Security\CredentialUser;
use App\Shared\Domain\Collection;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostRepository;

/**
 * @extends ConstraintValidatorTestCase<AccessibleHostValidator>
 */
final class AccessibleHostValidatorTest extends ConstraintValidatorTestCase
{
    private FakeHostRepository $hostRepository;

    private bool $isAdmin = true;

    protected function setUp(): void
    {
        $this->hostRepository = new FakeHostRepository();
        $this->hostRepository->states[10] = new HostState(true, new PollerId(1));
        parent::setUp();
    }

    public function testAnExistingHostRaisesNoViolation(): void
    {
        $this->validator->validate(10, new AccessibleHost());

        $this->assertNoViolation();
    }

    /**
     * An unknown id and a host template alike: neither is a real host.
     */
    public function testAnUnknownHostRaisesAViolation(): void
    {
        $constraint = new AccessibleHost();
        $this->validator->validate(999, $constraint);

        $this->buildViolation($constraint->message)->assertRaised();
    }

    public function testAHostOutsideTheScopeOfARestrictedActorRaisesAViolation(): void
    {
        $this->isAdmin = false;
        $this->hostRepository->resourceAccess->accessibleHostIds = new Collection([new HostId(11)], HostId::class);
        $this->validator = $this->createValidator();
        $this->validator->initialize($this->context);

        $constraint = new AccessibleHost();
        $this->validator->validate(10, $constraint);

        $this->buildViolation($constraint->message)->assertRaised();
    }

    public function testAHostInTheScopeOfARestrictedActorRaisesNoViolation(): void
    {
        $this->isAdmin = false;
        $this->hostRepository->resourceAccess->accessibleHostIds = new Collection([new HostId(10)], HostId::class);
        $this->validator = $this->createValidator();
        $this->validator->initialize($this->context);

        $this->validator->validate(10, new AccessibleHost());

        $this->assertNoViolation();
    }

    protected function createValidator(): ConstraintValidatorInterface
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($this->actor($this->isAdmin));

        return new AccessibleHostValidator($security, $this->hostRepository);
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
