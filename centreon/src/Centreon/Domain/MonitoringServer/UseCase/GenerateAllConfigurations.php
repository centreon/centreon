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

use Centreon\Domain\Authentication\Exception\AuthenticationException;
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
 * This class is designed to represent a use case to generate the monitoring server configurations.
 *
 * @package Centreon\Domain\MonitoringServer\UseCase
 */
class GenerateAllConfigurations
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
     * @throws PartialDeliveryException some monitoring servers have not been delivered, the others have
     * @throws GorgoneDeliveryException
     * @throws IncompleteGenerationException
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

        // MON-214616: every configuration is generated first, then all of them are moved at once. A monitoring
        // server which is not generated, or not delivered, does not stop the others: the error names each of them.
        /** @var array<int, string> $monitoringServerNames */
        $monitoringServerNames = [];
        /** @var array<int, string> $generationFailures reason, by monitoring server id */
        $generationFailures = [];
        $generatedMonitoringServerIds = [];
        $generationStillRunning = false;
        foreach ($monitoringServers as $monitoringServer) {
            $monitoringServerId = $monitoringServer->getId();
            if ($monitoringServerId === null) {
                $this->error('Monitoring server id from repository is null');
                continue;
            }
            if ($monitoringServer->isActivate() === false) {
                $this->info('Monitoring server #' . $monitoringServerId . ' is disabled');
                continue;
            }
            $monitoringServerNames[$monitoringServerId] = $monitoringServer->getName() ?? '#' . $monitoringServerId;
            if ($generationStillRunning) {
                // never two generations at once: the one which timed out may still be running
                $generationFailures[$monitoringServerId]
                    = _('not generated, the generation of a previous monitoring server did not finish in time');
                continue;
            }
            try {
                $this->info('Generate configuration files for monitoring server #' . $monitoringServerId);
                $this->configurationRepository->generateConfiguration($monitoringServerId);
                $generatedMonitoringServerIds[] = $monitoringServerId;
            } catch (TimeoutException $ex) {
                $this->error('Generation timeout on monitoring server #' . $monitoringServerId . ': ' . $ex->getMessage());
                $generationFailures[$monitoringServerId] = $ex->getMessage();
                $generationStillRunning = true;
            } catch (AuthenticationException $ex) {
                // nothing can be generated
                throw ConfigurationMonitoringServerException::errorOnGeneration($monitoringServerId, $ex->getMessage());
            } catch (\Exception $ex) {
                $this->error('Generation error on monitoring server #' . $monitoringServerId . ': ' . $ex->getMessage());
                $generationFailures[$monitoringServerId] = $ex->getMessage() !== ''
                    ? $ex->getMessage()
                    : _('the monitoring engine reported errors in its configuration');
            }
        }

        $generationError = $generationFailures === [] ? null : sprintf(
            _('%d of %d monitoring servers have not been generated, the others have: %s'),
            count($generationFailures),
            count($monitoringServerNames),
            implode(' | ', array_map(
                static fn (int $id, string $reason): string => $monitoringServerNames[$id] . ': ' . $reason,
                array_keys($generationFailures),
                $generationFailures
            ))
        );

        $delivered = $generatedMonitoringServerIds;
        $deliveryError = null;
        try {
            if ($generatedMonitoringServerIds !== []) {
                $this->info(
                    'Move configuration files for monitoring servers #' . implode(', #', $generatedMonitoringServerIds)
                );
                $this->configurationRepository->moveExportFilesOfAll($generatedMonitoringServerIds);
            }
        } catch (PartialDeliveryException $ex) {
            $delivered = $ex->getDeliveredMonitoringServerIds();
            $deliveryError = $ex;
        } catch (GorgoneDeliveryException|IncompleteGenerationException $ex) {
            $delivered = [];
            $deliveryError = $ex;
        } catch (TimeoutException $ex) {
            throw ConfigurationMonitoringServerException::timeoutOfAll(self::join($generationError, $ex->getMessage()));
        } catch (\Exception $ex) {
            throw ConfigurationMonitoringServerException::errorOfAll(self::join($generationError, $ex->getMessage()));
        }

        if ($generationError === null) {
            if ($deliveryError !== null) {
                throw $deliveryError;
            }

            return;
        }

        // Not generated: refused as an incomplete generation would be (409), the others can still be reloaded.
        throw new PartialDeliveryException(
            self::join($generationError, $deliveryError?->getMessage()),
            GorgoneDeliveryException::DELIVERY_FAILED,
            $delivered,
            $deliveryError
        );
    }

    private static function join(?string $first, ?string $second): string
    {
        return implode(' ', array_filter([$first, $second], static fn (?string $message): bool => $message !== null));
    }
}
