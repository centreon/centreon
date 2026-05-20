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

abstract readonly class ExceptionFormatter
{
    private const MAX_TRACE_FRAMES = 15;
    private const MAX_EXCEPTIONS = 20;
    private const MAX_MESSAGE_LENGTH = 1024;
    private const TRUNCATION_MARKER_TYPE = '@truncated';

    /**
     * Returns a flat list of every exception in the cause chain — the
     * thrown exception first, then each `previous` in order, capped at
     * {@see self::MAX_EXCEPTIONS}. Beyond the cap, a sentinel entry of
     * type `@truncated` is appended so a log reader can detect the cut
     * without re-walking the chain.
     *
     * The cap protects against pathological chains (or a theoretical
     * reflection-induced cycle) producing an unbounded log payload.
     *
     * @return array{
     *     exceptions: list<array{
     *         type: class-string|self::TRUNCATION_MARKER_TYPE,
     *         message: string,
     *         code: int|string,
     *         file: string,
     *         line: int,
     *         trace: list<string>
     *     }>
     * }
     */
    public static function format(\Throwable $throwable): array
    {
        $exceptions = [self::formatOne($throwable)];
        $current = $throwable->getPrevious();
        $depth = 1;

        while ($current instanceof \Throwable && $depth < self::MAX_EXCEPTIONS) {
            $exceptions[] = self::formatOne($current);
            $current = $current->getPrevious();
            $depth++;
        }

        if ($current instanceof \Throwable) {
            $exceptions[] = self::truncationMarker();
        }

        return ['exceptions' => $exceptions];
    }

    /**
     * @return array{
     *     type: class-string,
     *     message: string,
     *     code: int|string,
     *     file: string,
     *     line: int,
     *     trace: list<string>
     * }
     */
    private static function formatOne(\Throwable $throwable): array
    {
        return [
            'type' => $throwable::class,
            'message' => self::truncateMessage($throwable->getMessage()),
            'code' => $throwable->getCode(),
            'file' => $throwable->getFile(),
            'line' => $throwable->getLine(),
            'trace' => self::formatTrace($throwable),
        ];
    }

    /**
     * Cap the exception message at the same width as the sanitized payload
     * value (cf. LoggingMiddleware::MAX_VALUE_LENGTH). Without this, a
     * PDOException carrying a multi-KB SQL fragment plus its parameters
     * would land verbatim in the log line, blowing the row width on
     * every aggregator downstream.
     */
    private static function truncateMessage(string $message): string
    {
        if (\mb_strlen($message) <= self::MAX_MESSAGE_LENGTH) {
            return $message;
        }

        return mb_substr($message, 0, self::MAX_MESSAGE_LENGTH) . '…[truncated]';
    }

    /**
     * @return array{
     *     type: self::TRUNCATION_MARKER_TYPE,
     *     message: string,
     *     code: int,
     *     file: string,
     *     line: int,
     *     trace: list<never>
     * }
     */
    private static function truncationMarker(): array
    {
        return [
            'type' => self::TRUNCATION_MARKER_TYPE,
            'message' => sprintf('previous chain truncated at %d entries', self::MAX_EXCEPTIONS),
            'code' => 0,
            'file' => '',
            'line' => 0,
            'trace' => [],
        ];
    }

    /**
     * @return list<string>
     */
    private static function formatTrace(\Throwable $throwable): array
    {
        $trace = $throwable->getTrace();
        $frames = [];

        foreach (\array_slice($trace, 0, self::MAX_TRACE_FRAMES) as $frame) {
            $frames[] = sprintf(
                '%s%s%s() at %s:%d',
                $frame['class'] ?? '',
                $frame['type'] ?? '',
                $frame['function'] ?? '?',
                $frame['file'] ?? '?',
                $frame['line'] ?? 0,
            );
        }

        $omitted = \count($trace) - self::MAX_TRACE_FRAMES;
        if ($omitted > 0) {
            $frames[] = sprintf('… %d frames omitted', $omitted);
        }

        return $frames;
    }
}
