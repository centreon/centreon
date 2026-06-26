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

use Monolog\LogRecord;

/**
 * Monolog processor that masks sensitive data in every record through
 * {@see PayloadSanitizer}: `#[Sensitive]` / keyword entries in `context`
 * (the ad-hoc `$logger->error('msg', $context)` paths that bypass the
 * bus middleware) and secrets carried in URL query strings under `extra`
 * (notably `extra.url`, set by the WebProcessor).
 *
 * It must run after the context-enriching processors that populate
 * `extra`. Registration order — not the tag `priority`, which the
 * MonologBundle ignores for processors — guarantees this; see
 * config/services.yaml. `\Throwable` values under
 * `context.exception` are left as-is so that
 * {@see ExceptionFormatterProcessor} can structure them downstream.
 */
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
        /** @var array<string, mixed> $context */
        $context = $this->sanitizer->sanitize($record->context);

        // `extra` is filled by platform processors (WebProcessor puts the
        // request URI — query string included — in `extra.url`). Its keys are
        // not user-controlled, so keyword-key masking stays off to keep audit
        // fields readable (e.g. `extra.token` is TokenProcessor's audit
        // descriptor of the authenticated user, not a credential); only
        // sensitive URL query-string parameters are redacted.
        /** @var array<string, mixed> $extra */
        $extra = $this->sanitizer->sanitize($record->extra, maskKeywordKeys: false);

        return $record->with(context: $context, extra: $extra);
    }
}
