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

namespace Centreon\Domain\MonitoringServer\UseCase;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Exception\TimeoutException;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\MonitoringServer\Exception\ConfigurationMonitoringServerException;
use Centreon\Domain\MonitoringServer\Exception\GorgoneDeliveryException;
use Centreon\Domain\MonitoringServer\Exception\IncompleteGenerationException;
use Centreon\Domain\MonitoringServer\Exception\PartialDeliveryException;
use Centreon\Domain\MonitoringServer\Interfaces\MonitoringServerConfigurationRepositoryInterface;
use Centreon\Domain\MonitoringServer\Interfaces\MonitoringServerRepositoryInterface;
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
        private readonly ContactInterface $contact,
    ) {
    }

    /**
     * @throws ConfigurationMonitoringServerException
     * @throws TimeoutException
     */
    public function execute(): void
    {
        try {
            if ($this->contact->isAdmin()) {
                $monitoringServers = $this->monitoringServerRepository->findServersWithoutRequestParameters();
            } else {
                if (
                    ! $this->contact->hasTopologyRole(Contact::ROLE_CONFIGURATION_MONITORING_SERVER_READ)
                    && ! $this->contact->hasTopologyRole(Contact::ROLE_CONFIGURATION_MONITORING_SERVER_READ_WRITE)
                ) {
                    throw new AccessDeniedException(
                        'Insufficient rights (required: ROLE_CONFIGURATION_MONITORING_SERVER_READ or '
                            . 'ROLE_CONFIGURATION_MONITORING_SERVER_READ_WRITE)'
                    );
                }

                $accessGroups = $this->readAccessGroupRepositoryInterface->findByContact($this->contact);

                $monitoringServers = $this->monitoringServerRepository->findAllServersWithAccessGroups(
                    $accessGroups
                );
            }
        } catch (AccessDeniedException $ex) {
            throw new AccessDeniedException($ex->getMessage());
        } catch (\Throwable $ex) {
            throw ConfigurationMonitoringServerException::errorRetrievingMonitoringServers($ex);
        }

        $monitoringServerIds = [];
        foreach ($monitoringServers as $monitoringServer) {
            if ($monitoringServer->getId() !== null) {
                $monitoringServerIds[] = $monitoringServer->getId();
            } else {
                $this->error('Monitoring server id from repository is null');
            }
        }

        $this->reload($monitoringServerIds);
    }

    /**
     * MON-214616: after a partial delivery, the monitoring servers which have been delivered are still reloaded.
     * The delivery error is thrown afterwards, with the reload error when some of them are not reloaded either.
     *
     * @throws PartialDeliveryException
     * @throws GorgoneDeliveryException
     * @throws ConfigurationMonitoringServerException
     * @throws TimeoutException
     */
    public function executeAfterPartialDelivery(PartialDeliveryException $partialDelivery): never
    {
        try {
            $this->reload($partialDelivery->getDeliveredMonitoringServerIds());
        } catch (GorgoneDeliveryException $ex) {
            throw new GorgoneDeliveryException(
                $partialDelivery->getMessage() . ' ' . $ex->getMessage(),
                $partialDelivery->getCode() === $ex->getCode()
                    ? $partialDelivery->getCode()
                    : GorgoneDeliveryException::DELIVERY_FAILED,
                $partialDelivery,
            );
        }

        throw $partialDelivery;
    }

    /**
     * Every monitoring server is reloaded at once (MON-214616): one which is not reached does not stop the others.
     *
     * @param list<int> $monitoringServerIds
     *
     * @throws ConfigurationMonitoringServerException
     * @throws GorgoneDeliveryException
     * @throws IncompleteGenerationException
     * @throws TimeoutException
     */
    private function reload(array $monitoringServerIds): void
    {
        try {
            $this->info('Reload configuration for monitoring servers #' . implode(', #', $monitoringServerIds));
            $this->configurationRepository->reloadConfigurationOfAll($monitoringServerIds);
        } catch (TimeoutException $ex) {
            throw ConfigurationMonitoringServerException::timeoutOfAll($ex->getMessage());
        } catch (GorgoneDeliveryException|IncompleteGenerationException $ex) {
            throw $ex;
        } catch (\Exception $ex) {
            throw ConfigurationMonitoringServerException::errorOfAll($ex->getMessage());
        }
    }
}
