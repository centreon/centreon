<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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

namespace Centreon\Domain\HostConfiguration\Interfaces;

use Centreon\Domain\HostConfiguration\Exception\HostConfigurationServiceException;
use Centreon\Domain\HostConfiguration\Host;
use Centreon\Domain\HostConfiguration\HostConfigurationException;
use Centreon\Domain\HostConfiguration\HostMacro;

interface HostConfigurationServiceInterface
{
    /**
     * Add a host.
     *
     * @param Host $host
     * @throws HostConfigurationServiceException
     */
    public function addHost(Host $host): void;

    /**
     * Find a host.
     *
     * @param int $hostId Host Id to be found
     * @throws HostConfigurationException
     * @return Host|null Returns a host otherwise null
     */
    public function findHost(int $hostId): ?Host;

    /**
     * Returns the number of host.
     *
     * @throws HostConfigurationException
     * @return int Number of host
     */
    public function getNumberOfHosts(): int;

    /**
     * Find host templates recursively.
     *
     * **The priority order of host templates is maintained!**
     *
     * @param Host $host Host for which we want to find all host templates recursively
     * @throws HostConfigurationException
     * @return Host[]
     */
    public function findHostTemplatesRecursively(Host $host): array;

    /**
     * Find the command of a host.
     * A recursive search will be performed in the inherited templates in the
     * case where the host does not have a command.
     *
     * @param int $hostId Host id
     * @throws HostConfigurationException
     * @return string|null Return the command if found
     */
    public function findCommandLine(int $hostId): ?string;

    /**
     * Find all host macros for the host.
     *
     * @param int $hostId Id of the host
     * @param bool $isUsingInheritance Indicates whether to use inheritance to find host macros (FALSE by default)
     * @throws HostConfigurationException
     * @return HostMacro[] List of host macros found
     */
    public function findOnDemandHostMacros(int $hostId, bool $isUsingInheritance = false): array;

    /**
     * Find all on-demand host macros needed for this command.
     *
     * @param int $hostId Host id
     * @param string $command Command to analyse
     * @throws HostConfigurationException
     * @return HostMacro[] List of host macros
     */
    public function findHostMacrosFromCommandLine(int $hostId, string $command): array;

    /**
     * Change the activation status of host.
     *
     * @param Host $host Host for which we want to change the activation status
     * @param bool $shouldBeActivated TRUE to activate a host
     * @throws HostConfigurationException
     */
    public function changeActivationStatus(Host $host, bool $shouldBeActivated): void;

    /**
     * Find host names already used by hosts.
     *
     * @param string[] $namesToCheck List of names to find
     * @throws HostConfigurationException
     * @return string[] Return the host names found
     */
    public function findHostNamesAlreadyUsed(array $namesToCheck): array;

    /**
     * Update a host.
     *
     * @param Host $host
     * @throws HostConfigurationServiceException
     */
    public function updateHost(Host $host): void;

    /**
     * Find host templates by host id (non recursive)
     *
     * **The priority order of host templates is maintained!**
     *
     * @param Host $host
     * @return Host[]
     */
    public function findHostTemplatesByHost(Host $host): array;

    /**
     * Find a host by its name
     *
     * @param string $hostName Host name to be found
     * @throws HostConfigurationException
     * @return Host|null Returns a host otherwise null
     */
    public function findHostByName(string $hostName): ?Host;

    /**
     * Find a host template by its id
     *
     * @param int $hostTemplateId
     * @return Host|null
     */
    public function findHostTemplate(int $hostTemplateId): ?Host;
}
