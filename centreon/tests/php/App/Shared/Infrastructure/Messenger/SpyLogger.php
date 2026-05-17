<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
 *
 * Unauthorized reproduction, copy and distribution are not allowed.
 *
 * For more information : contact@centreon.com
 *
 */

declare(strict_types=1);

namespace Tests\App\Shared\Infrastructure\Messenger;

use Psr\Log\AbstractLogger;

final class SpyLogger extends AbstractLogger
{
    /** @var list<array{message: string, context: array<int|string, mixed>}> */
    public array $infoMessages = [];

    /** @var list<array{message: string, context: array<int|string, mixed>}> */
    public array $warningMessages = [];

    /** @var list<array{message: string, context: array<int|string, mixed>}> */
    public array $errorMessages = [];

    public function log(mixed $level, string|\Stringable $message, array $context = []): void
    {
        $entry = ['message' => (string) $message, 'context' => $context];

        // Captured levels reflect the current LoggingMiddleware contract:
        // `info` (dispatching/handled), `warning` (normalisation fallback),
        // `error` (handler throw). Any other level surfaces here as a
        // LogicException so the test fails loudly when a new level is
        // introduced without an explicit capture branch.
        match ($level) {
            'info' => $this->infoMessages[] = $entry,
            'warning' => $this->warningMessages[] = $entry,
            'error' => $this->errorMessages[] = $entry,
            default => throw new \LogicException(\sprintf(
                'Unexpected log level "%s" emitted to SpyLogger; capture it explicitly if it is now part of the contract.',
                \is_scalar($level) ? (string) $level : get_debug_type($level),
            )),
        };
    }
}
