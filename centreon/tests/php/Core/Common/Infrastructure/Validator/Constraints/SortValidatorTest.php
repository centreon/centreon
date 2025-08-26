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

use Core\Common\Infrastructure\Validator\Constraints\Sort;
use Core\Common\Infrastructure\Validator\Constraints\SortValidator;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<ConstraintValidatorInterface>
 */
final class SortValidatorTest extends ConstraintValidatorTestCase
{
    public function testValidData(): void
    {
        $this->validator->validate(
            '{"host_group":"ASC"}',
            new Sort(
                sortKeys: ['host_category', 'host_group', 'host', 'service_category'],
                sortDirections: ['ASC', 'DESC'],
            )
        );

        $this->assertNoViolation();
    }

    /**
     * @dataProvider providerInvalidData
     *
     * @param array<string,string> $parameters
     */
    public function testInvalidData(string $sortValue, string $errorMessage, array $parameters): void
    {
        $this->validator->validate(
            $sortValue,
            new Sort(
                sortKeys: ['host_category', 'host_group', 'host', 'service_category'],
                sortDirections: ['ASC', 'DESC'],
            )
        );

        $this->buildViolation($errorMessage)
            ->setParameters($parameters)
            ->assertRaised();
    }

    /**
     * @return Generator<string,mixed>
     */
    public static function providerInvalidData(): \Generator
    {
        yield 'invalid sort key' => [
            '{"service":"ASC"}',
            '{{ key }} is not a valid sort value. Expected values [{{ values }}].',
            ['{{ key }}' => 'service', '{{ values }}' => 'host_category, host_group, host, service_category'],
        ];

        yield 'invalid sort direction' => [
            '{"host":"INVALID"}',
            '{{ key }} has invalid sort direction ({{ direction }}). Expected directions [{{ values }}].',
            ['{{ key }}' => 'host', '{{ direction }}' => 'INVALID', '{{ values }}' => 'ASC,DESC'],
        ];
    }

    protected function createValidator(): ConstraintValidatorInterface
    {
        return new SortValidator();
    }
}
