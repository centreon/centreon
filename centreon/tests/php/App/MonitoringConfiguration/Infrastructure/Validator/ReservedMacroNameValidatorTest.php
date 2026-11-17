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

use App\MonitoringConfiguration\Infrastructure\Validator\ReservedMacroName;
use App\MonitoringConfiguration\Infrastructure\Validator\ReservedMacroNameValidator;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeStandardMacroRepository;

/**
 * The reserved names are compared in their stored form: the prefix, the trimmed upper-cased short name, `$`.
 *
 * @extends ConstraintValidatorTestCase<ReservedMacroNameValidator>
 */
final class ReservedMacroNameValidatorTest extends ConstraintValidatorTestCase
{
    public function testTheHostPrefixIsTheDefault(): void
    {
        $constraint = new ReservedMacroName();
        $this->validator->validate(' hostonly ', $constraint);

        $this->buildViolation('This macro name is reserved and cannot be used.')->assertRaised();
    }

    public function testAServiceReservedNameIsFreeForAHost(): void
    {
        $this->validator->validate('serviceonly', new ReservedMacroName());

        $this->assertNoViolation();
    }

    public function testServicePrefix(): void
    {
        $this->validator->validate(' serviceOnly ', new ReservedMacroName(prefix: '$_SERVICE'));

        $this->buildViolation('This macro name is reserved and cannot be used.')->assertRaised();
    }

    public function testAHostReservedNameIsFreeForAService(): void
    {
        $this->validator->validate('hostonly', new ReservedMacroName(prefix: '$_SERVICE'));

        $this->assertNoViolation();
    }

    public function testABlankNameIsLeftToTheOtherConstraints(): void
    {
        $this->validator->validate('  ', new ReservedMacroName(prefix: '$_SERVICE'));

        $this->assertNoViolation();
    }

    protected function createValidator(): ConstraintValidatorInterface
    {
        return new ReservedMacroNameValidator(new FakeStandardMacroRepository(['$_HOSTHOSTONLY$', '$_SERVICESERVICEONLY$']));
    }
}
