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

use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Turns a bus message into a redacted, log-safe array payload: sensitive
 * keys masked, oversized strings capped, and objects that the inner
 * normalizer would pass through (enums, datetimes, stringables) rendered
 * as readable scalars so private state never leaks to the log.
 */
final class LogPayloadNormalizer
{
    /**
     * Matched via str_contains on the lowercased payload key — intentionally
     * permissive: any field name that *contains* one of these tokens is
     * masked (e.g. `oauth_authorization_url`, `password_changed_at`,
     * `customer_token_id`). Over-masking is preferred to under-masking so a
     * variant we did not anticipate can't leak a real secret. A future
     * `#[Sensitive]` / `#[NotSensitive]` attribute layer will let callers
     * override per-property (tracked in MON-199097).
     */
    private const SENSITIVE_KEYWORDS = ['password', 'token', 'secret', 'api_key', 'authorization', 'credential', 'private_key'];
    private const MAX_VALUE_LENGTH = 1024;

    /**
     * Memoises the sensitive-or-not verdict per key to skip the keyword
     * scan on repeated fields.
     *
     * @var array<string, bool>
     */
    private array $sensitivityCache = [];

    public function __construct(
        private readonly NormalizerInterface $normalizer,
    ) {
    }

    /**
     * @throws NonArrayNormalizationException if the inner normalizer returns a non-array value
     * @throws ExceptionInterface from the inner normalizer
     * @return array<string, mixed>
     */
    public function normalize(object $message): array
    {
        $data = $this->normalizer->normalize($message);

        if (! \is_array($data)) {
            throw new NonArrayNormalizationException($message::class, get_debug_type($data));
        }

        /** @var array<string, mixed> */
        return $this->sanitize($data);
    }

    private function sanitize(mixed $data): mixed
    {
        if (\is_array($data)) {
            $result = [];
            foreach ($data as $key => $value) {
                if (\is_string($key) && $this->isSensitiveKey($key)) {
                    $result[$key] = '***';

                    continue;
                }
                $result[$key] = $this->sanitize($value);
            }

            return $result;
        }

        if (\is_object($data)) {
            return $this->sanitizeObject($data);
        }

        if (\is_string($data) && \mb_strlen($data) > self::MAX_VALUE_LENGTH) {
            return mb_substr($data, 0, self::MAX_VALUE_LENGTH) . '…[truncated]';
        }

        return $data;
    }

    /**
     * Defense-in-depth for objects NormalizerInterface would otherwise
     * pass through as-is (DateTime without a dedicated normalizer, Enum,
     * VO without a custom normalizer). Without this branch, a nested
     * object could carry an unmasked sensitive property all the way down
     * to the Monolog LineFormatter.
     */
    private function sanitizeObject(object $data): mixed
    {
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
            $string = (string) $data;

            return \mb_strlen($string) > self::MAX_VALUE_LENGTH
                ? mb_substr($string, 0, self::MAX_VALUE_LENGTH) . '…[truncated]'
                : $string;
        }

        return '{' . $data::class . '}';
    }

    private function isSensitiveKey(string $key): bool
    {
        if (isset($this->sensitivityCache[$key])) {
            return $this->sensitivityCache[$key];
        }

        $lower = mb_strtolower($key);

        foreach (self::SENSITIVE_KEYWORDS as $keyword) {
            if (str_contains($lower, $keyword)) {
                return $this->sensitivityCache[$key] = true;
            }
        }

        return $this->sensitivityCache[$key] = false;
    }
}
