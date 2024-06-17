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

namespace Core\Service\Domain\Model;

class ServiceStatusesCount
{
    public const STATUS_OK = 0,
                 STATUS_WARNING = 1,
                 STATUS_CRITICAL = 2,
                 STATUS_UNKNOWN = 3,
                 STATUS_PENDING = 4;

    public function __construct(
        private readonly int $totalOk,
        private readonly int $totalWarning,
        private readonly int $totalUnknown,
        private readonly int $totalCritical,
        private readonly int $totalPending
    ) {
    }

    public function getTotalPending(): int
    {
        return $this->totalPending;
    }

    public function getTotalCritical(): int
    {
        return $this->totalCritical;
    }

    public function getTotalOk(): int
    {
        return $this->totalOk;
    }

    public function getTotalWarning(): int
    {
        return $this->totalWarning;
    }

    public function getTotalUnknown(): int
    {
        return $this->totalUnknown;
    }

    public function getTotal(): int
    {
        return array_sum([
            $this->totalOk,
            $this->totalWarning,
            $this->totalCritical,
            $this->totalUnknown,
            $this->totalPending,
        ]);
    }
}

