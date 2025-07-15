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

namespace Centreon\Domain\Monitoring\Interfaces;

use Centreon\Domain\Contact\Interfaces\ContactFilterInterface;
use Centreon\Domain\Monitoring\Exception\MonitoringServiceException;
use Centreon\Domain\Monitoring\Host;
use Centreon\Domain\Monitoring\HostGroup;
use Centreon\Domain\Monitoring\Service;
use Centreon\Domain\Monitoring\ServiceGroup;

/**
 * @package Centreon\Domain\Monitoring\Interfaces
 */
interface MonitoringServiceInterface extends ContactFilterInterface
{
    /**
     * Find all hosts.
     *
     * @param bool $withServices Indicates whether hosts must be completed with their services
     * @throws \Exception
     * @return Host[]
     */
    public function findHosts(bool $withServices = false): array;

    /**
     * Find all host groups.
     *
     * @param bool $withHosts Indicates whether hosts groups must be completed with their hosts
     * @param bool $withServices Indicates whether hosts must be completed with their services
     * @param int|null $hostId Return only hostgroups for specific host null by default
     * @throws \Exception
     * @return HostGroup[]
     */
    public function findHostGroups(bool $withHosts = false, bool $withServices = false, ?int $hostId = null): array;

    /**
     * Find a host based on his ID.
     *
     * @param int $hostId Id of the host to be found
     * @throws \Exception
     * @return Host|null
     */
    public function findOneHost(int $hostId): ?Host;

    /**
     * Find a service based on his ID and a host ID.
     *
     * @param int $hostId Host ID for which the service belongs
     * @param int $serviceId Service ID to find
     * @throws \Exception
     * @return Service|null
     */
    public function findOneService(int $hostId, int $serviceId): ?Service;

    /**
     * Find a service based on its description
     *
     * @param string $description description of the service
     * @throws \Exception
     * @return Service|null
     */
    public function findOneServiceByDescription(string $description): ?Service;

    /**
     * Find all service groups.
     *
     * @param bool $withHosts Indicates whether service groups must be completed with their hosts
     * @param bool $withServices Indicates whether hosts must be completed with their services
     * @throws \Exception
     * @return ServiceGroup[]
     */
    public function findServiceGroups(bool $withHosts = false, bool $withServices = false): array;

    /**
     * Find all services.
     *
     * @throws \Exception
     * @return Service[]
     */
    public function findServices(): array;

    /**
     * Find all services belonging to the host.
     *
     * @param int $hostId
     * @throws \Exception
     * @return Service[]
     */
    public function findServicesByHost(int $hostId): array;

    /**
     * Indicates whether a host exists.
     *
     * @param int $hostId Host id to find
     * @throws \Exception
     * @return bool
     */
    public function isHostExists(int $hostId): bool;

    /**
     * Indicates whether a service exists.
     *
     * @param int $hostId Host id to find
     * @param int $serviceId Service id to find
     * @throws \Exception
     * @return bool
     */
    public function isServiceExists(int $hostId, int $serviceId): bool;

    /**
     * Find all service groups by host and service ids.
     *
     * @param int $hostId
     * @param int $serviceId
     * @return ServiceGroup[]
     */
    public function findServiceGroupsByHostAndService(int $hostId, int $serviceId): array;

    /**
     * Try to hide all macro password values of the host command line.
     *
     * @param Host $monitoringHost Monitoring host
     * @param string $replacementValue Replacement value used instead of macro password value
     * @throws \Exception
     */
    public function hidePasswordInHostCommandLine(Host $monitoringHost, string $replacementValue = '***'): void;

    /**
     * Try to hide all macro password values of the service command line.
     *
     * @param Service $monitoringService Monitoring service
     * @param string $replacementValue Replacement value used instead of macro password value
     * @throws \Exception
     */
    public function hidePasswordInServiceCommandLine(
        Service $monitoringService,
        string $replacementValue = '***'
    ): void;

    /**
     * Find the command line of a service.
     *
     * If a password exists, it will be hidden.
     *
     * @param int $hostId Host id associated to the service
     * @param int $serviceId Service id
     * @throws MonitoringServiceException
     * @return string|null Return the command line if it exists
     */
    public function findCommandLineOfService(int $hostId, int $serviceId): ?string;
}
