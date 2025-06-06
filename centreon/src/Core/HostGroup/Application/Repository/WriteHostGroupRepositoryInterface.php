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

namespace Core\HostGroup\Application\Repository;

use Core\HostGroup\Domain\Model\HostGroup;
use Core\HostGroup\Domain\Model\NewHostGroup;

interface WriteHostGroupRepositoryInterface
{
    /**
     * Delete a host group.
     *
     * @param int $hostGroupId
     */
    public function deleteHostGroup(int $hostGroupId): void;

    /**
     * @param NewHostGroup $newHostGroup
     *
     * @throws \Throwable
     *
     * @return int
     */
    public function add(NewHostGroup $newHostGroup): int;

    /**
     * @param HostGroup $hostGroup
     *
     * @throws \Throwable
     */
    public function update(HostGroup $hostGroup): void;

    /**
     * Link a list of groups to a host.
     *
     * @param int $hostId
     * @param int[] $groupIds
     *
     * @throws \Throwable
     */
    public function linkToHost(int $hostId, array $groupIds): void;

    /**
     * Unlink a list of groups from a host.
     *
     * @param int $hostId
     * @param int[] $groupIds
     *
     * @throws \Throwable
     */
    public function unlinkFromHost(int $hostId, array $groupIds): void;

    /**
     * Add a list of hosts to a host group.
     *
     * @param int $hostGroupId
     * @param int[] $hostIds
     *
     * @throws \Throwable
     */
    public function addHostLinks(int $hostGroupId, array $hostIds): void;

    /**
     * Delete a list of hosts from an host group.
     *
     * @param int $hostGroupId
     * @param int[] $hostIds
     * @return void
     */
    public function deleteHostLinks(int $hostGroupId, array $hostIds): void;

    /**
     * Set an host group as enabled or disabled.
     *
     * @param int $hostGroupId
     * @param bool $isEnable
     */
    public function enableDisableHostGroup(int $hostGroupId, bool $isEnable): void;

    /**
     * Duplicate a host group.
     *
     * @param int $hostGroupId
     * @param int $duplicateIndex The index to append to the duplicated host group name
     *
     * @throws \Throwable
     *
     * @return int The new host group ID
     */
    public function duplicate(int $hostGroupId, int $duplicateIndex): int;
}
