<?php

/*
 * Copyright 2005 - 2021 Centreon (https://www.centreon.com/)
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

namespace Centreon\Domain\MonitoringServer\UseCase;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Exception\TimeoutException;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\MonitoringServer\Exception\ConfigurationMonitoringServerException;
use Centreon\Domain\MonitoringServer\Interfaces\MonitoringServerRepositoryInterface;
use Centreon\Domain\MonitoringServer\Interfaces\MonitoringServerConfigurationRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * This class is designed to represent a use case to reload the monitoring server configurations.
 *
 * @package Centreon\Domain\MonitoringServer\UseCase
 */
class ReloadAllConfigurations
{
    use LoggerTrait;

    /**
     * @param MonitoringServerRepositoryInterface $monitoringServerRepository
     * @param MonitoringServerConfigurationRepositoryInterface $configurationRepository
     * @param ReadAccessGroupRepositoryInterface $readAccessGroupRepositoryInterface
     * @param ContactInterface $contact
     */
    public function __construct(
        private readonly MonitoringServerRepositoryInterface $monitoringServerRepository,
        private readonly MonitoringServerConfigurationRepositoryInterface $configurationRepository,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepositoryInterface,
        private readonly ContactInterface $contact
    ) {
    }

    /**
     * @throws ConfigurationMonitoringServerException
     * @throws \Centreon\Domain\Exception\TimeoutException
     */
    public function execute(): void
    {
        try {
            if (
                ! $this->contact->hasTopologyRole(Contact::ROLE_CONFIGURATION_MONITORING_SERVER_READ)
                && ! $this->contact->hasTopologyRole(Contact::ROLE_CONFIGURATION_MONITORING_SERVER_READ_WRITE)
            ) {
                throw new AccessDeniedException(
                    'Insufficient rights (required: ROLE_CONFIGURATION_MONITORING_SERVER_READ or ROLE_CONFIGURATION_MONITORING_SERVER_READ_WRITE)'
                );
            }

            if (! $this->contact->isAdmin()) {
                $accessGroups = $this->readAccessGroupRepositoryInterface->findByContact($this->contact);

                $monitoringServers = $this->monitoringServerRepository->findServersWithRequestParametersAndAccessGroups(
                    $accessGroups
                );
            } else {
                $monitoringServers = $this->monitoringServerRepository->findServersWithRequestParameters();
            }
        } catch(AccessDeniedException $ex) {
            throw new AccessDeniedException($ex->getMessage());
        } catch (\Throwable $ex) {
            throw ConfigurationMonitoringServerException::errorRetrievingMonitoringServers($ex);
        }

        $lastMonitoringServerId = 0;

        try {
            foreach ($monitoringServers as $monitoringServer) {
                $lastMonitoringServerId = $monitoringServer->getId();
                if ($lastMonitoringServerId !== null) {
                    $this->info('Reload configuration for monitoring server #' . $lastMonitoringServerId);
                    $this->configurationRepository->reloadConfiguration($lastMonitoringServerId);
                } else {
                    $this->error('Monitoring server id from repository is null');
                }
            }
        } catch (TimeoutException $ex) {
            throw ConfigurationMonitoringServerException::timeout($lastMonitoringServerId, $ex->getMessage());
        } catch (\Exception $ex) {
            throw ConfigurationMonitoringServerException::errorOnReload(
                $lastMonitoringServerId,
                $ex->getMessage()
            );
        }
    }
}
