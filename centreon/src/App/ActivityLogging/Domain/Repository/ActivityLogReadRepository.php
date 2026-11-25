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

namespace App\ActivityLogging\Domain\Repository;

use App\ActivityLogging\Domain\ReadModel\ActivityLogDetails;
use App\ActivityLogging\Domain\ReadModel\ActivityLogEntry;
use App\ActivityLogging\Domain\ReadModel\ActivityLogPage;
use App\ActivityLogging\Domain\Repository\Criteria\ActivityLogCriteria;
use App\Shared\Domain\Collection;

/**
 * Read side of the activity log.
 */
interface ActivityLogReadRepository
{
    /**
     * Most recent first unless the criteria ask otherwise. A paginated search returns a page
     * without total count.
     *
     * @return Collection<ActivityLogEntry>|ActivityLogPage
     */
    public function findAll(ActivityLogCriteria $criteria): Collection|ActivityLogPage;

    public function findEntry(int $id): ?ActivityLogEntry;

    /**
     * The recorded fields of every log of the entry's target, oldest first, up to and including the entry itself.
     *
     * @return list<ActivityLogDetails>
     */
    public function findTargetHistoryUntil(ActivityLogEntry $entry): array;

    /**
     * The distinct ids of the contacts having performed at least one logged change.
     *
     * @return list<int>
     */
    public function findActorIds(): array;

    /**
     * The distinct names of the API tokens at least one logged change was made with, sorted by name.
     *
     * @return list<string>
     */
    public function findTokenNames(): array;
}
