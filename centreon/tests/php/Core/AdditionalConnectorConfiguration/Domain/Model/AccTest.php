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
use Core\AdditionalConnectorConfiguration\Domain\Model\Type;

beforeEach(function (): void {
    $this->testedCreatedAt = new \DateTimeImmutable('2023-05-09T12:00:00+00:00');
    $this->testedUpdatedAt = new \DateTimeImmutable('2023-05-09T16:00:00+00:00');
    $this->testedParameters = $this->createMock(AccParametersInterface::class);
    $this->testedType = Type::VMWARE_V6;
    $this->createAcc = function (array $fields = []): Acc {
        return new Acc(
            id: $fields['id'] ?? 1,
            name: $fields['name'] ?? 'acc-name',
            type: $this->testedType,
            createdBy: \array_key_exists('created_by', $fields) ? $fields['created_by'] : 2,
            updatedBy: \array_key_exists('updated_by', $fields) ? $fields['updated_by'] : 3,
            createdAt: $this->testedCreatedAt,
            updatedAt: $this->testedUpdatedAt,
            parameters: $this->testedParameters,
        );
    };
});

it('should return properly set ACC instance', function (): void {
    $acc = ($this->createAcc)();

    expect($acc->getId())->toBe(1)
        ->and($acc->getName())->toBe('acc-name')
        ->and($acc->getDescription())->toBe(null)
        ->and($acc->getType())->toBe($this->testedType)
        ->and($acc->getUpdatedAt()->getTimestamp())->toBe($this->testedUpdatedAt->getTimestamp())
        ->and($acc->getCreatedAt()->getTimestamp())->toBe($this->testedCreatedAt->getTimestamp())
        ->and($acc->getCreatedBy())->toBe(2)
        ->and($acc->getUpdatedBy())->toBe(3)
        ->and($acc->getParameters())->toBe($this->testedParameters);
});

// mandatory fields

it(
    'should throw an exception when ACC name is an empty string',
    fn() => ($this->createAcc)(['name' => ''])
)->throws(
    AssertionException::class,
    AssertionException::notEmptyString('Acc::name')->getMessage()
);

// string field trimmed
it('should return trimmed field name after construct', function (): void {
    $acc = new Acc(
        id: 1,
        type: Type::VMWARE_V6,
        name: ' abcd ',
        createdBy: 1,
        updatedBy: 1,
        createdAt: new \DateTimeImmutable(),
        updatedAt: new \DateTimeImmutable(),
        parameters: $this->testedParameters
    );

    expect($acc->getName())->toBe('abcd');
});

it('should return trimmed field description', function (): void {
    $acc = new Acc(
        id: 1,
        type: Type::VMWARE_V6,
        name: 'abcd',
        createdBy: 1,
        updatedBy: 1,
        createdAt: new \DateTimeImmutable(),
        updatedAt: new \DateTimeImmutable(),
        parameters: $this->testedParameters,
        description: ' abcd '
    );

    expect($acc->getDescription())->toBe('abcd');
});

// updatedAt change

it(
    'should NOT change the updatedAt field',
    function (): void {
        $updatedAtBefore = $this->testedUpdatedAt->getTimestamp();
        $acc = ($this->createAcc)();
        $updatedAtAfter = $acc->getUpdatedAt()->getTimestamp();

        expect($updatedAtAfter)->toBe($updatedAtBefore);
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
        AssertionException::maxLength($tooLong, $length + 1, $length, "Acc::{$field}")->getMessage()
    );
}

// not positive integers

foreach (
    [
        'created_by' => 'createdBy',
        'updated_by' => 'updatedBy',
    ] as $field => $propertyName
) {
    it(
        "should throw an exception when ACC {$field} is not a positive integer",
        fn() => ($this->createAcc)([$field => 0])
    )->throws(
        AssertionException::class,
        AssertionException::positiveInt(0, 'Acc::' . $propertyName)->getMessage()
    );
}

// nullable field allowed

foreach (
    [
        'created_by' => 'createdBy',
        'updated_by' => 'updatedBy',
    ] as $field => $propertyName
) {
    it(
        "should return the NULL field {$field} after construct",
        function () use ($field, $propertyName): void {
            $acc = ($this->createAcc)([$field => null]);
            $valueFromGetter = $acc->{'get' . $propertyName}();

            expect($valueFromGetter)->toBeNull();
        }
    );
}
