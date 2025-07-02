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

namespace Tests\Adaptation\Database\Connection\Adapter\Dbal\Transformer;

use Adaptation\Database\Connection\Collection\QueryParameters;
use Adaptation\Database\Connection\Adapter\Dbal\Transformer\DbalParametersTransformer;
use Adaptation\Database\Connection\Enum\QueryParameterTypeEnum;
use Adaptation\Database\Connection\ValueObject\QueryParameter;
use Doctrine\DBAL\ParameterType as DbalParameterType;

it('transform from query parameters', function () {
    [$params, $types] = DbalParametersTransformer::transformFromQueryParameters(
        QueryParameters::create(
            [
                QueryParameter::create('host_id', 1, QueryParameterTypeEnum::INTEGER),
                QueryParameter::create('host_name', 'foo_server', QueryParameterTypeEnum::STRING),
                QueryParameter::create('host_enabled', true, QueryParameterTypeEnum::BOOLEAN),
                QueryParameter::create('host_blob', 'fsfqsd4f5qsdff325154', QueryParameterTypeEnum::LARGE_OBJECT),
                QueryParameter::create('host_null', null, QueryParameterTypeEnum::NULL),
            ]
        )
    );
    expect($params)->toBeArray()->toBe(
        [
            'host_id' => 1,
            'host_name' => 'foo_server',
            'host_enabled' => true,
            'host_blob' => 'fsfqsd4f5qsdff325154',
            'host_null' => null
        ]
    )
        ->and($types)->toBeArray()->toBe(
            [
                'host_id' => DbalParameterType::INTEGER,
                'host_name' => DbalParameterType::STRING,
                'host_enabled' => DbalParameterType::BOOLEAN,
                'host_blob' => DbalParameterType::LARGE_OBJECT,
                'host_null' => DbalParameterType::NULL
            ]
        );
});

it('transform from query parameters with : before key', function () {
    [$params, $types] = DbalParametersTransformer::transformFromQueryParameters(
        QueryParameters::create(
            [
                QueryParameter::create(':host_id', 1, QueryParameterTypeEnum::INTEGER),
                QueryParameter::create(':host_name', 'foo_server', QueryParameterTypeEnum::STRING),
                QueryParameter::create(':host_enabled', true, QueryParameterTypeEnum::BOOLEAN),
                QueryParameter::create(':host_blob', 'fsfqsd4f5qsdff325154', QueryParameterTypeEnum::LARGE_OBJECT),
                QueryParameter::create(':host_null', null, QueryParameterTypeEnum::NULL),
            ]
        )
    );
    expect($params)->toBeArray()->toBe(
        [
            'host_id' => 1,
            'host_name' => 'foo_server',
            'host_enabled' => true,
            'host_blob' => 'fsfqsd4f5qsdff325154',
            'host_null' => null
        ]
    )
        ->and($types)->toBeArray()->toBe(
            [
                'host_id' => DbalParameterType::INTEGER,
                'host_name' => DbalParameterType::STRING,
                'host_enabled' => DbalParameterType::BOOLEAN,
                'host_blob' => DbalParameterType::LARGE_OBJECT,
                'host_null' => DbalParameterType::NULL
            ]
        );
});

it('reverse to query parameters', function () {
    $queryParameters = DbalParametersTransformer::reverseToQueryParameters(
        [
            'host_id' => 1,
            'host_name' => 'foo_server',
            'host_enabled' => true,
            'host_blob' => 'fsfqsd4f5qsdff325154',
            'host_null' => null
        ],
        [
            'host_id' => DbalParameterType::INTEGER,
            'host_name' => DbalParameterType::STRING,
            'host_enabled' => DbalParameterType::BOOLEAN,
            'host_blob' => DbalParameterType::LARGE_OBJECT,
            'host_null' => DbalParameterType::NULL
        ]
    );
    expect($queryParameters)->toBeInstanceOf(QueryParameters::class)
        ->and($queryParameters->toArray())->toBeArray()->toHaveKeys(
            [
                'host_id',
                'host_name',
                'host_enabled',
                'host_blob',
                'host_null'
            ]
        )
        ->and($queryParameters->get('host_id'))->toBeInstanceOf(QueryParameter::class)
        ->and($queryParameters->get('host_id')->getName())->toBe('host_id')
        ->and($queryParameters->get('host_id')->getValue())->toBe(1)
        ->and($queryParameters->get('host_id')->getType())->toBe(QueryParameterTypeEnum::INTEGER)
        ->and($queryParameters->get('host_name'))->toBeInstanceOf(QueryParameter::class)
        ->and($queryParameters->get('host_name')->getName())->toBe('host_name')
        ->and($queryParameters->get('host_name')->getValue())->toBe('foo_server')
        ->and($queryParameters->get('host_name')->getType())->toBe(QueryParameterTypeEnum::STRING)
        ->and($queryParameters->get('host_enabled'))->toBeInstanceOf(QueryParameter::class)
        ->and($queryParameters->get('host_enabled')->getName())->toBe('host_enabled')
        ->and($queryParameters->get('host_enabled')->getValue())->toBe(true)
        ->and($queryParameters->get('host_enabled')->getType())->toBe(QueryParameterTypeEnum::BOOLEAN)
        ->and($queryParameters->get('host_blob'))->toBeInstanceOf(QueryParameter::class)
        ->and($queryParameters->get('host_blob')->getName())->toBe('host_blob')
        ->and($queryParameters->get('host_blob')->getValue())->toBe('fsfqsd4f5qsdff325154')
        ->and($queryParameters->get('host_blob')->getType())->toBe(QueryParameterTypeEnum::LARGE_OBJECT)
        ->and($queryParameters->get('host_null'))->toBeInstanceOf(QueryParameter::class)
        ->and($queryParameters->get('host_null')->getName())->toBe('host_null')
        ->and($queryParameters->get('host_null')->getValue())->toBeNull()
        ->and($queryParameters->get('host_null')->getType())->toBe(QueryParameterTypeEnum::NULL);
});
