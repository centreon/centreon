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

namespace Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host;

use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host\HostMacroInput;
use App\MonitoringConfiguration\Infrastructure\Validator\ReservedMacroNameValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\Validation;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeStandardMacroRepository;

/**
 * The name of a submitted macro is bounded so that its stored `$_HOST<NAME>$` form fits its column, the length
 * being the one of that upper-cased form.
 */
final class HostMacroInputValidationTest extends TestCase
{
    /**
     * @param list<string> $expected
     */
    #[DataProvider('nameProvider')]
    public function testTheNameLengthIsTheOneOfTheStoredName(string $name, array $expected): void
    {
        self::assertSame($expected, $this->nameViolations(new HostMacroInput(name: $name, value: 'v')));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function nameProvider(): iterable
    {
        yield 'name of 248 characters' => [str_repeat('a', 248), []];

        yield 'name of 248 characters once trimmed' => [' ' . str_repeat('a', 248) . ' ', []];

        yield 'name of 249 characters' => [str_repeat('a', 249), ['This value is too long. It should have 248 characters or less.']];

        // "ß" upper-cases to "SS".
        yield 'name of 248 characters once upper-cased' => [str_repeat('ß', 124), []];

        yield 'name of 250 characters once upper-cased' => [str_repeat('ß', 125), ['This value is too long. It should have 248 characters or less.']];
    }

    /**
     * @return list<string>
     */
    private function nameViolations(HostMacroInput $input): array
    {
        $validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->setConstraintValidatorFactory(new ConstraintValidatorFactory([
                ReservedMacroNameValidator::class => new ReservedMacroNameValidator(new FakeStandardMacroRepository()),
            ]))
            ->getValidator();

        $messages = [];
        foreach ($validator->validateProperty($input, 'name') as $violation) {
            $messages[] = (string) $violation->getMessage();
        }

        return $messages;
    }
}
