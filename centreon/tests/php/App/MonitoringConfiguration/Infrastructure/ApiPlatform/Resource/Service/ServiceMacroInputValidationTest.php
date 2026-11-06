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

namespace Tests\App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service;

use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service\ServiceCheckOptionsInput;
use App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Service\ServiceMacroInput;
use App\MonitoringConfiguration\Infrastructure\Validator\CheckCommandTypeValidator;
use App\MonitoringConfiguration\Infrastructure\Validator\ReservedMacroNameValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeCommandRepository;
use Tests\App\MonitoringConfiguration\Infrastructure\Double\FakeStandardMacroRepository;

/**
 * Every rule of a submitted macro, at the level of the input DTO. The paths are the PHP property paths; the HTTP
 * layer renders them in snake_case. The reserved names are `$_SERVICECRITICALITY_LEVEL$` and, for hosts only,
 * `$_HOSTHOSTONLY$`.
 */
final class ServiceMacroInputValidationTest extends TestCase
{
    /**
     * @param array<string, string> $expected property path => message
     */
    #[DataProvider('ruleProvider')]
    public function testRules(ServiceMacroInput $input, array $expected): void
    {
        self::assertSame($expected, $this->violations($input));
    }

    /**
     * @return iterable<string, array{ServiceMacroInput, array<string, string>}>
     */
    public static function ruleProvider(): iterable
    {
        yield 'new macro with a value' => [new ServiceMacroInput(name: 'port', value: '8080'), []];

        yield 'empty value' => [new ServiceMacroInput(name: 'port', value: ''), []];

        yield 'blank name' => [new ServiceMacroInput(name: '   ', value: 'v'), ['name' => 'This value should not be blank.']];

        yield 'name of 245 characters' => [new ServiceMacroInput(name: str_repeat('a', 245), value: 'v'), []];

        yield 'name of 245 characters once trimmed' => [new ServiceMacroInput(name: ' ' . str_repeat('a', 245) . ' ', value: 'v'), []];

        yield 'name of 246 characters' => [
            new ServiceMacroInput(name: str_repeat('a', 246), value: 'v'),
            ['name' => 'This value is too long. It should have 245 characters or less.'],
        ];

        yield 'reserved name, any case' => [
            new ServiceMacroInput(name: 'Criticality_Level', value: '1'),
            ['name' => 'This macro name is reserved and cannot be used.'],
        ];

        yield 'name reserved for hosts only' => [new ServiceMacroInput(name: 'hostonly', value: 'v'), []];

        yield 'value of 4096 characters' => [new ServiceMacroInput(name: 'big', value: str_repeat('v', 4096)), []];

        yield 'value of 4097 characters' => [
            new ServiceMacroInput(name: 'big', value: str_repeat('v', 4097)),
            ['value' => 'This value is too long. It should have 4096 characters or less.'],
        ];

        yield 'vault reference as value' => [
            new ServiceMacroInput(name: 'pwd', value: 'secret::vault::monitoring/services/uuid::_SERVICEPWD', isPassword: true),
            ['value' => 'A macro value cannot be a vault reference.'],
        ];

        yield 'no value on a new macro' => [
            new ServiceMacroInput(name: 'pwd', value: null, isPassword: true),
            ['value' => 'A value is required, except to keep the stored value of an existing password macro.'],
        ];

        yield 'no value on an existing macro that is not a password' => [
            new ServiceMacroInput(name: 'port', value: null, id: 3),
            ['value' => 'A value is required, except to keep the stored value of an existing password macro.'],
        ];

        yield 'no value on an existing password macro' => [new ServiceMacroInput(name: 'pwd', value: null, isPassword: true, id: 3, parent: 'template'), []];

        yield 'id 1' => [new ServiceMacroInput(name: 'port', value: 'v', id: 1, parent: 'command'), []];

        yield 'id 0' => [new ServiceMacroInput(name: 'port', value: 'v', id: 0), ['id' => 'This value should be positive.']];

        yield 'unknown parent' => [
            new ServiceMacroInput(name: 'port', value: 'v', id: 1, parent: 'host'),
            ['parent' => 'The value you selected is not a valid choice.'],
        ];
    }

    /**
     * The macros of the check options are validated one by one, each violation at its position.
     */
    public function testTheCheckOptionsValidateEachMacro(): void
    {
        $violations = [];
        foreach ($this->validator()->validate(new ServiceCheckOptionsInput(macros: [
            new ServiceMacroInput(name: 'port', value: '8080'),
            new ServiceMacroInput(name: 'criticality_level', value: '1'),
        ])) as $violation) {
            $violations[$violation->getPropertyPath()] = (string) $violation->getMessage();
        }

        self::assertSame(['macros[1].name' => 'This macro name is reserved and cannot be used.'], $violations);
    }

    /**
     * @return array<string, string> property path => message
     */
    private function violations(ServiceMacroInput $input): array
    {
        $violations = [];
        foreach ($this->validator()->validate($input) as $violation) {
            $violations[$violation->getPropertyPath()] = (string) $violation->getMessage();
        }
        ksort($violations);

        return $violations;
    }

    private function validator(): ValidatorInterface
    {
        return Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->setConstraintValidatorFactory(new ConstraintValidatorFactory([
                ReservedMacroNameValidator::class => new ReservedMacroNameValidator(
                    new FakeStandardMacroRepository(['$_SERVICECRITICALITY_LEVEL$', '$_HOSTHOSTONLY$']),
                ),
                CheckCommandTypeValidator::class => new CheckCommandTypeValidator(new FakeCommandRepository()),
            ]))
            ->getValidator();
    }
}
