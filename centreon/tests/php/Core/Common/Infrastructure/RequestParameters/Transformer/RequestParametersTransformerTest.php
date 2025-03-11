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
use Core\Common\Infrastructure\RequestParameters\Transformer\RequestParametersTransformer;

it('transform from query parameters', function () {
    $queryParameters = QueryParameters::create(
        [
            QueryParameter::int('contact_id', 110),
            QueryParameter::string('contact_name', 'foo_name'),
            QueryParameter::string('contact_alias', 'foo_alias')
        ]
    );
    $requestParameters = RequestParametersTransformer::transformFromQueryParameters($queryParameters);
    expect($requestParameters)->toBeArray()
        ->and($requestParameters)->toBe([
            'contact_id' => [\PDO::PARAM_INT, 110],
            'contact_name' => [\PDO::PARAM_STR, 'foo_name'],
            'contact_alias' => [\PDO::PARAM_STR, 'foo_alias']
        ]);
});

it('reverse to query parameters', function () {
    $requestParameters = [
        'contact_id' => [\PDO::PARAM_INT, 110],
        'contact_name' => [\PDO::PARAM_STR, 'foo_name'],
        'contact_alias' => [\PDO::PARAM_STR, 'foo_alias']
    ];
    $queryParameters = RequestParametersTransformer::reverseToQueryParameters($requestParameters);
    expect($queryParameters)->toBeInstanceOf(QueryParameters::class)
        ->and($queryParameters->length())->toBe(3)
        ->and($queryParameters->get('contact_id'))->toBeInstanceOf(QueryParameter::class)
        ->and($queryParameters->get('contact_id')->getType())->toBe(QueryParameterTypeEnum::INTEGER)
        ->and($queryParameters->get('contact_id')->getValue())->toBe(110)
        ->and($queryParameters->get('contact_name'))->toBeInstanceOf(QueryParameter::class)
        ->and($queryParameters->get('contact_name')->getType())->toBe(QueryParameterTypeEnum::STRING)
        ->and($queryParameters->get('contact_name')->getValue())->toBe('foo_name')
        ->and($queryParameters->get('contact_alias'))->toBeInstanceOf(QueryParameter::class)
        ->and($queryParameters->get('contact_alias')->getType())->toBe(QueryParameterTypeEnum::STRING)
        ->and($queryParameters->get('contact_alias')->getValue())->toBe('foo_alias');
});

it('reverse to query parameters with unknown PDO type', function () {
    $requestParameters = [
        'contact_id' => [\PDO::PARAM_INT, 110],
        'contact_name' => [\PDO::PARAM_STR, 'foo_name'],
        'contact_alias' => [\PDO::PARAM_STR_CHAR, 'foo_alias']
    ];
    RequestParametersTransformer::reverseToQueryParameters($requestParameters);
})->throws(TransformerException::class);
