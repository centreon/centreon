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

namespace Tests\Adaptation\Database\Connection\Adapter\Pdo\Transformer;

use Adaptation\Database\Connection\Adapter\Pdo\Transformer\PdoParameterTypeTransformer;
use Adaptation\Database\Connection\Enum\QueryParameterTypeEnum;
use Core\Common\Domain\Exception\TransformerException;

it('transform from query parameters', function (QueryParameterTypeEnum $queryType, int $pdoType) {
    $type = PdoParameterTypeTransformer::transformFromQueryParameterType($queryType);
    expect($type)->toBeInt()->toBe($pdoType);
})->with([
    [QueryParameterTypeEnum::STRING, \PDO::PARAM_STR],
    [QueryParameterTypeEnum::INTEGER, \PDO::PARAM_INT],
    [QueryParameterTypeEnum::LARGE_OBJECT, \PDO::PARAM_LOB],
    [QueryParameterTypeEnum::NULL, \PDO::PARAM_NULL],
    [QueryParameterTypeEnum::BOOLEAN, \PDO::PARAM_BOOL],
]);

it('reverse to query parameters', function (int $pdoType, QueryParameterTypeEnum $queryType) {
    $type = PdoParameterTypeTransformer::reverseToQueryParameterType($pdoType);
    expect($type)->toBe($queryType);
})->with([
    [\PDO::PARAM_STR, QueryParameterTypeEnum::STRING],
    [\PDO::PARAM_INT, QueryParameterTypeEnum::INTEGER],
    [\PDO::PARAM_LOB, QueryParameterTypeEnum::LARGE_OBJECT],
    [\PDO::PARAM_NULL, QueryParameterTypeEnum::NULL],
    [\PDO::PARAM_BOOL, QueryParameterTypeEnum::BOOLEAN],
]);

it('reverse to query parameters with a bad pdo type', function () {
    $type = PdoParameterTypeTransformer::reverseToQueryParameterType(999999);
})->throws(TransformerException::class);
