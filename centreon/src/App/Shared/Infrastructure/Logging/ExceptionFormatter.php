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

/**
 * @phpstan-type FormattedExceptionTypeAlias array{
 *     type: class-string|self::TRUNCATION_MARKER_TYPE,
 *     message: string,
 *     code: int|string,
 *     file: string,
 *     line: int,
 *     trace: list<string>
 * }
 */
final readonly class ExceptionFormatter
{
    private const MAX_TRACE_FRAMES = 15;
    private const MAX_EXCEPTIONS = 20;
    private const MAX_MESSAGE_LENGTH = 1024;
    private const TRUNCATION_MARKER_TYPE = '@truncated';

    private function __construct()
    {
    }

    /**
     * Returns a flat list of every exception in the cause chain — root
     * first, each `previous` in order — capped at MAX_EXCEPTIONS with a
     * trailing `@truncated` sentinel marking the cut.
     *
     * @return array{exceptions: list<FormattedExceptionTypeAlias>}
     */
    public static function format(\Throwable $throwable): array
    {
        $exceptions = [];
        $current = $throwable;
        $depth = 0;

        do {
            $exceptions[] = [
                'type' => $current::class,
                'message' => self::truncateMessage($current->getMessage()),
                'code' => $current->getCode(),
                'file' => $current->getFile(),
                'line' => $current->getLine(),
                'trace' => self::formatTrace($current),
            ];
            $current = $current->getPrevious();
            $depth++;
        } while ($current instanceof \Throwable && $depth < self::MAX_EXCEPTIONS);

        if ($current instanceof \Throwable) {
            $exceptions[] = self::truncationMarker();
        }

        return ['exceptions' => $exceptions];
    }

    private static function truncateMessage(string $message): string
    {
        if (\mb_strlen($message) <= self::MAX_MESSAGE_LENGTH) {
            return $message;
        }

        return mb_substr($message, 0, self::MAX_MESSAGE_LENGTH) . '…[truncated]';
    }

    /**
     * @return FormattedExceptionTypeAlias
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

        foreach ($trace as $index => $frame) {
            if ($index >= self::MAX_TRACE_FRAMES) {
                break;
            }
            $class = $frame['class'] ?? '';
            $function = $frame['function'] ?? '?';
            $callable = $class === ''
                ? $function
                : $class . ($frame['type'] ?? '::') . $function;

            $frames[] = sprintf(
                '%s() at %s:%d',
                $callable,
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
