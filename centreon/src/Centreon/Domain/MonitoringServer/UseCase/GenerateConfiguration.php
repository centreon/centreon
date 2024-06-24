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
use Centreon\Domain\Exception\EntityNotFoundException;
use Centreon\Domain\Exception\TimeoutException;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\MonitoringServer\Exception\ConfigurationMonitoringServerException;
use Centreon\Domain\MonitoringServer\Interfaces\MonitoringServerRepositoryInterface;
use Centreon\Domain\MonitoringServer\Interfaces\MonitoringServerConfigurationRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * This class is designed to represent a use case to generate a monitoring server configuration.
 *
 * @package Centreon\Domain\MonitoringServer\UseCase
 */
class GenerateConfiguration
{
    use LoggerTrait;

    /**
     * @param MonitoringServerRepositoryInterface $monitoringServerRepository
     * @param MonitoringServerConfigurationRepositoryInterface $configurationRepository
     */
    public function __construct(
        private readonly MonitoringServerRepositoryInterface $monitoringServerRepository,
        private readonly MonitoringServerConfigurationRepositoryInterface $configurationRepository,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepositoryInterface,
        private readonly ContactInterface $contact
    ) {
    }

    /**
     * @param int $monitoringServerId
     * @throws EntityNotFoundException
     * @throws ConfigurationMonitoringServerException
     * @throws TimeoutException
     */
    public function execute(int $monitoringServerId): void
    {
        try {
            if (
                ! $this->contact->hasTopologyRole(Contact::ROLE_CONFIGURATION_MONITORING_SERVER_READ)
                && ! $this->contact->hasTopologyRole(Contact::ROLE_CONFIGURATION_MONITORING_SERVER_READ_WRITE)
            ) {
                throw new AccessDeniedException();
            }

            if (! $this->contact->isAdmin()) {
                $accessGroups = $this->readAccessGroupRepositoryInterface->findByContact($this->contact);

                $monitoringServer = $this->monitoringServerRepository->findServerByIdAndAccessGroups(
                    $monitoringServerId,
                    $accessGroups
                );
            } else {
                $monitoringServer = $this->monitoringServerRepository->findServer($monitoringServerId);
            }

            if ($monitoringServer === null) {
                throw ConfigurationMonitoringServerException::notFound($monitoringServerId);
            }
            if ($monitoringServer->isActivate() === false) {
                throw ConfigurationMonitoringServerException::disabled($monitoringServerId);
            }
            $this->info('Generate configuration files for monitoring server #' . $monitoringServerId);
            $this->configurationRepository->generateConfiguration($monitoringServerId);
            $this->info('Move configuration files for monitoring server #' . $monitoringServerId);
            $this->configurationRepository->moveExportFiles($monitoringServerId);
        } catch (AccessDeniedException $ex) {
            throw new AccessDeniedException('You are not allowed to access this resource');
        } catch (EntityNotFoundException | TimeoutException $ex) {
            if ($ex instanceof TimeoutException) {
                throw ConfigurationMonitoringServerException::timeout($monitoringServerId, $ex->getMessage());
            }
            throw $ex;
        } catch (\Exception $ex) {
            throw ConfigurationMonitoringServerException::errorOnGeneration(
                $monitoringServerId,
                $ex->getMessage()
            );
        }
    }
}
