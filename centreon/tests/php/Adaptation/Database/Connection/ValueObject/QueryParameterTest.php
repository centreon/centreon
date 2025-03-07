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

namespace Tests\Adaptation\Database\Connection\ValueObject;

use Adaptation\Database\Connection\Enum\QueryParameterTypeEnum;
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use Core\Common\Domain\Exception\ValueObjectException;

it('test query parameter value object : success instanciation with create (with type)', function () {
    $param = QueryParameter::create('name', 'value', QueryParameterTypeEnum::STRING);
    expect($param->getName())->toBe('name')
        ->and($param->getValue())->toBe('value')
        ->and($param->getType())->toBe(QueryParameterTypeEnum::STRING);
});

it('test query parameter value object : failed instanciation with create (with no type)', function () {
    $param = QueryParameter::create('name', 'value');
    expect($param->getName())->toBe('name')
        ->and($param->getValue())->toBe('value')
        ->and($param->getType())->toBeNull();
});

it('test query parameter value object : failed instanciation with create (empty name) ', function () {
    QueryParameter::create('', 'value');
})->throws(ValueObjectException::class);

it('test query parameter value object : failed instanciation with create (bad type for string) ', function () {
    QueryParameter::create('name', new \stdClass());
})->throws(ValueObjectException::class);

it('test query parameter value object : failed instanciation with create (bad type for large object) ', function () {
    QueryParameter::create('name', 0, QueryParameterTypeEnum::LARGE_OBJECT);
})->throws(ValueObjectException::class);

it('test query parameter value object : success instanciation with string type', function () {
    $param = QueryParameter::string('name', 'value');
    expect($param->getName())->toBe('name')
        ->and($param->getValue())->toBe('value')
        ->and($param->getType())->toBe(QueryParameterTypeEnum::STRING);
    $param = QueryParameter::string('age', '25');
    expect($param->getName())->toBe('age')
        ->and($param->getValue())->toBe('25')
        ->and($param->getType())->toBe(QueryParameterTypeEnum::STRING);
    $param = QueryParameter::string('active', 'false');
    expect($param->getName())->toBe('active')
        ->and($param->getValue())->toBe('false')
        ->and($param->getType())->toBe(QueryParameterTypeEnum::STRING);
    $param = QueryParameter::string('price', '9.99');
    expect($param->getName())->toBe('price')
        ->and($param->getValue())->toBe('9.99')
        ->and($param->getType())->toBe(QueryParameterTypeEnum::STRING);
});

it('test query parameter value object : failed instanciation with string type (empty name) ', function () {
    QueryParameter::string('', 'value');
})->throws(ValueObjectException::class);

it('test query parameter value object : failed instanciation with string type (bad value) ', function () {
    QueryParameter::string('name', 0);
})->throws(\TypeError::class);

it('test query parameter value object : success instanciation with int type', function () {
    $param = QueryParameter::int('name', 1);
    expect($param->getName())->toBe('name')
        ->and($param->getValue())->toBe(1)
        ->and($param->getType())->toBe(QueryParameterTypeEnum::INTEGER);
});

it('test query parameter value object : failed instanciation with int type (empty name) ', function () {
    QueryParameter::int('', 1);
})->throws(ValueObjectException::class);

it('test query parameter value object : failed instanciation with int type (bad value) ', function () {
    QueryParameter::int('name', 'value');
})->throws(\TypeError::class);

it('test query parameter value object : success instanciation with bool type', function () {
    $param = QueryParameter::bool('name', true);
    expect($param->getName())->toBe('name')
        ->and($param->getValue())->toBe(true)
        ->and($param->getType())->toBe(QueryParameterTypeEnum::BOOLEAN);
});

it('test query parameter value object : failed instanciation with bool type (empty name) ', function () {
    QueryParameter::bool('', true);
})->throws(ValueObjectException::class);

it('test query parameter value object : failed instanciation with bool type (bad value) ', function () {
    QueryParameter::bool('name', 1);
})->throws(\TypeError::class);

it('test query parameter value object : success instanciation with null type', function () {
    $param = QueryParameter::null('name');
    expect($param->getName())->toBe('name')
        ->and($param->getValue())->toBeNull()
        ->and($param->getType())->toBe(QueryParameterTypeEnum::NULL);
});

it('test query parameter value object : failed instanciation with null type (empty name) ', function () {
    QueryParameter::null('');
})->throws(ValueObjectException::class);

it('test query parameter value object : success instanciation with large object type', function () {
    $param = QueryParameter::largeObject('name', 'value');
    expect($param->getName())->toBe('name')
        ->and($param->getValue())->toBe('value')
        ->and($param->getType())->toBe(QueryParameterTypeEnum::LARGE_OBJECT);
});

it('test query parameter value object : failed instanciation with large object type (empty name) ', function () {
    QueryParameter::largeObject('', 'value');
})->throws(ValueObjectException::class);

it('test query parameter value object : failed instanciation with large object type (bad value) ', function () {
    QueryParameter::largeObject('name', 1);
})->throws(ValueObjectException::class);

it('test query parameter value object : json serialize', function () {
    $param = QueryParameter::string('name', 'value');
    expect($param->jsonSerialize())->toBe(
        [
            'name' => 'name',
            'value' => 'value',
            'type' => QueryParameterTypeEnum::STRING,
        ]
    );
});
