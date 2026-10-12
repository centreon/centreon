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

use App\MonitoringConfiguration\Infrastructure\Service\CommandArgumentsFormatter;
use App\MonitoringConfiguration\Infrastructure\Validator\ValidCommandArguments;
use App\MonitoringConfiguration\Infrastructure\Validator\ValidCommandArgumentsValidator;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<ValidCommandArgumentsValidator>
 */
final class ValidCommandArgumentsValidatorTest extends ConstraintValidatorTestCase
{
    public function testAcceptsPlainArguments(): void
    {
        $this->validator->validate(['-w', '80', "multi\nline"], new ValidCommandArguments('check command'));

        $this->assertNoViolation();
    }

    public function testRejectsTheDelimiterAtTheIndexOfTheArgument(): void
    {
        $this->validator->validate(['-w', 'a!b'], new ValidCommandArguments('check command'));

        $this->buildViolation('A check command argument cannot contain "!".')->atPath('property.path[1]')->assertRaised();
    }

    public function testRejectsAnEscapeToken(): void
    {
        $this->validator->validate(['x#BR#y'], new ValidCommandArguments('check command'));

        $this->buildViolation('A check command argument cannot contain the reserved escape tokens #BR#, #T# or #R#.')->atPath('property.path[0]')->assertRaised();
    }

    public function testNamesTheSubjectWithTheRightArticle(): void
    {
        $this->validator->validate(['a!b'], new ValidCommandArguments('event handler'));

        $this->buildViolation('An event handler argument cannot contain "!".')->atPath('property.path[0]')->assertRaised();
    }

    public function testRejectsArgumentsThatDoNotFitTheirColumn(): void
    {
        $this->validator->validate([str_repeat('a', CommandArgumentsFormatter::MAX_STORAGE_LENGTH + 1)], new ValidCommandArguments('check command'));

        $this->buildViolation('The check command arguments are too long.')->assertRaised();
    }

    public function testLeavesEntriesThatAreNotStringsToTheTypeConstraint(): void
    {
        $this->validator->validate(['a!b', 42], new ValidCommandArguments('check command'));

        $this->assertNoViolation();
    }

    protected function createValidator(): ConstraintValidatorInterface
    {
        return new ValidCommandArgumentsValidator();
    }
}
