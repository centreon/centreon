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
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

/**
 * Scans the `#[Sensitive]` markers and nested class types of a class.
 * `#[Sensitive]` is honoured on three targets:
 *
 *  - **property** — the property value is masked;
 *  - **method** — the accessor key it exposes (`getX`/`isX`/`hasX`/`canX`
 *    → `x`, otherwise the raw method name) is masked, matching how the
 *    Symfony normalizer derives keys from getters;
 *  - **class** — every value typed as that class is masked wholesale
 *    (`classSensitive`), so the sanitiser never descends into it.
 *
 * Recorded keys are snake_cased like the payload keys the framework's global
 * name converter produces, so the sanitiser's name match fires (`ssoTicket`
 * surfaces as `sso_ticket`).
 *
 * Result cached per class to share the {@see \ReflectionClass} walk
 * across every record produced by the same payload.
 */
final class SensitivityScanner
{
    /**
     * @var array<class-string, array{
     *     sensitive: list<string>,
     *     subClasses: array<string, class-string>,
     *     classSensitive: bool
     * }>
     */
    private static array $cache = [];

    /**
     * @param class-string $class
     *
     * @throws \ReflectionException when the class does not exist or cannot be reflected
     *
     * @return array{
     *     sensitive: list<string>,
     *     subClasses: array<string, class-string>,
     *     classSensitive: bool
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
        $classSensitive = $reflection->getAttributes(Sensitive::class) !== [];
        $keyConverter = new CamelCaseToSnakeCaseNameConverter();

        foreach ($reflection->getProperties() as $property) {
            $name = $keyConverter->normalize($property->getName());

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

        foreach ($reflection->getMethods() as $method) {
            if ($method->getAttributes(Sensitive::class) === []) {
                continue;
            }

            $key = $keyConverter->normalize(self::accessorKey($method->getName()));
            if (! \in_array($key, $sensitive, true)) {
                $sensitive[] = $key;
            }
        }

        return self::$cache[$class] = [
            'sensitive' => $sensitive,
            'subClasses' => $subClasses,
            'classSensitive' => $classSensitive,
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

    /**
     * Derives the payload key a method exposes, mirroring the Symfony
     * normalizer: `getPasscode` → `passcode`, `isActive` → `active`,
     * `hasToken` → `token`, `canAdmin` → `admin`; any other method
     * keeps its own name.
     */
    private static function accessorKey(string $method): string
    {
        foreach (['get', 'is', 'has', 'can'] as $prefix) {
            if (\str_starts_with($method, $prefix) && \mb_strlen($method) > \mb_strlen($prefix)) {
                return \lcfirst(\mb_substr($method, \mb_strlen($prefix)));
            }
        }

        return $method;
    }
}
