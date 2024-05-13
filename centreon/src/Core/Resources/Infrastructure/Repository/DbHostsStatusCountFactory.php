<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

namespace Core\Resources\Infrastructure\Repository;

use Core\Resources\Domain\Model\DownStatusCount;
use Core\Resources\Domain\Model\HostsStatusCount;
use Core\Resources\Domain\Model\PendingStatusCount;
use Core\Resources\Domain\Model\UnreachableStatusCount;
use Core\Resources\Domain\Model\UpStatusCount;

final class DbHostsStatusCountFactory
{
    public const UP_STATUS = 0;
    public const DOWN_STATUS = 1;
    public const UNREACHABLE_STATUS = 2;
    public const PENDING_STATUS = 4;

    /**
     * @param array<array{id:int, status: int}> $record
     *
     * @return HostsStatusCount
     */
    public static function createFromRecord(array $record): HostsStatusCount
    {
        $statuses = array_map(static fn(array $recordEntry): int => $recordEntry['status'], $record);

        return new HostsStatusCount(
            new DownStatusCount(self::countInStatus(self::DOWN_STATUS, $statuses)),
            new UnreachableStatusCount(self::countInStatus(self::UNREACHABLE_STATUS, $statuses)),
            new UpStatusCount(self::countInStatus(self::UP_STATUS, $statuses)),
            new PendingStatusCount(self::countInStatus(self::PENDING_STATUS, $statuses))
        );
    }

    /**
     * Count resources in given status.
     *
     * @param int $statusCode
     * @param int[] $statuses
     *
     * @return int
     */
    private static function countInStatus(int $statusCode, array $statuses): int
    {
        $resourceInStatus = array_filter(
            $statuses,
            static fn(int $status) => $status === $statusCode
        );

        return count($resourceInStatus);
    }
}