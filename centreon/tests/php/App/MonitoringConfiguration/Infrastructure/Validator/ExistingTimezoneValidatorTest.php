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

use App\MonitoringConfiguration\Domain\Aggregate\Timezone\TimezoneId;
use App\MonitoringConfiguration\Domain\Aggregate\Timezone\TimezoneName;
use App\MonitoringConfiguration\Domain\Repository\TimezoneRepository;
use App\MonitoringConfiguration\Infrastructure\Validator\ExistingTimezone;
use App\MonitoringConfiguration\Infrastructure\Validator\ExistingTimezoneValidator;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<ExistingTimezoneValidator>
 */
final class ExistingTimezoneValidatorTest extends ConstraintValidatorTestCase
{
    private TimezoneRepository&MockObject $timezoneRepository;

    protected function setUp(): void
    {
        $this->timezoneRepository = $this->createMock(TimezoneRepository::class);
        parent::setUp();
    }

    public function testAnExistingTimezoneRaisesNoViolation(): void
    {
        $this->timezoneRepository->method('findNameById')->with(new TimezoneId(3))->willReturn(new TimezoneName('Europe/Paris'));

        $this->validator->validate(3, new ExistingTimezone());

        $this->assertNoViolation();
    }

    public function testAnUnknownTimezoneRaisesAViolation(): void
    {
        $this->timezoneRepository->method('findNameById')->willReturn(null);
        $constraint = new ExistingTimezone();

        $this->validator->validate(404, $constraint);

        $this->buildViolation($constraint->message)->assertRaised();
    }

    public function testAValueThatIsNotAnIntegerIsLeftToOtherConstraints(): void
    {
        $this->validator->validate('Europe/Paris', new ExistingTimezone());

        $this->assertNoViolation();
    }

    protected function createValidator(): ConstraintValidatorInterface
    {
        return new ExistingTimezoneValidator($this->timezoneRepository);
    }
}
