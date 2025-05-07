<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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

namespace Core\Common\Infrastructure\Repository;

use Adaptation\Database\Connection\Enum\QueryParameterTypeEnum;
use Adaptation\Database\Connection\ValueObject\QueryParameter;

trait SqlMultipleBindTrait
{
    /**
     * @param array<int|string, int|string> $list
     * @param string $prefix
     * @param int|null $bindType (optional)
     *
     * @return array{
     *     0: array<string, int|string|array{0: int|string, 1: int}>,
     *     1: string
     * }
     */
    protected function createMultipleBindQuery(array $list, string $prefix, ?int $bindType = null): array
    {
        $bindValues = [];
        $list = array_values($list); // Reset index to avoid SQL injection

        foreach ($list as $index => $id) {
            $bindValues[$prefix . $index] = ($bindType === null) ? $id : [$id, $bindType];
        }

        return [$bindValues, implode(', ', array_keys($bindValues))];
    }

    /**
     * Create multiple bind parameters compatible with QueryParameterTypeEnum
     *
     * @param array<int|string, int|string> $values Scalar values to bind
     * @param string $prefix Placeholder prefix (no colon)
     * @param QueryParameterTypeEnum $paramType Type of binding (INTEGER|STRING)
     *
     * @return array{parameters: QueryParameter[], placeholderList: string}
     */
    protected function createMultipleBindParameters(
        array $values,
        string $prefix,
        QueryParameterTypeEnum $paramType
    ): array {
        $placeholders = [];
        $parameters   = [];

        foreach (array_values($values) as $idx => $val) {
            $name = "{$prefix}_{$idx}";
            $placeholders[] = ':' . $name;
            switch ($paramType) {
                case QueryParameterTypeEnum::INTEGER:
                    $parameters[] = QueryParameter::int($name, (int) $val);
                    break;
                default:
                    $parameters[] = QueryParameter::string($name, (string) $val);
            }
        }

        return [
            'parameters'     => $parameters,
            'placeholderList'=> implode(', ', $placeholders),
        ];
    }
}
