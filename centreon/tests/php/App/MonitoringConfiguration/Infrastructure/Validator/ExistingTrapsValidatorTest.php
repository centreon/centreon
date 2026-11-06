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

use App\MonitoringConfiguration\Domain\Aggregate\Trap\Trap;
use App\MonitoringConfiguration\Domain\Aggregate\Trap\TrapId;
use App\MonitoringConfiguration\Domain\Aggregate\Trap\TrapName;
use App\MonitoringConfiguration\Domain\Aggregate\Trap\TrapVendor;
use App\MonitoringConfiguration\Domain\Aggregate\Trap\TrapVendorId;
use App\MonitoringConfiguration\Domain\Aggregate\Trap\TrapVendorName;
use App\MonitoringConfiguration\Infrastructure\Validator\ExistingTraps;
use App\MonitoringConfiguration\Infrastructure\Validator\ExistingTrapsValidator;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeTrapRepository;

/**
 * @extends ConstraintValidatorTestCase<ExistingTrapsValidator>
 */
final class ExistingTrapsValidatorTest extends ConstraintValidatorTestCase
{
    private FakeTrapRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new FakeTrapRepository();
        foreach ([3, 4] as $id) {
            $this->repository->traps[$id] = new Trap(new TrapId($id), new TrapName('trap-' . $id), new TrapVendor(new TrapVendorId(1), new TrapVendorName('generic')));
        }
        parent::setUp();
    }

    public function testExistingTrapsRaiseNoViolation(): void
    {
        $this->validator->validate([3, 4], new ExistingTraps());

        $this->assertNoViolation();
    }

    /**
     * The finder of the trap selector also leaves out a trap without vendor or name: such a trap is refused the
     * same way.
     */
    public function testAnUnknownTrapRaisesAViolation(): void
    {
        $constraint = new ExistingTraps();
        $this->validator->validate([3, 999], $constraint);

        $this->buildViolation($constraint->message)->assertRaised();
    }

    public function testNoTrapRaisesNoViolation(): void
    {
        $this->validator->validate([], new ExistingTraps());

        $this->assertNoViolation();
    }

    protected function createValidator(): ConstraintValidatorInterface
    {
        return new ExistingTrapsValidator($this->repository);
    }
}
