<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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

declare(strict_types = 1);

require_once _CENTREON_PATH_ . '/src/Core/Common/Infrastructure/Repository/SqlMultipleBindTrait.php';

use Adaptation\Database\Connection\Enum\QueryParameterTypeEnum;
use \Core\Common\Infrastructure\Repository\SqlMultipleBindTrait;

/**
 * @param array<int|string, int|string> $list
 * @param string $prefix
 * @param int $bindType (optional)
 *
 * @return array{
 *     0: array<string, int|string|array{0: int|string, 1: int}>,
 *     1: string
 * }
 */
function createMultipleBindQuery(array $list, string $prefix, int $bindType = null): array
{
    return (new class {
        use SqlMultipleBindTrait
        {
            SqlMultipleBindTrait::createMultipleBindQuery as public create;
        }
    })->create($list, $prefix, $bindType);
}

/**
 * Create multiple bind parameters compatible with QueryParameterTypeEnum
 *
 * @param array<int|string, int|string|null> $values Scalar values to bind
 * @param string $prefix Placeholder prefix (no colon)
 * @param QueryParameterTypeEnum $paramType Type of binding (INTEGER|STRING)
 *
 * @return array{parameters: QueryParameter[], placeholderList: string}
 */
function createMultipleBindParameters(
    array $values,
    string $prefix,
    QueryParameterTypeEnum $paramType
): array {
    return (new class {
        use SqlMultipleBindTrait {
            SqlMultipleBindTrait::createMultipleBindParameters as public create;
        }
    })->create($values, $prefix, $paramType);
}