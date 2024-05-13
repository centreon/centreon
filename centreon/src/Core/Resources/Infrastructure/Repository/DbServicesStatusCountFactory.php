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

use Core\Resources\Domain\Model\CriticalStatusCount;
use Core\Resources\Domain\Model\OkStatusCount;
use Core\Resources\Domain\Model\PendingStatusCount;
use Core\Resources\Domain\Model\ServicesStatusCount;
use Core\Resources\Domain\Model\UnknownStatusCount;
use Core\Resources\Domain\Model\WarningStatusCount;

final class DbServicesStatusCountFactory
{
    public const OK_STATUS = 0;
    public const WARNING_STATUS = 1;
    public const CRITICAL_STATUS = 2;
    public const UNKNOWN_STATUS = 3;
    public const PENDING_STATUS = 4;

    /**
     * @param array<array{id:int, status: int}> $record
     *
     * @return ServicesStatusCount
     */
    public static function createFromRecord(array $record): ServicesStatusCount
    {
        $statuses = array_map(static fn(array $recordEntry): int => $recordEntry['status'], $record);

        return new ServicesStatusCount(
            new CriticalStatusCount(self::countInStatus(self::CRITICAL_STATUS, $statuses)),
            new WarningStatusCount(self::countInStatus(self::WARNING_STATUS, $statuses)),
            new UnknownStatusCount(self::countInStatus(self::UNKNOWN_STATUS, $statuses)),
            new OkStatusCount(self::countInStatus(self::OK_STATUS, $statuses)),
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