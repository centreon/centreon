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

namespace App\ActivityLogging\Domain\Aggregate;

use App\Shared\Domain\Exception\MissingIdException;

final class ActivityLog
{
    /**
     * @param array<string, string> $details
     */
    public function __construct(
        private ?ActivityLogId $id,
        public readonly ActionEnum $action,
        public readonly Actor $actor,
        public readonly Target $target,
        public readonly array $details = [],
        public readonly \DateTimeImmutable $performedAt = new \DateTimeImmutable(),
    ) {
    }

    public function id(): ActivityLogId
    {
        if (! $this->id instanceof ActivityLogId) {
            throw new MissingIdException(self::class);
        }

        return $this->id;
    }
}
