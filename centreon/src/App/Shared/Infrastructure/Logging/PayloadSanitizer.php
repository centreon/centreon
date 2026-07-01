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

namespace App\Shared\Infrastructure\Logging;

use App\Shared\Infrastructure\Logging\Attribute\SensitivityScanner;

/**
 * Stateless walker that masks sensitive values inside an arbitrary
 * payload (an ad-hoc logger context, a Monolog record's `context` /
 * `extra` — anything forwarded to a handler).
 *
 * Typed payloads are masked **attribute-driven**: a property without
 * `#[Sensitive]` is logged in clear, the owning class declares its
 * sensitivity explicitly. As an extra net, raw array keys are
 * additionally matched against {@see SensitiveKeywordDenylist} so that
 * an ad-hoc `$logger->error('m', ['token' => $x])` — which carries no
 * class to reflect — is still caught. Independently, in any string value
 * holding a URL **query string**, the `&`-separated parameters whose name
 * matches that same denylist are redacted in place (e.g. `…?page=2&token=***`).
 * This covers the common case of a secret logged in a request URL
 * (`extra.url`); it is best-effort and scoped to the query component —
 * secrets in the path, the fragment, or nested inside another parameter's
 * value are out of scope.
 *
 * Object values are rendered defensively to avoid leaking private
 * properties; `\Throwable` instances are returned as-is so that
 * `ExceptionFormatterProcessor` can format them downstream.
 */
