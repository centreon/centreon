<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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

namespace Tests\Adaptation\Database\Connection\Collection;

use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use Core\Common\Domain\Exception\CollectionException;

it('add a query parameter with a good type', function () {
    $queryParameters = new QueryParameters();
    $param = QueryParameter::string('name_string', 'value');
    $queryParameters->add('name_string', $param);
    expect($queryParameters->length())->toBe(1)
        ->and($queryParameters->get('name_string'))->toBe($param);
});

it('add a query parameter with a bad type', function () {
    $param = new \stdClass();
    $queryParameters = new QueryParameters();
    $queryParameters->add('name', $param);
})->throws(CollectionException::class);

it('create with good type', function () {
    $param = [
        'name_string' => QueryParameter::string('name_string', 'value'),
        'name_int' => QueryParameter::int('name_int', 1)
    ];
    $queryParameters = \Adaptation\Database\Connection\Collection\QueryParameters::create($param);
    expect($queryParameters->length())->toBe(2)
        ->and($queryParameters->get('name_string'))->toBe($param['name_string'])
        ->and($queryParameters->get('name_int'))->toBe($param['name_int']);
});

it('create with bad type', function () {
    $param = [
        'name_string' => QueryParameter::string('name_string', 'value'),
        'name_int' => new \stdClass()
    ];
    \Adaptation\Database\Connection\Collection\QueryParameters::create($param);
})->throws(CollectionException::class);

it('get query parameters with int type', function () {
    $param = [
        'name_string' => QueryParameter::string('name_string', 'value'),
        'name_int' => QueryParameter::int('name_int', 1),
        'name_null' => QueryParameter::null('name_null'),
        'name_bool' => QueryParameter::bool('name_bool', true),
        'name_large_object' => QueryParameter::largeObject(
            'name_large_object',
            'hjghjgjhgkhjgkhghgh7d8f7sdf7sdf7sd7fds'
        ),
    ];
    $queryParameters = QueryParameters::create($param);
    expect($queryParameters->getIntQueryParameters()->length())->toBe(1)
        ->and($queryParameters->getIntQueryParameters()->has('name_int'))->toBeTrue();
});

it('get query parameters with string type', function () {
    $param = [
        'name_string' => QueryParameter::string('name_string', 'value'),
        'name_int' => QueryParameter::int('name_int', 1),
        'name_null' => QueryParameter::null('name_null'),
        'name_bool' => QueryParameter::bool('name_bool', true),
        'name_large_object' => QueryParameter::largeObject(
            'name_large_object',
            'hjghjgjhgkhjgkhghgh7d8f7sdf7sdf7sd7fds'
        ),
    ];
    $queryParameters = \Adaptation\Database\Connection\Collection\QueryParameters::create($param);
    expect($queryParameters->getStringQueryParameters()->length())->toBe(1)
        ->and($queryParameters->getStringQueryParameters()->has('name_string'))->toBeTrue();
});

it('get query parameters with bool type', function () {
    $param = [
        'name_string' => QueryParameter::string('name_string', 'value'),
        'name_int' => QueryParameter::int('name_int', 1),
        'name_null' => QueryParameter::null('name_null'),
        'name_bool' => QueryParameter::bool('name_bool', true),
        'name_large_object' => QueryParameter::largeObject(
            'name_large_object',
            'hjghjgjhgkhjgkhghgh7d8f7sdf7sdf7sd7fds'
        ),
    ];
    $queryParameters = \Adaptation\Database\Connection\Collection\QueryParameters::create($param);
    expect($queryParameters->getBoolQueryParameters()->length())->toBe(1)
        ->and($queryParameters->getBoolQueryParameters()->has('name_bool'))->toBeTrue();
});

it('get query parameters with null type', function () {
    $param = [
        'name_string' => QueryParameter::string('name_string', 'value'),
        'name_int' => QueryParameter::int('name_int', 1),
        'name_null' => QueryParameter::null('name_null'),
        'name_bool' => QueryParameter::bool('name_bool', true),
        'name_large_object' => QueryParameter::largeObject(
            'name_large_object',
            'hjghjgjhgkhjgkhghgh7d8f7sdf7sdf7sd7fds'
        ),
    ];
    $queryParameters = QueryParameters::create($param);
    expect($queryParameters->getNullQueryParameters()->length())->toBe(1)
        ->and($queryParameters->getNullQueryParameters()->has('name_null'))->toBeTrue();
});

it('get query parameters with large object type', function () {
    $param = [
        'name_string' => QueryParameter::string('name_string', 'value'),
        'name_int' => QueryParameter::int('name_int', 1),
        'name_null' => QueryParameter::null('name_null'),
        'name_bool' => QueryParameter::bool('name_bool', true),
        'name_large_object' => QueryParameter::largeObject(
            'name_large_object',
            'hjghjgjhgkhjgkhghgh7d8f7sdf7sdf7sd7fds'
        ),
    ];
    $queryParameters = \Adaptation\Database\Connection\Collection\QueryParameters::create($param);
    expect($queryParameters->getLargeObjectQueryParameters()->length())->toBe(1)
        ->and($queryParameters->getLargeObjectQueryParameters()->has('name_large_object'))->toBeTrue();
});
