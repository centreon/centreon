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

namespace App\ActivityLogging\Domain\ReadModel;

use App\ActivityLogging\Domain\Aggregate\ActionEnum;
use App\ActivityLogging\Domain\Aggregate\TargetTypeEnum;

/**
 * One line of the activity log, as read back from storage.
 *
 * Unlike the {@see \App\ActivityLogging\Domain\Aggregate\ActivityLog} aggregate (write side), it
 * accepts what the legacy writers stored: no actor for system changes, or an actor which no
 * longer exists.
 */
final readonly class ActivityLogEntry
{
    public function __construct(
        public int $id,
        public \DateTimeImmutable $performedAt,
        public ActionEnum $action,
        public int $targetId,
        public TargetTypeEnum $targetType,
        public string $targetName,
        public ?int $actorId,
        public bool $hasChanges,
        // Name of the API token the change was made with, null when made from the UI
        public ?string $tokenName = null,
    ) {
    }
}
