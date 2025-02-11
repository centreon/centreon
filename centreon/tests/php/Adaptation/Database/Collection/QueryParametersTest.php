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


use Adaptation\Database\Collection\QueryParameters;
use Adaptation\Database\ValueObject\QueryParameter;
use Core\Common\Domain\Exception\CollectionException;

it('test query parameters collection : add a query parameter with a good type', function () {
    $queryParameters = new QueryParameters();
    $param = QueryParameter::string('name_string', 'value');
    $queryParameters->add('name_string', $param);
    expect($queryParameters->length())->toBe(1)
        ->and($queryParameters->get('name_string'))->toBe($param);
});

it('test query parameters collection : add a query parameter with a bad type', function () {
    $param = new stdClass();
    $queryParameters = new QueryParameters();
    $queryParameters->add('name', $param);
})->throws(CollectionException::class);

it('test query parameters collection : create with good type', function () {
    $param = [
        QueryParameter::string('name_string', 'value'),
        QueryParameter::int('name_int', 1)
    ];
    $queryParameters = QueryParameters::create($param);
    expect($queryParameters->length())->toBe(2)
        ->and($queryParameters->get('name_string'))->toBe($param[0])
        ->and($queryParameters->get('name_int'))->toBe($param[1]);
});

it('test query parameters collection : create with bad type', function () {
    $param = [
        QueryParameter::string('name_string', 'value'),
        new stdClass()
    ];
    QueryParameters::create($param);
})->throws(CollectionException::class);

it('test query parameters collection : get query parameters with int type', function () {
    $param = [
        QueryParameter::string('name_string', 'value'),
        QueryParameter::int('name_int', 1),
        QueryParameter::null('name_null'),
        QueryParameter::bool('name_bool', true),
        QueryParameter::largeObject('name_large_object', 'hjghjgjhgkhjgkhghgh7d8f7sdf7sdf7sd7fds'),
    ];
    $queryParameters = QueryParameters::create($param);
    expect($queryParameters->getIntQueryParameters()->length())->toBe(1)
        ->and($queryParameters->getIntQueryParameters()->has('name_int'))->toBeTrue();
});

it('test query parameters collection : get query parameters with string type', function () {
    $param = [
        QueryParameter::string('name_string', 'value'),
        QueryParameter::int('name_int', 1),
        QueryParameter::null('name_null'),
        QueryParameter::bool('name_bool', true),
        QueryParameter::largeObject('name_large_object', 'hjghjgjhgkhjgkhghgh7d8f7sdf7sdf7sd7fds'),
    ];
    $queryParameters = QueryParameters::create($param);
    expect($queryParameters->getStringQueryParameters()->length())->toBe(1)
        ->and($queryParameters->getStringQueryParameters()->has('name_string'))->toBeTrue();
});

it('test query parameters collection : get query parameters with bool type', function () {
    $param = [
        QueryParameter::string('name_string', 'value'),
        QueryParameter::int('name_int', 1),
        QueryParameter::null('name_null'),
        QueryParameter::bool('name_bool', true),
        QueryParameter::largeObject('name_large_object', 'hjghjgjhgkhjgkhghgh7d8f7sdf7sdf7sd7fds'),
    ];
    $queryParameters = QueryParameters::create($param);
    expect($queryParameters->getBoolQueryParameters()->length())->toBe(1)
        ->and($queryParameters->getBoolQueryParameters()->has('name_bool'))->toBeTrue();
});

it('test query parameters collection : get query parameters with null type', function () {
    $param = [
        QueryParameter::string('name_string', 'value'),
        QueryParameter::int('name_int', 1),
        QueryParameter::null('name_null'),
        QueryParameter::bool('name_bool', true),
        QueryParameter::largeObject('name_large_object', 'hjghjgjhgkhjgkhghgh7d8f7sdf7sdf7sd7fds'),
    ];
    $queryParameters = QueryParameters::create($param);
    expect($queryParameters->getNullQueryParameters()->length())->toBe(1)
        ->and($queryParameters->getNullQueryParameters()->has('name_null'))->toBeTrue();
});

it('test query parameters collection : get query parameters with large object type', function () {
    $param = [
        QueryParameter::string('name_string', 'value'),
        QueryParameter::int('name_int', 1),
        QueryParameter::null('name_null'),
        QueryParameter::bool('name_bool', true),
        QueryParameter::largeObject('name_large_object', 'hjghjgjhgkhjgkhghgh7d8f7sdf7sdf7sd7fds'),
    ];
    $queryParameters = QueryParameters::create($param);
    expect($queryParameters->getLargeObjectQueryParameters()->length())->toBe(1)
        ->and($queryParameters->getLargeObjectQueryParameters()->has('name_large_object'))->toBeTrue();
});
