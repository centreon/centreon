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
use Core\AgentConfiguration\Domain\Model\AgentConfiguration;
use Core\AgentConfiguration\Domain\Model\ConfigurationParametersInterface;
use Core\AgentConfiguration\Domain\Model\Type;

beforeEach(function (): void {
    $this->testedCreatedAt = new \DateTimeImmutable('2023-05-09T12:00:00+00:00');
    $this->testedUpdatedAt = new \DateTimeImmutable('2023-05-09T16:00:00+00:00');
    $this->testedParameters = $this->createMock(ConfigurationParametersInterface::class);
    $this->testedType = Type::TELEGRAF;
    $this->createAc = function (array $fields = []): AgentConfiguration {
        return new AgentConfiguration(
            id: $fields['id'] ?? 1,
            name: $fields['name'] ?? 'ac-name',
            type: $this->testedType,
            configuration: $this->testedParameters,
        );
    };
});

it('should return properly set AC instance', function (): void {
    $agentconfiguration = ($this->createAc)();

    expect($agentconfiguration->getId())->toBe(1)
        ->and($agentconfiguration->getName())->toBe('ac-name')
        ->and($agentconfiguration->getType())->toBe($this->testedType)
        ->and($agentconfiguration->getConfiguration())->toBe($this->testedParameters);
});

// mandatory fields
it(
    'should throw an exception when ACC name is an empty string',
    fn() => ($this->createAc)(['name' => ''])
)->throws(
    AssertionException::class,
    AssertionException::notEmptyString('AgentConfiguration::name')->getMessage()
);

// string field trimmed
it('should return trimmed field name after construct', function (): void {
    $agentconfiguration = new AgentConfiguration(
        id: 1,
        type: Type::TELEGRAF,
        name: ' abcd ',
        configuration: $this->testedParameters
    );

    expect($agentconfiguration->getName())->toBe('abcd');
});

// too long fields
foreach (
    [
        'name' => AgentConfiguration::MAX_NAME_LENGTH,
    ] as $field => $length
) {
    $tooLong = str_repeat('a', $length + 1);
    it(
        "should throw an exception when AC {$field} is too long",
        fn() => ($this->createAc)([$field => $tooLong])
    )->throws(
        AssertionException::class,
        AssertionException::maxLength($tooLong, $length + 1, $length, "AgentConfiguration::{$field}")->getMessage()
    );
}
