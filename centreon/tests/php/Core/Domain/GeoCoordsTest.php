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

namespace Tests\Core\Domain;

use Core\Domain\Common\GeoCoords;
use Core\Domain\Exception\InvalidGeoCoordException;

beforeEach(function (): void {
});

it(
    'should return a valid geographic coordinates object for valid latitudes',
    fn () => expect((new GeoCoords($lat = '0', '0'))->latitude)->toBe($lat)
        ->and((new GeoCoords($lat = '0.0', '0'))->latitude)->toBe($lat)
        ->and((new GeoCoords($lat = '+0.0', '0'))->latitude)->toBe($lat)
        ->and((new GeoCoords($lat = '-0.0', '0'))->latitude)->toBe($lat)
        ->and((new GeoCoords($lat = '1', '0'))->latitude)->toBe($lat)
        ->and((new GeoCoords($lat = '11', '0'))->latitude)->toBe($lat)
        ->and((new GeoCoords($lat = '89.999', '0'))->latitude)->toBe($lat)
        ->and((new GeoCoords($lat = '-89.999', '0'))->latitude)->toBe($lat)
        ->and((new GeoCoords($lat = '90.000', '0'))->latitude)->toBe($lat)
        ->and((new GeoCoords($lat = '+90.000', '0'))->latitude)->toBe($lat)
        ->and((new GeoCoords($lat = '-90.000', '0'))->latitude)->toBe($lat)
);

it(
    'should return a valid geographic coordinates object for valid longitudes',
    fn () => expect((new GeoCoords('0', $lng = '0'))->longitude)->toBe($lng)
        ->and((new GeoCoords('0', $lng = '0.0'))->longitude)->toBe($lng)
        ->and((new GeoCoords('0', $lng = '+0.0'))->longitude)->toBe($lng)
        ->and((new GeoCoords('0', $lng = '-0.0'))->longitude)->toBe($lng)
        ->and((new GeoCoords('0', $lng = '1'))->longitude)->toBe($lng)
        ->and((new GeoCoords('0', $lng = '11'))->longitude)->toBe($lng)
        ->and((new GeoCoords('0', $lng = '111'))->longitude)->toBe($lng)
        ->and((new GeoCoords('0', $lng = '179.999'))->longitude)->toBe($lng)
        ->and((new GeoCoords('0', $lng = '-179.999'))->longitude)->toBe($lng)
        ->and((new GeoCoords('0', $lng = '180.000'))->longitude)->toBe($lng)
        ->and((new GeoCoords('0', $lng = '+180.000'))->longitude)->toBe($lng)
        ->and((new GeoCoords('0', $lng = '-180.000'))->longitude)->toBe($lng)
);

it(
    'should not return a valid geographic coordinates object for invalid latitudes',
    function (): void {
        $factory = static fn (string $lat, string $lng) => static fn () => new GeoCoords($lat, $lng);

        expect($factory('', '0'))->toThrow(InvalidGeoCoordException::class)
            ->and($factory('90.00001', '0'))->toThrow(InvalidGeoCoordException::class)
            ->and($factory('-90.00001', '0'))->toThrow(InvalidGeoCoordException::class)
            ->and($factory('1.2.3', '0'))->toThrow(InvalidGeoCoordException::class)
            ->and($factory('1-2', '0'))->toThrow(InvalidGeoCoordException::class)
            ->and($factory('1+2', '0'))->toThrow(InvalidGeoCoordException::class)
            ->and($factory('--1.2', '0'))->toThrow(InvalidGeoCoordException::class)
            ->and($factory('++1.2', '0'))->toThrow(InvalidGeoCoordException::class);
    }
);

it(
    'should not return a valid geographic coordinates object for invalid longitudes',
    function (): void {
        $factory = static fn (string $lat, string $lng) => static fn (): GeoCoords => new GeoCoords($lat, $lng);

        expect($factory('0', ''))->toThrow(InvalidGeoCoordException::class)
            ->and($factory('0', '180.00001'))->toThrow(InvalidGeoCoordException::class)
            ->and($factory('0', '-180.00001'))->toThrow(InvalidGeoCoordException::class)
            ->and($factory('0', '1.2.3'))->toThrow(InvalidGeoCoordException::class)
            ->and($factory('0', '1-2'))->toThrow(InvalidGeoCoordException::class)
            ->and($factory('0', '1+2'))->toThrow(InvalidGeoCoordException::class)
            ->and($factory('0', '--1.2'))->toThrow(InvalidGeoCoordException::class)
            ->and($factory('0', '++1.2'))->toThrow(InvalidGeoCoordException::class);
    }
);

it(
    'should throw an exception when the geographic coordinates object has too few coordinates',
    fn () => GeoCoords::fromString('')
)->throws(
    InvalidGeoCoordException::class,
    InvalidGeoCoordException::invalidFormat()->getMessage()
);

it(
    'should throw an exception when the geographic coordinates object has too many coordinates',
    fn () => GeoCoords::fromString('1,2,3')
)->throws(
    InvalidGeoCoordException::class,
    InvalidGeoCoordException::invalidFormat()->getMessage()
);

