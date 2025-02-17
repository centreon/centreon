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

use Adaptation\Database\Connection\Adapter\Dbal\Transformer\DbalParameterTypeTransformer;
use Adaptation\Database\Connection\Enum\QueryParameterTypeEnum;
use Core\Common\Domain\Exception\TransformerException;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\ParameterType as DbalParameterType;

it('test DbalParameterTypeTranformer : transform', function () {
    $queryParameterTypeEnum = QueryParameterTypeEnum::STRING;
    $dbalParameterType = DbalParameterTypeTransformer::transform($queryParameterTypeEnum);
    expect($dbalParameterType)->toBe(DbalParameterType::STRING);

    $queryParameterTypeEnum = QueryParameterTypeEnum::INTEGER;
    $dbalParameterType = DbalParameterTypeTransformer::transform($queryParameterTypeEnum);
    expect($dbalParameterType)->toBe(DbalParameterType::INTEGER);

    $queryParameterTypeEnum = QueryParameterTypeEnum::BOOLEAN;
    $dbalParameterType = DbalParameterTypeTransformer::transform($queryParameterTypeEnum);
    expect($dbalParameterType)->toBe(DbalParameterType::BOOLEAN);

    $queryParameterTypeEnum = QueryParameterTypeEnum::NULL;
    $dbalParameterType = DbalParameterTypeTransformer::transform($queryParameterTypeEnum);
    expect($dbalParameterType)->toBe(DbalParameterType::NULL);

    $queryParameterTypeEnum = QueryParameterTypeEnum::LARGE_OBJECT;
    $dbalParameterType = DbalParameterTypeTransformer::transform($queryParameterTypeEnum);
    expect($dbalParameterType)->toBe(DbalParameterType::LARGE_OBJECT);
});

it('test DbalParameterTypeTranformer : reverse', function () {
    $dbalParameterType = DbalParameterType::STRING;
    $queryParameterTypeEnum = DbalParameterTypeTransformer::reverse($dbalParameterType);
    expect($queryParameterTypeEnum)->toBe(QueryParameterTypeEnum::STRING);

    $dbalParameterType = DbalParameterType::INTEGER;
    $queryParameterTypeEnum = DbalParameterTypeTransformer::reverse($dbalParameterType);
    expect($queryParameterTypeEnum)->toBe(QueryParameterTypeEnum::INTEGER);

    $dbalParameterType = DbalParameterType::BOOLEAN;
    $queryParameterTypeEnum = DbalParameterTypeTransformer::reverse($dbalParameterType);
    expect($queryParameterTypeEnum)->toBe(QueryParameterTypeEnum::BOOLEAN);

    $dbalParameterType = DbalParameterType::NULL;
    $queryParameterTypeEnum = DbalParameterTypeTransformer::reverse($dbalParameterType);
    expect($queryParameterTypeEnum)->toBe(QueryParameterTypeEnum::NULL);

    $dbalParameterType = DbalParameterType::LARGE_OBJECT;
    $queryParameterTypeEnum = DbalParameterTypeTransformer::reverse($dbalParameterType);
    expect($queryParameterTypeEnum)->toBe(QueryParameterTypeEnum::LARGE_OBJECT);
});

it('test DbalParameterTypeTranformer : reverse with exception', function () {
    DbalParameterTypeTransformer::reverse(ParameterType::ASCII);
})->throws(TransformerException::class);
