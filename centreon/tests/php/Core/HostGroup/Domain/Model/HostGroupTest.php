<?php

/*
 * Copyright 2005 - 2022 Centreon (https://www.centreon.com/)
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

namespace Tests\Core\HostGroup\Domain\Model;

use Assert\InvalidArgumentException;
use Centreon\Domain\Common\Assertion\AssertionException;
use Core\Domain\Common\GeoCoords;
use Core\HostGroup\Domain\Model\HostGroup;

beforeEach(function (): void {
    $this->createHostGroup = static function (array $fields = []): HostGroup {
        return new HostGroup(
            ...[
                'id' => 1,
                'name' => 'host-name',
                'alias' => 'host-alias',
                'notes' => '',
                'notesUrl' => '',
                'actionUrl' => '',
                'iconId' => 2,
                'iconMapId' => null,
                'rrdRetention' => null,
                'geoCoords' => GeoCoords::fromString('-90.0,180.0'),
                'comment' => '',
                'isActivated' => true,
                ...$fields,
            ]
        );
    };
});

it('should return properly set host group instance', function (): void {
    $hostGroup = ($this->createHostGroup)();

    expect($hostGroup->getId())->toBe(1)
        ->and($hostGroup->getIconId())->toBe(2)
        ->and((string) $hostGroup->getGeoCoords())->toBe('-90.0,180.0')
        ->and($hostGroup->getName())->toBe('host-name')
        ->and($hostGroup->getAlias())->toBe('host-alias');
});

// mandatory fields

it(
    'should throw an exception when host group name is an empty string',
    fn() => ($this->createHostGroup)(['name' => ''])
)->throws(
    InvalidArgumentException::class,
    AssertionException::minLength('', 0, HostGroup::MIN_NAME_LENGTH, 'HostGroup::name')->getMessage()
);

// string field trimmed

foreach (
    [
        'name',
        'alias',
        'notes',
        'notesUrl',
        'actionUrl',
        'comment',
    ] as $field
) {
    it(
        "should return trim the field {$field} after construct",
        function () use ($field): void {
            $hostGroup = ($this->createHostGroup)([$field => '  abcd ']);
            $valueFromGetter = $hostGroup->{'get' . $field}();

            expect($valueFromGetter)->toBe('abcd');
        }
    );
}

// too long fields

foreach (
    [
        'name' => HostGroup::MAX_NAME_LENGTH,
        'alias' => HostGroup::MAX_ALIAS_LENGTH,
        'notes' => HostGroup::MAX_NOTES_LENGTH,
        'notesUrl' => HostGroup::MAX_NOTES_URL_LENGTH,
        'actionUrl' => HostGroup::MAX_ACTION_URL_LENGTH,
        // We have skipped the comment max size test because it costs ~1 second
        // to run it, so more than 10 times the time of all the other tests.
        // At this moment, I didn't find any explanation, and considering
        // this is a non-critical field ... I prefer skipping it.
        // 'comment' => HostGroup::MAX_COMMENT_LENGTH,
    ] as $field => $length
) {
    $tooLong = str_repeat('a', $length + 1);
    it(
        "should throw an exception when host group {$field} is too long",
        fn() => ($this->createHostGroup)([$field => $tooLong])
    )->throws(
        InvalidArgumentException::class,
        AssertionException::maxLength($tooLong, $length + 1, $length, "HostGroup::{$field}")->getMessage()
    );
}

// FK fields : int = 0 forbidden

foreach (['iconId', 'iconMapId'] as $field) {
    it(
        "should throw an exception when host group {$field} is an empty integer",
        fn() => ($this->createHostGroup)([$field => 0])
    )->throws(
        InvalidArgumentException::class,
        AssertionException::positiveInt(0, "HostGroup::{$field}")->getMessage()
    );
}

// FK fields : int < 0 forbidden

foreach (['iconId', 'iconMapId'] as $field) {
    it(
        "should throw an exception when host group {$field} is a negative integer",
        fn() => ($this->createHostGroup)([$field => -1])
    )->throws(
        InvalidArgumentException::class,
        AssertionException::positiveInt(-1, "HostGroup::{$field}")->getMessage()
    );
}

// geoCoords field

it(
    'should return a valid host group if the "geoCoords" field is valid',
    function (): void {
        $hostGroup = ($this->createHostGroup)();
        expect($hostGroup->getGeoCoords())->toBeInstanceOf(GeoCoords::class);
    }
);
