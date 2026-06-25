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
 * Global Monolog processor that masks `#[Sensitive]` entries in every
 * record's `context` through {@see PayloadSanitizer}, including the
 * ad-hoc `$logger->error('msg', $context)` calls. `\Throwable` values
 * are returned as-is so that {@see ExceptionFormatterProcessor} can
 * structure them downstream.
 */
#[AsMonologProcessor]
final readonly class SanitizingProcessor
{
    public function __construct(private PayloadSanitizer $sanitizer)
    {
    }

    /**
     * @throws \ReflectionException propagated from PayloadSanitizer when a context class cannot be reflected
     */
    public function __invoke(LogRecord $record): LogRecord
    {
        /** @var array<string, mixed> $sanitized */
        $sanitized = $this->sanitizer->sanitize($record->context);

        return $record->with(context: $sanitized);
    }
}
