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

namespace Core\HostGroup\Domain\Model;

class HostsCountById
{
    /** @var array<int,int> */
    private array $enabledHostsCount = [];

    /** @var array<int,int> */
    private array $disabledHostsCount = [];

    /**
     * @param int $hostGroupId
     * @param int $count
     */
    public function setEnabledCount(int $hostGroupId, int $count): void
    {
        $this->enabledHostsCount[$hostGroupId] = $count;
    }

    /**
     * @param int $hostGroupId
     * @param int $count
     */
    public function setDisabledCount(int $hostGroupId, int $count): void
    {
        $this->disabledHostsCount[$hostGroupId] = $count;
    }

    /**
     * @param int $hostGroupId
     *
     * @return int
     */
    public function getEnabledCount(int $hostGroupId): int {
        return $this->enabledHostsCount[$hostGroupId] ?? 0;
    }

    /**
     * @param int $hostGroupId
     *
     * @return int
     */
    public function getDisabledCount(int $hostGroupId): int {
        return $this->disabledHostsCount[$hostGroupId] ?? 0;
    }
}
