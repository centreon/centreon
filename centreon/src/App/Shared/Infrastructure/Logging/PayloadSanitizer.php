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
 * payload (a Command/Query, an ad-hoc logger context, a Monolog
 * record's `context` / `extra` — anything forwarded to a handler).
 *
 * Typed payloads are masked **attribute-driven**: a property without
 * `#[Sensitive]` is logged in clear, the owning class declares its
 * sensitivity explicitly. As the cross-channel net, raw array keys are
 * additionally matched against {@see SensitiveKeywordDenylist} so that
 * an ad-hoc `$logger->error('m', ['token' => $x])` — which carries no
 * class to reflect — is still caught. Independently, any string value
 * holding a URL query has the parameters whose name matches that same
 * denylist redacted in place (e.g. `…?login=x&token=***`), so a secret
 * carried in a logged URL — notably `extra.url` — does not leak.
 *
 * Object values are rendered defensively to avoid leaking private
 * properties; `\Throwable` instances are returned as-is so that
 * `ExceptionFormatterProcessor` can format them downstream.
 */
final readonly class PayloadSanitizer
{
    public const MAX_DEPTH = 3;
    public const MAX_VALUE_LENGTH = 1024;

    /**
     * @param class-string|null $contextClass class whose `#[Sensitive]`
     *                                        attributes annotate the
     *                                        array keys at the current
     *                                        depth, or `null` when the
     *                                        type at this level is
     *                                        scalar / array / unknown
     * @param bool $maskKeywordKeys whether array keys matching the shared
     *                              keyword denylist are masked. `true` for
     *                              ad-hoc / context payloads; `false` for a
     *                              Monolog `extra` bag, whose keys are set
     *                              by platform processors (e.g. `token` =>
     *                              the authenticated user) and must stay
     *                              readable — there only URL query secrets
     *                              are redacted.
     *
     * @throws \ReflectionException when SensitivityScanner cannot reflect the context class
     */
    public function sanitize(mixed $data, int $depth = 0, ?string $contextClass = null, bool $maskKeywordKeys = true): mixed
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
                // `context.exception` carries one of two legitimate shapes: a raw
                // Throwable (handed to ExceptionFormatterProcessor downstream) or an
                // already-structured exception array (ExceptionFormatter::format),
                // whose nested chain is deeper than MAX_DEPTH and must not be
                // truncated. Leave both intact; any other type under this key falls
                // through to the normal masking walk.
                if ($key === 'exception' && ($value instanceof \Throwable || \is_array($value))) {
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

                // A `#[Sensitive]` class masks every value typed as it,
                // so the whole sub-payload is redacted without descending.
                if ($childContext !== null
                    && class_exists($childContext)
                    && SensitivityScanner::scan($childContext)['classSensitive']
                ) {
                    $result[$key] = '***';

                    continue;
                }

                $result[$key] = $this->sanitize($value, $depth + 1, $childContext, $maskKeywordKeys);
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
     * Redacts the values of query parameters whose name matches the shared
     * keyword denylist inside a URL-like string, in place — the path and
     * non-sensitive parameters are preserved. No-op for strings without a
     * query part, so it is safe to run on every string value.
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
