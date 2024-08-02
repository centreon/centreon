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

namespace Tests\Core\AdditionalConnector\Domain\Model;

use Centreon\Domain\Common\Assertion\AssertionException;
use Core\AdditionalConnector\Domain\Model\AdditionalConnector;
use Core\AdditionalConnector\Domain\Model\Type;

beforeEach(function (): void {
    $this->testedCreatedAt = new \DateTimeImmutable('2023-05-09T12:00:00+00:00');
    $this->testedUpdatedAt = new \DateTimeImmutable('2023-05-09T16:00:00+00:00');
    $this->testedParameters = [
        'port' => 4242,
        'vcenters' => [[
            'name' => 'my-vcenter',
            'url' => 'http://10.10.10.10/sdk',
            'username' => 'admin',
            'password' => 'my-pwd',
        ]],
    ];
    $this->testedType = Type::VMWARE_V6;
    $this->createAdditionalConnector = function (array $fields = []): AdditionalConnector {
        return new AdditionalConnector(
            id: $fields['id'] ?? 1,
            name: $fields['name'] ?? 'additionalconnector-name',
            type: $this->testedType,
            createdBy: \array_key_exists('created_by', $fields) ? $fields['created_by'] : 2,
            updatedBy: \array_key_exists('updated_by', $fields) ? $fields['updated_by'] : 3,
            createdAt: $this->testedCreatedAt,
            updatedAt: $this->testedUpdatedAt,
            parameters: $this->testedParameters,
        );
    };
});

it('should return properly set AdditionalConnector instance', function (): void {
    $additionalconnector = ($this->createAdditionalConnector)();

    expect($additionalconnector->getId())->toBe(1)
        ->and($additionalconnector->getName())->toBe('additionalconnector-name')
        ->and($additionalconnector->getDescription())->toBe(null)
        ->and($additionalconnector->getType())->toBe($this->testedType)
        ->and($additionalconnector->getUpdatedAt()->getTimestamp())->toBe($this->testedUpdatedAt->getTimestamp())
        ->and($additionalconnector->getCreatedAt()->getTimestamp())->toBe($this->testedCreatedAt->getTimestamp())
        ->and($additionalconnector->getCreatedBy())->toBe(2)
        ->and($additionalconnector->getUpdatedBy())->toBe(3)
        ->and($additionalconnector->getParameters())->toBe($this->testedParameters);
});

// mandatory fields

it(
    'should throw an exception when AdditionalConnector name is an empty string',
    fn() => ($this->createAdditionalConnector)(['name' => ''])
)->throws(
    AssertionException::class,
    AssertionException::notEmptyString('AdditionalConnector::name')->getMessage()
);

// string field trimmed
it('should return trim the field name after construct', function (): void {
    $additionalconnector = new AdditionalConnector(
        id: 1,
        type: Type::VMWARE_V6,
        name: ' abcd ',
        createdBy: 1,
        updatedBy: 1,
        createdAt: new \DateTimeImmutable(),
        updatedAt: new \DateTimeImmutable(),
        parameters: []
    );

    expect($additionalconnector->getName())->toBe('abcd');
});

it('should return trim the field description', function (): void {
    $additionalconnector = (new AdditionalConnector(
        id: 1,
        type: Type::VMWARE_V6,
        name: 'abcd',
        createdBy: 1,
        updatedBy: 1,
        createdAt: new \DateTimeImmutable(),
        updatedAt: new \DateTimeImmutable(),
        parameters: []
    ))->setDescription(' abcd ');

    expect($additionalconnector->getDescription())->toBe('abcd');
});

// updatedAt change

it(
    'should NOT change the updatedAt field',
    function (): void {
        $updatedAtBefore = $this->testedUpdatedAt->getTimestamp();
        $additionalconnector = ($this->createAdditionalConnector)();
        $updatedAtAfter = $additionalconnector->getUpdatedAt()->getTimestamp();

        expect($updatedAtAfter)->toBe($updatedAtBefore);
    }
);

// too long fields

foreach (
    [
        'name' => AdditionalConnector::MAX_NAME_LENGTH,
    ] as $field => $length
) {
    $tooLong = str_repeat('a', $length + 1);
    it(
        "should throw an exception when AdditionalConnector {$field} is too long",
        fn() => ($this->createAdditionalConnector)([$field => $tooLong])
    )->throws(
        AssertionException::class,
        AssertionException::maxLength($tooLong, $length + 1, $length, "AdditionalConnector::{$field}")->getMessage()
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
        "should throw an exception when AdditionalConnector {$field} is not a positive integer",
        fn() => ($this->createAdditionalConnector)([$field => 0])
    )->throws(
        AssertionException::class,
        AssertionException::positiveInt(0, 'AdditionalConnector::' . $propertyName)->getMessage()
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
            $additionalconnector = ($this->createAdditionalConnector)([$field => null]);
            $valueFromGetter = $additionalconnector->{'get' . $propertyName}();

            expect($valueFromGetter)->toBeNull();
        }
    );
}
