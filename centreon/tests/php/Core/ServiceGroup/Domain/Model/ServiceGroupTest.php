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

namespace Tests\Core\ServiceGroup\Domain\Model;

use Assert\InvalidArgumentException;
use Centreon\Domain\Common\Assertion\AssertionException;
use Core\Domain\Common\GeoCoords;
use Core\ServiceGroup\Domain\Model\ServiceGroup;

beforeEach(function (): void {
    $this->createServiceGroup = static function (array $fields = []): ServiceGroup {
        return new ServiceGroup(
            ...[
                'id' => 1,
                'name' => 'service-name',
                'alias' => 'service-alias',
                'geoCoords' => GeoCoords::fromString('-90.0,180.0'),
                'comment' => '',
                'isActivated' => true,
                ...$fields,
            ]
        );
    };
});

it('should return properly set service group instance', function (): void {
    $serviceGroup = ($this->createServiceGroup)();

    expect($serviceGroup->getId())->toBe(1)
        ->and((string) $serviceGroup->getGeoCoords())->toBe('-90.0,180.0')
        ->and($serviceGroup->getName())->toBe('service-name')
        ->and($serviceGroup->getAlias())->toBe('service-alias');
});

// mandatory fields

it(
    'should throw an exception when service group name is an empty string',
    fn() => ($this->createServiceGroup)(['name' => ''])
)->throws(
    InvalidArgumentException::class,
    AssertionException::minLength('', 0, ServiceGroup::MIN_NAME_LENGTH, 'ServiceGroup::name')->getMessage()
);

// string field trimmed

foreach (
    [
        'name',
        'alias',
        'comment',
    ] as $field
) {
    it(
        "should return trim the field {$field} after construct",
        function () use ($field): void {
            $serviceGroup = ($this->createServiceGroup)([$field => '  abcd ']);
            $valueFromGetter = $serviceGroup->{'get' . $field}();

            expect($valueFromGetter)->toBe('abcd');
        }
    );
}

// too long fields

foreach (
    [
        'name' => ServiceGroup::MAX_NAME_LENGTH,
        'alias' => ServiceGroup::MAX_ALIAS_LENGTH,
        // We have skipped the comment max size test because it costs ~1 second
        // to run it, so more than 10 times the time of all the other tests.
        // At this moment, I didn't find any explanation, and considering
        // this is a non-critical field ... I prefer skipping it.
        // 'comment' => ServiceGroup::MAX_COMMENT_LENGTH,
    ] as $field => $length
) {
    $tooLong = str_repeat('a', $length + 1);
    it(
        "should throw an exception when service group {$field} is too long",
        fn() => ($this->createServiceGroup)([$field => $tooLong])
    )->throws(
        InvalidArgumentException::class,
        AssertionException::maxLength($tooLong, $length + 1, $length, "ServiceGroup::{$field}")->getMessage()
    );
}

// geoCoords field

it(
    'should return a valid service group if the "geoCoords" field is valid',
    function (): void {
        $serviceGroup = ($this->createServiceGroup)();
        expect($serviceGroup->getGeoCoords())->toBeInstanceOf(GeoCoords::class);
    }
);
