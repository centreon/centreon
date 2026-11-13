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

namespace App\Upgrade\Domain\Exception;

use App\Upgrade\Domain\Model\MigrationSet;

/**
 * A migration failed or was skipped: the run stopped there.
 */
final class MigrationFailedException extends \RuntimeException
{
    private function __construct(
        public readonly string $version,
        string $message,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * @param list<string> $completed the migrations of the set that completed before it in this run
     * @param list<string> $warnings what those migrations warned of
     */
    public static function failed(MigrationSet $set, string $version, \Throwable $previous, array $completed = [], array $warnings = []): self
    {
        return new self($version, sprintf('The "%s" migration of the %s failed: %s', $version, $set->label(), rtrim($previous->getMessage(), '.')) . self::progress($completed, $warnings), $previous);
    }

    /**
     * @param list<string> $completed the migrations of the set that completed before it in this run
     * @param list<string> $warnings what those migrations warned of
     */
    public static function skipped(MigrationSet $set, string $version, ?string $reason, array $completed = [], array $warnings = []): self
    {
        return new self($version, sprintf(
            'The %s skipped %s%s: a migration must run or fail, never skip',
            $set->label(),
            $version,
            $reason === null ? '' : sprintf(' ("%s")', $reason),
        ) . self::progress($completed, $warnings));
    }

    /**
     * @param list<string> $completed
     * @param list<string> $warnings
     */
    private static function progress(array $completed, array $warnings): string
    {
        return ($completed === [] ? '.' : sprintf('. Completed before it in this set: %s.', implode(', ', $completed)))
            . ($warnings === [] ? '' : sprintf(' Warnings: %s.', implode('; ', $warnings)));
    }
}
