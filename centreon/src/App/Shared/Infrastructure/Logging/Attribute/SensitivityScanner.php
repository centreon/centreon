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

declare(strict_types=1);

namespace App\Shared\Infrastructure\Logging\Attribute;

use App\Shared\Domain\Logging\Attribute\Sensitive;

/**
 * Reflection-based scanner that resolves which properties of a class
 * carry the `#[Sensitive]` attribute and, for each property, the
 * nested class type the sanitiser must descend into so an annotation
 * placed on a sub-aggregate is honoured the same way as one on the
 * top-level class.
 *
 * Result is cached per class — every dispatch / record produced by a
 * given class reuses the same {@see \ReflectionClass} walk.
 */
final class SensitivityScanner
{
    /**
     * @var array<class-string, array{
     *     sensitive: list<string>,
     *     subClasses: array<string, class-string>
     * }>
     */
    private static array $cache = [];

    /**
     * @param class-string $class
     *
     * @return array{
     *     sensitive: list<string>,
     *     subClasses: array<string, class-string>
     * }
     */
    public static function scan(string $class): array
    {
        if (isset(self::$cache[$class])) {
            return self::$cache[$class];
        }

        $sensitive = [];
        $subClasses = [];

        $reflection = new \ReflectionClass($class);
        foreach ($reflection->getProperties() as $property) {
            $name = $property->getName();

            if ($property->getAttributes(Sensitive::class) !== []) {
                $sensitive[] = $name;
            }

            $type = $property->getType();
            if ($type instanceof \ReflectionNamedType && ! $type->isBuiltin()) {
                /** @var class-string $typeName */
                $typeName = $type->getName();
                $subClasses[$name] = $typeName;
            }
        }

        return self::$cache[$class] = [
            'sensitive' => $sensitive,
            'subClasses' => $subClasses,
        ];
    }

    /**
     * Reset the cache. Reserved for tests — production callers must
     * never invoke this, the scan is otherwise stable for the lifetime
     * of a process.
     *
     * @internal
     */
    public static function reset(): void
    {
        self::$cache = [];
    }
}