it(
    'should throw an exception when the geographic coordinates object has wrong values but a valid format',
    fn () => GeoCoords::fromString(',')
)->throws(
    InvalidGeoCoordException::class,
    InvalidGeoCoordException::invalidValues()->getMessage()
);

it(
    'should throw an exception when the geographic coordinates object has wrong latitude',
    fn () => GeoCoords::fromString('-91.0,100')
)->throws(
    InvalidGeoCoordException::class,
    InvalidGeoCoordException::invalidValues()->getMessage()
);

it(
    'should throw an exception when the geographic coordinates object has wrong longitude',
    fn () => GeoCoords::fromString('-90.0,200')
)->throws(
    InvalidGeoCoordException::class,
    InvalidGeoCoordException::invalidValues()->getMessage()
);

it(
    'should handle whitespace after comma in string format',
    function (): void {
        $coords = GeoCoords::fromString('45.123, -123.789');
        expect($coords->latitude)->toBe('45.123')
            ->and($coords->longitude)->toBe('-123.789');

        $coords = GeoCoords::fromString('0,  0');
        expect($coords->latitude)->toBe('0')
            ->and($coords->longitude)->toBe('0');
    }
);

it(
    'should convert coordinates to string format using toString method',
    function (): void {
        $coords = new GeoCoords('45.123456', '-123.789012');
        expect((string) $coords)->toBe('45.123456,-123.789012');

        $coords = new GeoCoords('0', '0');
        expect($coords->__toString())->toBe('0,0');

        $coords = new GeoCoords('-90.000000', '180.000000');
        expect((string) $coords)->toBe('-90.000000,180.000000');
    }
);

it(
    'should truncate decimal places to maximum of 6 digits',
    function (): void {
        $coords = GeoCoords::fromString('45.1234567890,-123.9876543210');
        expect($coords->latitude)->toBe('45.123456')
            ->and($coords->longitude)->toBe('-123.987654');

        $coords = GeoCoords::fromString('45.1000000000,-123.0000000000');
        expect($coords->latitude)->toBe('45.100000')
            ->and($coords->longitude)->toBe('-123.000000');

        $coords = GeoCoords::fromString('45.0000000000,-123.0000000000');
        expect($coords->latitude)->toBe('45.000000')
            ->and($coords->longitude)->toBe('-123.000000');
    }
);

it(
    'should handle coordinates without decimal places',
    function (): void {
        $coords = GeoCoords::fromString('45,-123');
        expect($coords->latitude)->toBe('45')
            ->and($coords->longitude)->toBe('-123');

        $coords = GeoCoords::fromString('0,0');
        expect($coords->latitude)->toBe('0')
            ->and($coords->longitude)->toBe('0');
    }
);

it(
    'should handle edge case boundary values',
    function (): void {
        $coords = GeoCoords::fromString('89.9999999999999,179.99999999999999');
        expect($coords->latitude)->toBe('89.999999')
            ->and($coords->longitude)->toBe('179.999999');

        $coords = GeoCoords::fromString('-89.9999999999999,-179.9999999999999');
        expect($coords->latitude)->toBe('-89.999999')
            ->and($coords->longitude)->toBe('-179.999999');

        $coords = GeoCoords::fromString('90.0000000000000,180.0000000000000');
        expect($coords->latitude)->toBe('90.000000')
            ->and($coords->longitude)->toBe('180.000000');
    }
);

it(
    'should handle positive sign prefix',
    function (): void {
        $coords = GeoCoords::fromString('+45.123,+123.789');
        expect($coords->latitude)->toBe('+45.123')
            ->and($coords->longitude)->toBe('+123.789');

        $coords = GeoCoords::fromString('+0,+0');
        expect($coords->latitude)->toBe('+0')
            ->and($coords->longitude)->toBe('+0');
    }
);

it(
    'should handle numeric validation for fromString method',
    function (): void {
        expect(fn () => GeoCoords::fromString('abc,123'))
            ->toThrow(InvalidGeoCoordException::class, InvalidGeoCoordException::invalidValues()->getMessage());

        expect(fn () => GeoCoords::fromString('123,abc'))
            ->toThrow(InvalidGeoCoordException::class, InvalidGeoCoordException::invalidValues()->getMessage());

        expect(fn () => GeoCoords::fromString('abc,abc'))
            ->toThrow(InvalidGeoCoordException::class, InvalidGeoCoordException::invalidValues()->getMessage());
    }
);

it(
    'should validate coordinates through constructor with complex invalid formats',
    function (): void {
        $factory = static fn (string $lat, string $lng) => static fn () => new GeoCoords($lat, $lng);

        expect($factory('91', '0'))->toThrow(InvalidGeoCoordException::class)
            ->and($factory('-91', '0'))->toThrow(InvalidGeoCoordException::class)
            ->and($factory('0', '181'))->toThrow(InvalidGeoCoordException::class)
            ->and($factory('0', '-181'))->toThrow(InvalidGeoCoordException::class)
            ->and($factory('90.1', '0'))->toThrow(InvalidGeoCoordException::class)
            ->and($factory('0', '180.1'))->toThrow(InvalidGeoCoordException::class);
    }
);
