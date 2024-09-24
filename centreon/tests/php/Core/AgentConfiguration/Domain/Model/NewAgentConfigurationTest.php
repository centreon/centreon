<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

namespace Tests\Core\AgentConfiguration\Domain\Model;

use Centreon\Domain\Common\Assertion\AssertionException;
use Core\AgentConfiguration\Domain\Model\ConfigurationParametersInterface;
use Core\AgentConfiguration\Domain\Model\NewAgentConfiguration;
use Core\AgentConfiguration\Domain\Model\Type;

beforeEach(function (): void {
    $this->createAc = function (array $fields = []): NewAgentConfiguration {
        $agentconfiguration = new NewAgentConfiguration(
            name: $fields['name'] ?? 'ac-name',
            type: Type::TELEGRAF,
            configuration: $this->createMock(ConfigurationParametersInterface::class)
        );

        return $agentconfiguration;
    };
});

it('should return properly set AC instance', function (): void {
    $now = time();
    $agentconfiguration = ($this->createAc)();

    expect($agentconfiguration->getName())->toBe('ac-name');
});

// mandatory fields

it(
    'should throw an exception when AC name is an empty string',
    fn() => ($this->createAc)(['name' => ''])
)->throws(
    AssertionException::class,
    AssertionException::notEmptyString('NewAgentConfiguration::name')->getMessage()
);

// string field trimmed

foreach (
    [
        'name',
    ] as $field
) {
    it(
        "should return trimmed field {$field} after construct",
        function () use ($field): void {
            $agentconfiguration = ($this->createAc)([$field => '  abcd ']);
            $valueFromGetter = $agentconfiguration->{'get' . $field}();

            expect($valueFromGetter)->toBe('abcd');
        }
    );
}

// too long fields

foreach (
    [
        'name' => NewAgentConfiguration::MAX_NAME_LENGTH,
    ] as $field => $length
) {
    $tooLong = str_repeat('a', $length + 1);
    it(
        "should throw an exception when AC {$field} is too long",
        fn() => ($this->createAc)([$field => $tooLong])
    )->throws(
        AssertionException::class,
        AssertionException::maxLength($tooLong, $length + 1, $length, "NewAgentConfiguration::{$field}")->getMessage()
    );
}
