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

namespace Core\Host\Application\Repository;

use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Host\Domain\Model\Host;
use Core\Host\Domain\Model\HostNamesById;
use Core\Host\Domain\Model\SmallHost;
use Core\Host\Domain\Model\TinyHost;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

interface ReadHostRepositoryInterface
{
    /**
     * Determine if a host exists by its name.
     * (include both host templates and hosts names).
     *
     * @param string $hostName
     *
     * @throws \Throwable
     *
     * @return bool
     */
    public function existsByName(string $hostName): bool;

    /**
     * Find a host by its id.
     *
     * @param int $hostId
     *
     * @throws \Throwable
     *
     * @return ?Host
     */
    public function findById(int $hostId): ?Host;

    /**
     * Find hosts by their id.
     *
     * @param list<int> $hostIds
     *
     * @throws \Throwable
     *
     * @return list<TinyHost>
     */
    public function findByIds(array $hostIds): array;

    /**
     * Find hosts by their names.
     *
     * @param list<string> $hostNames
     *
     * @throws \Throwable
     *
     * @return list<TinyHost>
     */
    public function findByNames(array $hostNames): array;

    /**
     * Find hosts based on query parameters.
     *
     * @param RequestParametersInterface $requestParameters
     *
     * @throws \Throwable
     *
     * @return SmallHost[]
     */
    public function findByRequestParameters(RequestParametersInterface $requestParameters): array;

    /**
     * Find hosts based on query parameters and access groups.
     * If the list of access groups is empty, no restrictions will be applied.
     *
     * @param RequestParametersInterface $requestParameters
     * @param AccessGroup[] $accessGroups If the list is empty, no restrictions will be applied
     *
     * @throws \Throwable
     *
     * @return SmallHost[]
     */
    public function findByRequestParametersAndAccessGroups(
        RequestParametersInterface $requestParameters,
        array $accessGroups
    ): array;

    /**
     * Retrieve all parent template ids of a host.
     *
     * @param int $hostId
     *
     * @throws \Throwable
     *
     * @return array<array{parent_id:int,child_id:int,order:int}>
     */
    public function findParents(int $hostId): array;

    /**
     * Indicates whether the host already exists.
     *
     * @param int $hostId
     *
     * @throws \Throwable
     *
     * @return bool
     */
    public function exists(int $hostId): bool;

    /**
     * Indicates whether the hosts exist and return the ids found.
     *
     * @param int[] $hostIds
     *
     * @throws \Throwable
     *
     * @return int[]
     */
    public function exist(array $hostIds): array;

    /**
     * Indicates whether the host already exists.
     *
     * @param int $hostId
     * @param AccessGroup[] $accessGroups
     *
     * @throws \Throwable
     *
     * @return bool
     */
    public function existsByAccessGroups(int $hostId, array $accessGroups): bool;

    /**
     * Find host names by their IDs.
     *
     * @param int[] $hostIds
     *
     * @throws \Throwable
     *
     * @return HostNamesById
     */
    public function findNames(array $hostIds): HostNamesById;

    /**
     * Find all hosts.
     *
     * @throws \Throwable
     *
     * @return Host[]
     */
    public function findAll(): array;
}
