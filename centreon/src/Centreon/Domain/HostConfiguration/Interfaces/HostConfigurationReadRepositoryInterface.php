<?php

/*
 * Copyright 2005 - 2020 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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

namespace Centreon\Domain\HostConfiguration\Interfaces;

use Centreon\Domain\HostConfiguration\Host;
use Centreon\Domain\HostConfiguration\HostMacro;

/**
 * This interface gathers all the reading operations on the repository.
 *
 * @package Centreon\Domain\HostConfiguration\Interfaces
 */
interface HostConfigurationReadRepositoryInterface
{
    /**
     * Find a host.
     *
     * @param int $hostId Host Id to be found
     * @throws \Throwable
     * @return Host|null Returns a host otherwise null
     */
    public function findHost(int $hostId): ?Host;

    /**
     * Recursively find all host templates.
     *
     * **The priority order of host templates is maintained!**
     *
     * @param Host $host Host for which we want to find all host templates recursively
     * @throws \Throwable
     * @return Host[]
     */
    public function findHostTemplatesRecursively(Host $host): array;

    /**
     * Indicates if a hostname is already in use.
     *
     * @param string $hostName Hostname to be found
     * @return bool True if the hostname is already in use
     */
    public function hasHostWithSameName(string $hostName): bool;

    /**
     * Returns the number of hosts.
     *
     * @return int Number of hosts
     */
    public function getNumberOfHosts(): int;

    /**
     * Find the command of a host.
     *
     * Recursively search in the inherited templates if no result found.
     *
     * @param int $hostId Host id
     * @throws \Throwable
     * @return string|null Return the command if found
     */
    public function findCommandLine(int $hostId): ?string;

    /**
     * Find all host macros for the host.
     *
     * @param int $hostId Id of the host
     * @param bool $isUsingInheritance Indicates whether to use inheritance to find host macros (FALSE by default)
     * @throws \Throwable
     * @return array<HostMacro> List of host macros found
     */
    public function findOnDemandHostMacros(int $hostId, bool $isUsingInheritance = false): array;

    /**
     * Find host names already used by hosts.
     *
     * @param string[] $namesToCheck List of names to find
     * @return string[] Return the host names found
     */
    public function findHostNamesAlreadyUsed(array $namesToCheck): array;

    /**
     * Find a host regarding user ACL
     *
     * @param int $hostId
     * @param int[] $accessGroupIds
     * @throws \Throwable
     * @return Host|null
     */
    public function findHostByAccessGroupIds(int $hostId, array $accessGroupIds): ?Host;

    /**
     * Find host templates linked to a host (non recursive)
     *
     * **The priority order of host templates is maintained!**
     *
     * @param Host $host
     * @return Host[]
     */
    public function findHostTemplatesByHost(Host $host): array;

    /**
     * Find a host by its name.
     *
     * @param string $hostName Host Id to be found
     * @throws \Throwable
     * @return Host|null Returns a host otherwise null
     */
    public function findHostByName(string $hostName): ?Host;

    /**
     * Find host template by its id
     *
     * @param int $hostTemplateId
     * @throws \Throwable
     * @return Host|null
     */
    public function findHostTemplate(int $hostTemplateId): ?Host;
}
