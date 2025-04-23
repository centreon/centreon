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

namespace Core\Host\Domain\Model;

class HostStatusesCount
{
    public const STATUS_UP = 0;
    public const STATUS_DOWN = 1;
    public const STATUS_UNREACHABLE = 2;
    public const STATUS_PENDING = 4;

    public function __construct(
        private readonly int $totalUp,
        private readonly int $totalDown,
        private readonly int $totalUnreachable,
        private readonly int $totalPending
    ) {
    }

    public function getTotalPending(): int
    {
        return $this->totalPending;
    }

    public function getTotalUnreachable(): int
    {
        return $this->totalUnreachable;
    }

    public function getTotalDown(): int
    {
        return $this->totalDown;
    }

    public function getTotalUp(): int
    {
        return $this->totalUp;
    }

    public function getTotal(): int
    {
        return array_sum([$this->totalPending, $this->totalUp, $this->totalUnreachable, $this->totalDown]);
    }
}
