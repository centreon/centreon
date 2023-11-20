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

namespace Tests\Core\ResourceAccess\Domain\Model;

use Assert\InvalidArgumentException;
use Centreon\Domain\Common\Assertion\AssertionException;
use Core\ResourceAccess\Domain\Model\Rule;

beforeEach(function (): void {
    $this->createRule = static fn (): Rule => (new Rule(1, 'FULL', true))->setDescription('Full access');
});

it('should return properly set Rule instance (all properties)', function (): void {
    $rule = ($this->createRule)();

    expect($rule->getId())->toBe(1)
        ->and($rule->getName())->toBe('FULL')
        ->and($rule->getDescription())->toBe('Full access')
        ->and($rule->isEnabled())->toBe(true);
});

it('should return properly set Rule instance (mandatory properties only)', function (): void {
    $rule = new Rule(
        id: 1,
        name: 'FULL',
        isEnabled: false
    );

    expect($rule->getId())->toBe(1)
        ->and($rule->getName())->toBe('FULL')
        ->and($rule->getDescription())->toBe(null)
        ->and($rule->isEnabled())->toBe(false);
});

it('should throw an exception when rules id is not a positive int', function (): void {
    new Rule(
        id: 0,
        name: '',
        isEnabled: false
    );
})->throws(
    InvalidArgumentException::class,
    AssertionException::positiveInt(0, 'Rule::id')->getMessage()
);

it('should throw an exception when rules name is an empty string', function (): void {
    new Rule(
        id: 1,
        name: '',
        isEnabled: false
    );
})->throws(
    InvalidArgumentException::class,
    AssertionException::notEmptyString('Rule::name')->getMessage()
);

it('should throw an exception when rules name is an string exceeding max size', function (): void {
    new Rule(
        id: 1,
        name: str_repeat('a', Rule::MAX_NAME_LENGTH + 1),
        isEnabled: false
    );
})->throws(
    InvalidArgumentException::class,
    AssertionException::maxLength(
        str_repeat('a', Rule::MAX_NAME_LENGTH + 1),
        Rule::MAX_NAME_LENGTH + 1,
        Rule::MAX_NAME_LENGTH,
        'Rule::name'
    )->getMessage(),
);

