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
use Core\AdditionalConnector\Domain\Model\NewAdditionalConnector;
use Core\AdditionalConnector\Domain\Model\Type;

beforeEach(function (): void {
    $this->createAdditionalConnector = static function (array $fields = []): NewAdditionalConnector {
        $additionalconnector = new NewAdditionalConnector(
            name: $fields['name'] ?? 'additionalconnector-name',
            type: Type::VMWARE_V6,
            createdBy: $fields['created_by'] ?? 2,
            parameters: []
        );
        $additionalconnector->setDescription($fields['description'] ?? 'additionalconnector-description');
        // $additionalconnector->setUpdatedBy($fields['updated_by'] ?? 3);

        return $additionalconnector;
    };
});

it('should return properly set AdditionalConnector instance', function (): void {
    $now = time();
    $additionalconnector = ($this->createAdditionalConnector)();

    expect($additionalconnector->getName())->toBe('additionalconnector-name')
        ->and($additionalconnector->getDescription())->toBe('additionalconnector-description')
        ->and($additionalconnector->getCreatedAt()->getTimestamp())->toBeGreaterThanOrEqual($now)
        ->and($additionalconnector->getUpdatedAt()->getTimestamp())->toBeGreaterThanOrEqual($now)
        ->and($additionalconnector->getCreatedBy())->toBe(2)
        ->and($additionalconnector->getUpdatedBy())->toBe(2);
});

// mandatory fields

it(
    'should throw an exception when AdditionalConnector name is an empty string',
    fn() => ($this->createAdditionalConnector)(['name' => ''])
)->throws(
    AssertionException::class,
    AssertionException::notEmptyString('NewAdditionalConnector::name')->getMessage()
);

// string field trimmed

foreach (
    [
        'name',
        'description',
    ] as $field
) {
    it(
        "should return trim the field {$field} after construct",
        function () use ($field): void {
            $additionalconnector = ($this->createAdditionalConnector)([$field => '  abcd ']);
            $valueFromGetter = $additionalconnector->{'get' . $field}();

            expect($valueFromGetter)->toBe('abcd');
        }
    );
}

// updatedAt change

it(
    'should change the updatedAt field',
    function (): void {
        $updatedAtBefore = time();
        $additionalconnector = ($this->createAdditionalConnector)();
        $updatedAtAfter = $additionalconnector->getUpdatedAt()->getTimestamp();

        expect($updatedAtAfter)->toBeGreaterThanOrEqual($updatedAtBefore);
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
        AssertionException::maxLength($tooLong, $length + 1, $length, "NewAdditionalConnector::{$field}")->getMessage()
    );
}

// not positive integers

foreach (
    [
        'created_by' => 'createdBy',
    ] as $field => $propertyName
) {
    it(
        "should throw an exception when AdditionalConnector {$field} is not a positive integer",
        fn() => ($this->createAdditionalConnector)([$field => 0])
    )->throws(
        AssertionException::class,
        AssertionException::positiveInt(0, 'NewAdditionalConnector::' . $propertyName)->getMessage()
    );
}
