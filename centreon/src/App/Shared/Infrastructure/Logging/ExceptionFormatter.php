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
    private const MAX_PREVIOUS_DEPTH = 20;
    private const MAX_MESSAGE_LENGTH = 1024;
    private const TRUNCATION_MARKER_TYPE = '@truncated';

    /**
     * Every entry of the returned structure (root + each entry under
     * `previous`) carries the same six fields, plus a `previous` array.
     * On the root, `previous` may contain other entries (the flattened
     * chain). On every other entry, `previous` is **always empty** —
     * we never recurse the format, so a reader can iterate the tree
     * with a single shape and stop on each leaf without a special case.
     *
     * @return array{
     *     type: class-string,
     *     message: string,
     *     code: int|string,
     *     file: string,
     *     line: int,
     *     trace: list<string>,
     *     previous: list<array{
     *         type: class-string|self::TRUNCATION_MARKER_TYPE,
     *         message: string,
     *         code: int|string,
     *         file: string,
     *         line: int,
     *         trace: list<string>,
     *         previous: list<never>
     *     }>
     * }
     */
    public static function format(\Throwable $throwable): array
    {
        $previous = [];
        $current = $throwable->getPrevious();
        $depth = 0;

        while ($current instanceof \Throwable && $depth < self::MAX_PREVIOUS_DEPTH) {
            $previous[] = self::formatLeaf($current);
            $current = $current->getPrevious();
            $depth++;
        }

        if ($current instanceof \Throwable) {
            // Chain longer than the cap, or pathological cycle (theoretical
            // via reflection). Stop here without counting the rest — that
            // would re-enter the same unbounded walk we just broke out of.
            $previous[] = self::truncationMarker();
        }

        return [...self::baseFields($throwable), 'previous' => $previous];
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
    private static function baseFields(\Throwable $throwable): array
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
     *     type: class-string,
     *     message: string,
     *     code: int|string,
     *     file: string,
     *     line: int,
     *     trace: list<string>,
     *     previous: list<never>
     * }
     */
    private static function formatLeaf(\Throwable $throwable): array
    {
        return [...self::baseFields($throwable), 'previous' => []];
    }

    /**
     * @return array{
     *     type: self::TRUNCATION_MARKER_TYPE,
     *     message: string,
     *     code: int,
     *     file: string,
     *     line: int,
     *     trace: list<string>,
     *     previous: list<never>
     * }
     */
    private static function truncationMarker(): array
    {
        return [
            'type' => self::TRUNCATION_MARKER_TYPE,
            'message' => sprintf('previous chain truncated at %d entries', self::MAX_PREVIOUS_DEPTH),
            'code' => 0,
            'file' => '',
            'line' => 0,
            'trace' => [],
            'previous' => [],
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
