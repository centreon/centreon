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

use App\MonitoringConfiguration\Infrastructure\Validator\ValidCentralAddress;
use App\MonitoringConfiguration\Infrastructure\Validator\ValidCentralAddressValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<ValidCentralAddressValidator>
 */
final class ValidCentralAddressValidatorTest extends ConstraintValidatorTestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function validAddressProvider(): iterable
    {
        yield 'hostname' => ['central.example.com'];

        yield 'IPv4 with port and base path' => ['10.0.0.1:8443/centreon'];

        yield 'bare IPv6' => ['2001:db8::1'];

        yield 'bracketed IPv6 with port' => ['[2001:db8::1]:8443'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidAddressProvider(): iterable
    {
        yield 'malformed bracketed IPv6' => ['[::::]'];

        yield 'bracketed IPv4' => ['[10.0.0.1]'];

        yield 'port out of range' => ['central.example.com:70000'];

        yield 'dot segment in the base path' => ['central.example.com/platform/../admin'];

        yield 'shell metacharacter' => ['central.example.com;id'];
    }

    #[DataProvider('validAddressProvider')]
    public function testValidAddressRaisesNoViolation(string $address): void
    {
        $this->validator->validate($address, new ValidCentralAddress());

        $this->assertNoViolation();
    }

    #[DataProvider('invalidAddressProvider')]
    public function testInvalidAddressRaisesViolation(string $address): void
    {
        $constraint = new ValidCentralAddress();
        $this->validator->validate($address, $constraint);

        $this->buildViolation($constraint->message)->assertRaised();
    }

    /**
     * Left to NotBlank, which already covers the field: reporting it twice would clutter the
     * response with two violations for one mistake.
     */
    public function testEmptyValueIsSkipped(): void
    {
        $this->validator->validate('   ', new ValidCentralAddress());

        $this->assertNoViolation();
    }

    protected function createValidator(): ConstraintValidatorInterface
    {
        return new ValidCentralAddressValidator();
    }
}
