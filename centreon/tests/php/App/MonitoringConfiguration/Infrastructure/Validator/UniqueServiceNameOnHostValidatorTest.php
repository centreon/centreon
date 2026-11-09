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
use App\MonitoringConfiguration\Domain\Aggregate\Service\Service;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceName;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service\CreateServiceInput;
use App\MonitoringConfiguration\Infrastructure\Validator\UniqueServiceNameOnHost;
use App\MonitoringConfiguration\Infrastructure\Validator\UniqueServiceNameOnHostValidator;
use App\Security\Domain\Aggregate\Credential;
use App\Security\Domain\Aggregate\CredentialIdentifier;
use App\Security\Domain\Aggregate\UserId;
use App\Security\Infrastructure\Security\CredentialUser;
use App\Shared\Domain\Collection;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeHostRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeServiceRepository;

/**
 * @extends ConstraintValidatorTestCase<UniqueServiceNameOnHostValidator>
 */
final class UniqueServiceNameOnHostValidatorTest extends ConstraintValidatorTestCase
{
    private FakeServiceRepository $serviceRepository;

    private FakeHostRepository $hostRepository;

    protected function setUp(): void
    {
        $this->serviceRepository = new FakeServiceRepository();
        $this->serviceRepository->add(new Service(id: null, name: new ServiceName('disk usage'), hostId: new HostId(10), hostActivated: true, hostPollerId: null));

        $this->hostRepository = new FakeHostRepository();
        $this->hostRepository->states[10] = new HostState(true, new PollerId(1));
        $this->hostRepository->states[11] = new HostState(true, new PollerId(1));
        parent::setUp();
    }

    public function testANameUnusedOnTheHostRaisesNoViolation(): void
    {
        $this->validator->validate($this->input('ping', 10), new UniqueServiceNameOnHost());

        $this->assertNoViolation();
    }

    public function testANameUsedOnAnotherHostRaisesNoViolation(): void
    {
        $this->validator->validate($this->input('disk usage', 11), new UniqueServiceNameOnHost());

        $this->assertNoViolation();
    }

    /**
     * Compared as it will be stored: trimmed, inner spaces collapsed, whatever its case.
     */
    public function testANameUsedOnTheHostRaisesAViolationOnTheName(): void
    {
        $constraint = new UniqueServiceNameOnHost();
        $this->validator->validate($this->input('  DISK   usage ', 10), $constraint);

        $this->buildViolation($constraint->message)->atPath('property.path.name')->assertRaised();
    }

    /**
     * A restricted actor cannot learn that a name exists on a host they cannot use.
     */
    public function testItChecksNothingWhileTheHostIsNotValid(): void
    {
        $this->validator->validate($this->input('disk usage', 999), new UniqueServiceNameOnHost());

        $this->assertNoViolation();
    }

    public function testItChecksNothingForANameTheOtherRulesRefuse(): void
    {
        $this->serviceRepository->forceNameUsed = true;

        $this->validator->validate($this->input(str_repeat('a', 201), 10), new UniqueServiceNameOnHost());
        $this->validator->validate($this->input('   ', 10), new UniqueServiceNameOnHost());

        $this->assertNoViolation();
    }

    protected function createValidator(): ConstraintValidatorInterface
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn(new CredentialUser(new Credential(new CredentialIdentifier('user'), new UserId(1), active: true)));
        $this->hostRepository->resourceAccess->accessibleHostIds = new Collection([new HostId(10), new HostId(11)], HostId::class);

        return new UniqueServiceNameOnHostValidator($security, $this->serviceRepository, $this->hostRepository);
    }

    private function input(string $name, int $hostId): CreateServiceInput
    {
        return new CreateServiceInput(name: $name, hostId: $hostId);
    }
}
