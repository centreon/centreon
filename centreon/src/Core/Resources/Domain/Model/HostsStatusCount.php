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

declare(strict_types = 1);

namespace Core\Resources\Domain\Model;

final class HostsStatusCount
{
    public function __construct(
        private readonly DownStatusCount $downStatusCount,
        private readonly UnreachableStatusCount $unreachableStatusCount,
        private readonly UpStatusCount $upStatusCount,
        private readonly PendingStatusCount $pendingStatusCount
    ) {
    }

    public function getDownStatusCount(): DownStatusCount
    {
        return $this->downStatusCount;
    }

    public function getUnreachableStatusCount(): UnreachableStatusCount
    {
        return $this->unreachableStatusCount;
    }

    public function getUpStatusCount(): UpStatusCount
    {
        return $this->upStatusCount;
    }

    public function getPendingStatusCount(): PendingStatusCount
    {
        return $this->pendingStatusCount;
    }

    /**
     * Return the total of all the status.
     *
     * @return int
     */
    public function getTotal(): int
    {
        return $this->downStatusCount->getTotal()
            + $this->unreachableStatusCount->getTotal()
            + $this->upStatusCount->getTotal()
            + $this->pendingStatusCount->getTotal();
    }
}