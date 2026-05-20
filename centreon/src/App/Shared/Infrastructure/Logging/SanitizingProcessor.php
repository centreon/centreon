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

use Monolog\Attribute\AsMonologProcessor;
use Monolog\LogRecord;

/**
 * Monolog processor that walks every record's `context` and masks
 * sensitive entries through {@see PayloadSanitizer} — same precedence
 * as the bus pipeline, applied to every ad-hoc
 * `$logger->error('msg', $context)` regardless of channel.
 *
 * Without this processor the masking only covered the Messenger bus
 * payload (built by `LoggingMiddleware`); a controller / service that
 * logs `['password' => $raw]` directly would otherwise leak. Tracking
 * gap closed by MON-199097.
 *
 * `\Throwable` values are returned as-is by the sanitiser so that
 * {@see ExceptionFormatterProcessor} can structure them downstream.
 */
#[AsMonologProcessor]
final readonly class SanitizingProcessor
{
    public function __construct(private PayloadSanitizer $sanitizer)
    {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        /** @var array<string, mixed> $sanitized */
        $sanitized = $this->sanitizer->sanitize($record->context);

        return $record->with(context: $sanitized);
    }
}
