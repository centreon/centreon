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

namespace Centreon\Domain\PlatformTopology\Interfaces;

interface PlatformTopologyReadRepositoryInterface
{
    /**
     * Search for already registered servers using same name or address.
     *
     * @param string $serverName
     *
     * @throws \Exception
     *
     * @return PlatformInterface|null
     */
    public function findPlatformByName(string $serverName): ?PlatformInterface;

    /**
     * Search for platform's ID using its address.
     *
     * @param string $serverAddress
     *
     * @throws \Exception
     *
     * @return PlatformInterface|null
     */
    public function findPlatformByAddress(string $serverAddress): ?PlatformInterface;

    /**
     * Search for platform's name and address using its type.
     *
     * @param string $serverType
     *
     * @throws \Exception
     *
     * @return PlatformInterface|null
     */
    public function findTopLevelPlatformByType(string $serverType): ?PlatformInterface;

    /**
     * Search for local platform's monitoring Id using its name.
     *
     * @param string $serverName
     *
     * @throws \Exception
     *
     * @return PlatformInterface|null
     */
    public function findLocalMonitoringIdFromName(string $serverName): ?PlatformInterface;

    /**
     * Search for the global topology of the platform.
     *
     * @return PlatformInterface[]
     */
    public function getPlatformTopology(): array;

    /**
     * Search for the global topology of the platform according to a list of access groups.
     *
     * @param int[] $accessGroupIds
     *
     * @return PlatformInterface[]
     */
    public function getPlatformTopologyByAccessGroupIds(array $accessGroupIds): array;

    /**
     * Search for the address of a topology using its Id.
     *
     * @param int $platformId
     *
     * @return PlatformInterface|null
     */
    public function findPlatform(int $platformId): ?PlatformInterface;

    /**
     * Find the Top Level Platform.
     *
     * @return PlatformInterface|null
     */
    public function findTopLevelPlatform(): ?PlatformInterface;

    /**
     * Find the children Platforms of another Platform.
     *
     * @param int $parentId
     *
     * @return PlatformInterface[]
     */
    public function findChildrenPlatformsByParentId(int $parentId): array;

    /**
     * find all the type 'remote' children of a Central.
     *
     * @throws \Exception
     *
     * @return PlatformInterface[]
     */
    public function findCentralRemoteChildren(): array;

    /**
     * Determine if the user has retricted access rights to platforms.
     *
     * @param int[] $accessGroupIds
     *
     * @throws \Throwable
     *
     * @return bool
     */
    public function hasRestrictedAccessToPlatforms(array $accessGroupIds): bool;

    /**
     * Determine if the user has access rights to a platform.
     *
     * @param int[] $accessGroupIds
     * @param int $platformId
     *
     * @throws \Throwable
     *
     * @return bool
     */
    public function hasAccessToPlatform(array $accessGroupIds, int $platformId): bool;
}
