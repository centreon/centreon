<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

declare(strict_types=1);

namespace Core\Common\Application\Type;

/**
 * This class is used as a dedicated type for the meaning of "no value".
 *
 * You absolutely need it when the `null` type has a meaning from an application point of view.
 *
 * <pre>
 * // Example DTO
 * class MyDto {
 *     public function __construct(
 *         public NoValue|null|int $number = new NoValue()
 *     )
 *     {}
 * }
 *
 * // Classical coalesce with null as we use to encounter
 * $value = $dto->number ?? $defaultValue;
 *
 * // Replaced with the NoValue behaviour
 * $value = NoValue::coalesce($dto->number, $defaultValue);
 *
 * </pre>
 */
final class NoValue
{
    /**
     * This method is here to mimic the null coalescing operator of php `??` but with this object.
     *
     * @see https://www.php.net/manual/en/language.operators.comparison.php#language.operators.comparison.coalesce
     *
     * @template T of int|bool|float|string|array|null
     *
     * @param self|T $value
     * @param T $default
     *
     * @return T
     */
    public static function coalesce(
        self|null|int|bool|float|string|array $value,
        null|int|bool|float|string|array $default,
    ): null|int|bool|float|string|array {
        return $value instanceof self
            ? $default
            : $value;
    }
}
