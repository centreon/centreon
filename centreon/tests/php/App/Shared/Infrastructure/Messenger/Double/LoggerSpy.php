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

namespace Tests\App\Shared\Infrastructure\Messenger\Double;

use Psr\Log\AbstractLogger;

final class LoggerSpy extends AbstractLogger
{
    /** @var list<array{message: string, context: array<int|string, mixed>}> */
    public array $infoMessages = [];

    /** @var list<array{message: string, context: array<int|string, mixed>}> */
    public array $warningMessages = [];

    /** @var list<array{message: string, context: array<int|string, mixed>}> */
    public array $errorMessages = [];

    /** @var list<array{message: string, context: array<int|string, mixed>}> */
    public array $criticalMessages = [];

    public function log(mixed $level, string|\Stringable $message, array $context = []): void
    {
        $entry = ['message' => (string) $message, 'context' => $context];

        // Captured levels reflect the current LoggingMiddleware contract:
        // `info` (dispatching/handled), `warning` (normalisation fallback +
        // domain-validation failure), `critical` (unexpected handler throw).
        // Any other level surfaces here as a LogicException so the test fails
        // loudly when a new level is introduced without an explicit capture
        // branch.
        match ($level) {
            'info' => $this->infoMessages[] = $entry,
            'warning' => $this->warningMessages[] = $entry,
            'error' => $this->errorMessages[] = $entry,
            'critical' => $this->criticalMessages[] = $entry,
            default => throw new \LogicException(\sprintf(
                'Unexpected log level "%s" emitted to "%s"; capture it explicitly if it is now part of the contract.',
                \is_scalar($level) ? (string) $level : get_debug_type($level),
                self::class,
            )),
        };
    }
}
