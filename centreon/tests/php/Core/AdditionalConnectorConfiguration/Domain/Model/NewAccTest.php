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

namespace Tests\Core\AdditionalConnectorConfiguration\Domain\Model;

use Centreon\Domain\Common\Assertion\AssertionException;
use Core\AdditionalConnectorConfiguration\Domain\Model\Acc;
use Core\AdditionalConnectorConfiguration\Domain\Model\AccParametersInterface;
use Core\AdditionalConnectorConfiguration\Domain\Model\NewAcc;
use Core\AdditionalConnectorConfiguration\Domain\Model\Type;

beforeEach(function (): void {
    $this->createAcc = function (array $fields = []): NewAcc {
        $acc = new NewAcc(
            name: $fields['name'] ?? 'acc-name',
            type: Type::VMWARE_V6,
            createdBy: $fields['created_by'] ?? 2,
            parameters: $this->createMock(AccParametersInterface::class)
        );
        $acc->setDescription($fields['description'] ?? 'acc-description');

        return $acc;
    };
});

it('should return properly set ACC instance', function (): void {
    $now = time();
    $acc = ($this->createAcc)();

    expect($acc->getName())->toBe('acc-name')
        ->and($acc->getDescription())->toBe('acc-description')
        ->and($acc->getCreatedAt()->getTimestamp())->toBeGreaterThanOrEqual($now)
        ->and($acc->getUpdatedAt()->getTimestamp())->toBeGreaterThanOrEqual($now)
        ->and($acc->getCreatedBy())->toBe(2)
        ->and($acc->getUpdatedBy())->toBe(2);
});

// mandatory fields

it(
    'should throw an exception when ACC name is an empty string',
    fn() => ($this->createAcc)(['name' => ''])
)->throws(
    AssertionException::class,
    AssertionException::notEmptyString('NewAcc::name')->getMessage()
);

// string field trimmed

foreach (
    [
        'name',
        'description',
    ] as $field
) {
    it(
        "should return trimmed field {$field} after construct",
        function () use ($field): void {
            $acc = ($this->createAcc)([$field => '  abcd ']);
            $valueFromGetter = $acc->{'get' . $field}();

            expect($valueFromGetter)->toBe('abcd');
        }
    );
}

// updatedAt change
it(
    'should change the updatedAt field',
    function (): void {
        $updatedAtBefore = time();
        $acc = ($this->createAcc)();
        $updatedAtAfter = $acc->getUpdatedAt()->getTimestamp();

        expect($updatedAtAfter)->toBeGreaterThanOrEqual($updatedAtBefore);
    }
);

// too long fields

foreach (
    [
        'name' => Acc::MAX_NAME_LENGTH,
    ] as $field => $length
) {
    $tooLong = str_repeat('a', $length + 1);
    it(
        "should throw an exception when ACC {$field} is too long",
        fn() => ($this->createAcc)([$field => $tooLong])
    )->throws(
        AssertionException::class,
        AssertionException::maxLength($tooLong, $length + 1, $length, "NewAcc::{$field}")->getMessage()
    );
}

// not positive integers

foreach (
    [
        'created_by' => 'createdBy',
    ] as $field => $propertyName
) {
    it(
        "should throw an exception when ACC {$field} is not a positive integer",
        fn() => ($this->createAcc)([$field => 0])
    )->throws(
        AssertionException::class,
        AssertionException::positiveInt(0, 'NewAcc::' . $propertyName)->getMessage()
    );
}
