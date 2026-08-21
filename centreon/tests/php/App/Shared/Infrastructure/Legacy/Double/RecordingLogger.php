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

namespace Tests\App\Shared\Infrastructure\Legacy\Double;

use Psr\Log\AbstractLogger;

/**
 * @phpstan-type RecordTypeAlias array{level: string, message: string, context: array<string, mixed>}
 */
final class RecordingLogger extends AbstractLogger
{
    /** @var list<RecordTypeAlias> */
    private array $records = [];

    /**
     * @param array<string, mixed> $context
     * @param mixed $level
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        assert(is_string($level));

        $this->records[] = [
            'level' => $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }

    /**
     * @return list<RecordTypeAlias>
     */
    public function records(): array
    {
        return $this->records;
    }

    /**
     * @return RecordTypeAlias|null
     */
    public function lastRecord(): ?array
    {
        return $this->records === [] ? null : $this->records[array_key_last($this->records)];
    }
}
