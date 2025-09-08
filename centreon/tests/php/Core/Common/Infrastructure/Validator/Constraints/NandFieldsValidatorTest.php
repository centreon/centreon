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

namespace Tests\Core\Common\Infrastructure\Validator\Constraints;

use Core\Common\Infrastructure\Validator\Constraints\NandFields;
use Core\Common\Infrastructure\Validator\Constraints\NandFieldsValidator;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<ConstraintValidatorInterface>
 */
final class NandFieldsValidatorTest extends ConstraintValidatorTestCase
{
    public const PROPERTIES = ['firstProperty', 'secondProperty'];

    /**
     * @dataProvider validDataProvider
     */
    public function testValidData(object $object): void
    {
        $constraint = new NandFields(properties: self::PROPERTIES);
        $this->validator->validate($object, $constraint);

        $this->assertNoViolation();
    }

    /**
     * @dataProvider invalidDataProvider
     */
    public function testInvalidData(object $object): void
    {
        $constraint = new NandFields(properties: self::PROPERTIES);
        $this->validator->validate($object, $constraint);

        $this->buildViolation($constraint->message)
            ->setParameter('{{ properties }}', implode(', ', self::PROPERTIES))
            ->assertRaised();
    }

    /**
     * @return \Generator<string,array<object>>
     */
    public static function validDataProvider(): \Generator
    {
        yield 'first property is not null, second property is null' => [
            new class () {
                public ?int $firstProperty = 1;

                public ?string $secondProperty = null;

                public array $thirdProperty = [];
            },
        ];

        yield 'first property is null, second property is not null' => [
            new class () {
                public ?int $firstProperty = null;

                public ?string $secondProperty = 'value';

                public array $thirdProperty = [];
            },
        ];

        yield 'one property is missing' => [
            new class () {
                public ?int $firstProperty = 1;

                public array $thirdProperty = [];
            },
        ];

        yield 'both properties are null' => [
            new class () {
                public ?int $firstProperty = null;

                public ?string $secondProperty = null;

                public array $thirdProperty = [];
            },
        ];

        yield 'both properties are missing' => [
            new class () {
                public array $thirdProperty = [];
            },
        ];
    }

    /**
     * @return \Generator<string,array<object>>
     */
    public static function invalidDataProvider(): \Generator
    {
        yield 'both properties are not null' => [
            new class () {
                public ?int $firstProperty = 1;

                public ?string $secondProperty = 'value';

                public array $thirdProperty = [];
            },
        ];
    }

    protected function createValidator(): ConstraintValidatorInterface
    {
        return new NandFieldsValidator();
    }
}
