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

namespace Tests\Core\Domain;

use Core\Domain\Common\GeoCoords;
use Core\Domain\Exception\InvalidGeoCoordException;

beforeEach(function (): void {
});

it(
    'should return a valid geographic coordinates object for valid latitudes',
    fn() => expect((new GeoCoords($lat = '0', '0'))->latitude)->toBe($lat)
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
    fn() => expect((new GeoCoords('0', $lng = '0'))->longitude)->toBe($lng)
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
        $factory = static fn(string $lat, string $lng) => static fn() => new GeoCoords($lat, $lng);

        expect($factory('', '0'))->toThrow(InvalidGeoCoordException::class)
            ->and($factory('90.00001', '0'))->toThrow(InvalidGeoCoordException::class)
            ->and($factory('-90.00001', '0'))->toThrow(\Core\Domain\Exception\InvalidGeoCoordException::class)
            ->and($factory('1.2.3', '0'))->toThrow(InvalidGeoCoordException::class)
            ->and($factory('1-2', '0'))->toThrow(InvalidGeoCoordException::class)
            ->and($factory('1+2', '0'))->toThrow(InvalidGeoCoordException::class)
            ->and($factory('--1.2', '0'))->toThrow(\Core\Domain\Exception\InvalidGeoCoordException::class)
            ->and($factory('++1.2', '0'))->toThrow(InvalidGeoCoordException::class);
    }
);

it(
    'should not return a valid geographic coordinates object for invalid longitudes',
    function (): void {
        $factory = static fn(string $lat, string $lng) => static fn(): GeoCoords => new GeoCoords($lat, $lng);

        expect($factory('0', ''))->toThrow(\Core\Domain\Exception\InvalidGeoCoordException::class)
            ->and($factory('0', '180.00001'))->toThrow(InvalidGeoCoordException::class)
            ->and($factory('0', '-180.00001'))->toThrow(InvalidGeoCoordException::class)
            ->and($factory('0', '1.2.3'))->toThrow(InvalidGeoCoordException::class)
            ->and($factory('0', '1-2'))->toThrow(InvalidGeoCoordException::class)
            ->and($factory('0', '1+2'))->toThrow(InvalidGeoCoordException::class)
            ->and($factory('0', '--1.2'))->toThrow(InvalidGeoCoordException::class)
            ->and($factory('0', '++1.2'))->toThrow(\Core\Domain\Exception\InvalidGeoCoordException::class);
    }
);

it(
    'should throw an exception when the geographic coordinates object has too few coordinates',
    fn() => GeoCoords::fromString('')
)->throws(
    InvalidGeoCoordException::class,
    InvalidGeoCoordException::invalidFormat()->getMessage()
);

it(
    'should throw an exception when the geographic coordinates object has too many coordinates',
    fn() => GeoCoords::fromString('1,2,3')
)->throws(
    InvalidGeoCoordException::class,
    InvalidGeoCoordException::invalidFormat()->getMessage()
);

it(
    'should throw an exception when the geographic coordinates object has wrong values but a valid format',
    fn() => GeoCoords::fromString(',')
)->throws(
    \Core\Domain\Exception\InvalidGeoCoordException::class,
    InvalidGeoCoordException::invalidValues()->getMessage()
);

it(
    'should throw an exception when the geographic coordinates object has wrong latitude',
    fn() => GeoCoords::fromString('-91.0,100')
)->throws(
    InvalidGeoCoordException::class,
    InvalidGeoCoordException::invalidValues()->getMessage()
);

it(
    'should throw an exception when the geographic coordinates object has wrong longitude',
    fn() => GeoCoords::fromString('-90.0,200')
)->throws(
    InvalidGeoCoordException::class,
    InvalidGeoCoordException::invalidValues()->getMessage()
);