final readonly class PayloadSanitizer
{
    public const MAX_DEPTH = 3;
    public const MAX_VALUE_LENGTH = 1024;
    public const RUNTIME_CLASS_KEY = '__runtime_class__';

    /**
     * @param class-string|null $contextClass class whose `#[Sensitive]`
     *                                        attributes annotate the array
     *                                        keys, or `null` when the type is
     *                                        scalar / array / unknown
     * @param bool $maskKeywordKeys whether array keys matching the shared
     *                              keyword denylist are masked. `true` for
     *                              ad-hoc / context payloads; `false` for a
     *                              Monolog `extra` bag, whose keys are set by
     *                              platform processors (e.g. `token` => an
     *                              audit descriptor of the authenticated user
     *                              — authenticated flag, roles, identifier —
     *                              not a credential) and must stay readable;
     *                              there only URL query secrets are redacted.
     *
     * @throws \ReflectionException when SensitivityScanner cannot reflect the context class
     */
    public function sanitize(mixed $data, ?string $contextClass = null, bool $maskKeywordKeys = true): mixed
    {
        return $this->walk($data, 0, $contextClass, $maskKeywordKeys);
    }

    /**
     * Recursive masking walk; `$depth` bounds the recursion. Internal —
     * callers use {@see self::sanitize()}.
     *
     * @param class-string|null $contextClass
     *
     * @throws \ReflectionException
     */
    private function walk(mixed $data, int $depth, ?string $contextClass, bool $maskKeywordKeys): mixed
    {
        if ($depth >= self::MAX_DEPTH) {
            return '{…}';
        }

        if (\is_array($data)) {
            $sensitive = [];
            $subClasses = [];
            if ($contextClass !== null && class_exists($contextClass)) {
                $scan = SensitivityScanner::scan($contextClass);
                $sensitive = $scan['sensitive'];
                $subClasses = $scan['subClasses'];
            }

            $result = [];
            foreach ($data as $key => $value) {
                if ($key === self::RUNTIME_CLASS_KEY) {
                    continue;
                }

                // `context.exception`: a raw Throwable passes through for
                // ExceptionFormatterProcessor; a structured exception array
                // (ExceptionFormatter::format output) passes through to
                // avoid depth truncation. Any other array falls through to
                // the normal masking walk.
                if ($key === 'exception' && ($value instanceof \Throwable || $this->isStructuredException($value))) {
                    $result[$key] = $value;

                    continue;
                }

                if (\is_string($key)
                    && (\in_array($key, $sensitive, true) || ($maskKeywordKeys && SensitiveKeywordDenylist::matches($key)))
                ) {
                    $result[$key] = '***';

                    continue;
                }

                $childContext = (\is_string($key) ? ($subClasses[$key] ?? null) : null);

                // When the normalizer embedded the concrete runtime class,
                // prefer it over the declared type so that #[Sensitive] on
                // the concrete class is honoured even behind an interface.
                if (\is_array($value) && isset($value[self::RUNTIME_CLASS_KEY]) && \is_string($value[self::RUNTIME_CLASS_KEY]) && class_exists($value[self::RUNTIME_CLASS_KEY])) {
                    $childContext = $value[self::RUNTIME_CLASS_KEY];
                }

                if ($childContext !== null
                    && class_exists($childContext)
                    && SensitivityScanner::scan($childContext)['classSensitive']
                ) {
                    $result[$key] = '***';

                    continue;
                }

                $result[$key] = $this->walk($value, $depth + 1, $childContext, $maskKeywordKeys);
            }

            return $result;
        }

        if (\is_object($data)) {
            return $this->sanitizeObject($data);
        }

        if (\is_string($data)) {
            return $this->capLength($this->maskSensitiveUrlQueryParameters($data));
        }

        return $data;
    }

    /**
     * Redacts, in place, the values of `&`-separated query parameters whose
     * name matches the shared keyword denylist inside a URL-like string; the
     * path and non-sensitive parameters are preserved. Scoped to the query
     * component only — the path, the fragment, alternate (`;`) separators, and
     * secrets nested inside a parameter's value are NOT inspected. No-op for
     * strings without a query part, so it is safe to run on every string value.
     */
    private function maskSensitiveUrlQueryParameters(string $value): string
    {
        $queryStart = mb_strpos($value, '?');
        if ($queryStart === false) {
            return $value;
        }

        $query = mb_substr($value, $queryStart + 1);
        if ($query === '') {
            return $value;
        }

        $masked = false;
        $pairs = explode('&', $query);
        foreach ($pairs as $index => $pair) {
            $separator = mb_strpos($pair, '=');
            if ($separator === false) {
                continue;
            }

            $name = mb_substr($pair, 0, $separator);
            if (SensitiveKeywordDenylist::matches(rawurldecode($name))) {
                $pairs[$index] = $name . '=***';
                $masked = true;
            }
        }

        return $masked
            ? mb_substr($value, 0, $queryStart + 1) . implode('&', $pairs)
            : $value;
    }

    private function capLength(string $value): string
    {
        return \mb_strlen($value) > self::MAX_VALUE_LENGTH
            ? mb_substr($value, 0, self::MAX_VALUE_LENGTH) . '…[truncated]'
            : $value;
    }

    /**
     * Recognises the output shape of {@see ExceptionFormatter::format()}:
     * `['exceptions' => [['type' => …, 'message' => …, 'trace' => …], …]]`.
     */
    private function isStructuredException(mixed $value): bool
    {
        if (! \is_array($value) || ! isset($value['exceptions']) || ! \is_array($value['exceptions'])) {
            return false;
        }

        $first = $value['exceptions'][0] ?? null;

        return \is_array($first) && isset($first['type'], $first['message'], $first['trace']);
    }

    /**
     * Defense-in-depth for objects normalisation would otherwise pass
     * through as-is (DateTime without a dedicated normalizer, Enum, VO
     * without a custom normalizer). Without this branch, a nested
     * object could carry an unmasked sensitive property all the way
     * down to the Monolog LineFormatter.
     *
     * `\Throwable` instances are returned as-is — they are the
     * dedicated input of {@see ExceptionFormatterProcessor} and must
     * survive the sanitisation walk.
     */
    private function sanitizeObject(object $data): mixed
    {
        if ($data instanceof \Throwable) {
            return $data;
        }

        if ($data instanceof \BackedEnum) {
            return $data->value;
        }

        if ($data instanceof \UnitEnum) {
            return $data->name;
        }

        if ($data instanceof \DateTimeInterface) {
            return $data->format(\DateTimeInterface::ATOM);
        }

        if ($data instanceof \Stringable) {
            return $this->capLength($this->maskSensitiveUrlQueryParameters((string) $data));
        }

        return '{' . $data::class . '}';
    }
}
