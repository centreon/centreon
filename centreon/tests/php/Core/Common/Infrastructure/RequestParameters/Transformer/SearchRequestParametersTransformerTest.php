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

use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\Enum\QueryParameterTypeEnum;
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use Core\Common\Domain\Exception\TransformerException;
use Core\Common\Infrastructure\RequestParameters\Transformer\SearchRequestParametersTransformer;

it('transform from query parameters', function () {
    $queryParameters = QueryParameters::create(
        [
            QueryParameter::int('contact_id', 110),
            QueryParameter::string('contact_name', 'foo_name'),
            QueryParameter::string('contact_alias', 'foo_alias'),
            QueryParameter::bool('contact_active', true),
            QueryParameter::bool('contact_is_admin', false),
            QueryParameter::null('contact_email'),
            QueryParameter::largeObject('contact_token', 'fghfhffhhj545d4f4sfdsfsdfdsfs4fsdf'),
        ]
    );
    $requestParameters = SearchRequestParametersTransformer::transformFromQueryParameters($queryParameters);
    expect($requestParameters)->toBeArray()->toHaveCount(7)
        ->and($requestParameters)->toBe([
            'contact_id' => [\PDO::PARAM_INT => 110],
            'contact_name' => [\PDO::PARAM_STR => 'foo_name'],
            'contact_alias' => [\PDO::PARAM_STR => 'foo_alias'],
            'contact_active' => [\PDO::PARAM_BOOL => true],
            'contact_is_admin' => [\PDO::PARAM_BOOL => false],
            'contact_email' => [\PDO::PARAM_NULL => null],
            'contact_token' => [\PDO::PARAM_LOB => 'fghfhffhhj545d4f4sfdsfsdfdsfs4fsdf'],
        ]);
});

it('reverse to query parameters', function () {
    $requestParameters = [
        'contact_id' => [\PDO::PARAM_INT => 110],
        'contact_name' => [\PDO::PARAM_STR => 'foo_name'],
        'contact_alias' => [\PDO::PARAM_STR => 'foo_alias'],
        'contact_active' => [\PDO::PARAM_BOOL => true],
        'contact_is_admin' => [\PDO::PARAM_BOOL => false],
        'contact_email' => [\PDO::PARAM_NULL => null],
        'contact_token' => [\PDO::PARAM_LOB => 'fghfhffhhj545d4f4sfdsfsdfdsfs4fsdf'],
    ];
    $queryParameters = SearchRequestParametersTransformer::reverseToQueryParameters($requestParameters);
    expect($queryParameters)->toBeInstanceOf(QueryParameters::class)
        ->and($queryParameters->length())->toBe(7)
        ->and($queryParameters->get('contact_id'))->toBeInstanceOf(QueryParameter::class)
        ->and($queryParameters->get('contact_id')->getType())->toBe(QueryParameterTypeEnum::INTEGER)
        ->and($queryParameters->get('contact_id')->getValue())->toBe(110)
        ->and($queryParameters->get('contact_name'))->toBeInstanceOf(QueryParameter::class)
        ->and($queryParameters->get('contact_name')->getType())->toBe(QueryParameterTypeEnum::STRING)
        ->and($queryParameters->get('contact_name')->getValue())->toBe('foo_name')
        ->and($queryParameters->get('contact_alias'))->toBeInstanceOf(QueryParameter::class)
        ->and($queryParameters->get('contact_alias')->getType())->toBe(QueryParameterTypeEnum::STRING)
        ->and($queryParameters->get('contact_alias')->getValue())->toBe('foo_alias')
        ->and($queryParameters->get('contact_active'))->toBeInstanceOf(QueryParameter::class)
        ->and($queryParameters->get('contact_active')->getType())->toBe(QueryParameterTypeEnum::BOOLEAN)
        ->and($queryParameters->get('contact_active')->getValue())->toBeTrue()
        ->and($queryParameters->get('contact_is_admin'))->toBeInstanceOf(QueryParameter::class)
        ->and($queryParameters->get('contact_is_admin')->getType())->toBe(QueryParameterTypeEnum::BOOLEAN)
        ->and($queryParameters->get('contact_is_admin')->getValue())->toBeFalse()
        ->and($queryParameters->get('contact_email'))->toBeInstanceOf(QueryParameter::class)
        ->and($queryParameters->get('contact_email')->getType())->toBe(QueryParameterTypeEnum::NULL)
        ->and($queryParameters->get('contact_email')->getValue())->toBeNull()
        ->and($queryParameters->get('contact_token'))->toBeInstanceOf(QueryParameter::class)
        ->and($queryParameters->get('contact_token')->getType())->toBe(QueryParameterTypeEnum::LARGE_OBJECT)
        ->and($queryParameters->get('contact_token')->getValue())->toBe('fghfhffhhj545d4f4sfdsfsdfdsfs4fsdf');
});

it('reverse to query parameters with unknown PDO type', function () {
    $requestParameters = [
        'contact_id' => [\PDO::PARAM_INT => 110],
        'contact_name' => [\PDO::PARAM_STR => 'foo_name'],
        'contact_alias' => [\PDO::PARAM_STR_CHAR => 'foo_alias'],
        'contact_active' => [\PDO::PARAM_BOOL => true],
        'contact_is_admin' => [\PDO::PARAM_BOOL => false],
        'contact_email' => [\PDO::PARAM_NULL => null],
        'contact_token' => [\PDO::PARAM_LOB => 'fghfhffhhj545d4f4sfdsfsdfdsfs4fsdf'],
    ];
    SearchRequestParametersTransformer::reverseToQueryParameters($requestParameters);
})->throws(TransformerException::class);
