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

namespace Core\Service\Application\UseCase\DeployServices;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Common\Domain\TrimmedString;
use Core\Host\Application\Repository\ReadHostRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;
use Core\Service\Application\Repository\ReadServiceRepositoryInterface;
use Core\Service\Application\Repository\WriteRealTimeServiceRepositoryInterface;
use Core\Service\Application\Repository\WriteServiceRepositoryInterface;
use Core\Service\Domain\Model\NewService;
use Core\Service\Domain\Model\Service;
use Core\ServiceTemplate\Application\Repository\ReadServiceTemplateRepositoryInterface;

final class DeployServices
{
    use LoggerTrait;

    public function __construct(
        private readonly ContactInterface $contact,
        private readonly DataStorageEngineInterface $dataStorageEngine,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly ReadHostRepositoryInterface $readHostRepository,
        private readonly ReadServiceRepositoryInterface $readServiceRepository,
        private readonly ReadServiceTemplateRepositoryInterface $readServiceTemplateRepository,
        private readonly WriteServiceRepositoryInterface $writeServiceRepository,
        private readonly WriteRealTimeServiceRepositoryInterface $writeRealTimeServiceRepository
    ) {}

    /**
     * @param DeployServicesPresenterInterface $presenter
     * @param int $hostId
     */
    public function __invoke(DeployServicesPresenterInterface $presenter, int $hostId): void
    {
        try {
            if (
                ! $this->contact->hasTopologyRole(Contact::ROLE_CONFIGURATION_HOSTS_READ)
                && ! $this->contact->hasTopologyRole(Contact::ROLE_CONFIGURATION_HOSTS_WRITE)
            ) {
                $this->error('User does not have sufficient rights', ['user_id' => $this->contact->getId()]);
                $response = new ForbiddenResponse('User does not have sufficient rights');
                $presenter->presentResponse($response);

                return;
            }

            $hostParents = $this->readHostRepository->findParents($hostId);
            if ($hostParents === []) {
                $this->info(
                    'Services cannot be deployed: requested host does not have associated host templates',
                    ['host_id' => $hostId]
                );
                $response = new NoContentResponse();
                $presenter->presentResponse($response);

                return;
            }

            if ($this->contact->isAdmin()) {
                if (! $this->readHostRepository->exists($hostId)) {
                    $this->error('Host with provided id is not found', ['host_id' => $hostId]);
                    $response = new NotFoundResponse('Host');
                    $presenter->presentResponse($response);

                    return;
                }

                $deployedServices = $this->deployServicesInTransaction($hostParents, $hostId);
            } else {
                $accessGroups = $this->readAccessGroupRepository->findByContact($this->contact);

                if (! $this->readHostRepository->existsByAccessGroups($hostId, $accessGroups)) {
                    $this->error('Host with provided id is not found', ['host_id' => $hostId]);
                    $response = new NotFoundResponse('Host');
                    $presenter->presentResponse($response);

                    return;
                }

                $deployedServices = $this->deployServicesInTransaction($hostParents, $hostId, $accessGroups);
            }

            if ($deployedServices === []) {
                $this->info(
                    'Services cannot be deployed: requested host already contains all deployed services',
                    ['host_id' => $hostId]
                );
                $response = new NoContentResponse();
                $presenter->presentResponse($response);

                return;
            }

            $response = $this->createResponse($deployedServices);
            $presenter->presentResponse($response);
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $response = new ErrorResponse($ex->getMessage());
            $presenter->presentResponse($response);
        }
    }

    /**
     * @param Service[] $deployedServices
     *
     * @return DeployServicesResponse
     */
    private function createResponse(array $deployedServices): DeployServicesResponse
    {
        $response = new DeployServicesResponse();
        foreach ($deployedServices as $deployedService) {
            $deployServiceDto = new DeployServiceDto();
            $deployServiceDto->id = $deployedService->getId();
            $deployServiceDto->name = $deployedService->getName();
            $deployServiceDto->hostId = $deployedService->getHostId();
            $deployServiceDto->geoCoords = $deployedService->getGeoCoords()?->__toString();
            $deployServiceDto->comment = $deployedService->getComment();
            $deployServiceDto->serviceTemplateId = $deployedService->getServiceTemplateParentId();
            $deployServiceDto->commandId = $deployedService->getCommandId();
            $deployServiceDto->commandArguments = $deployedService->getCommandArguments();
            $deployServiceDto->checkTimePeriodId = $deployedService->getCheckTimePeriodId();
            $deployServiceDto->maxCheckAttempts = $deployedService->getMaxCheckAttempts();
            $deployServiceDto->normalCheckInterval = $deployedService->getNormalCheckInterval();
            $deployServiceDto->retryCheckInterval = $deployedService->getRetryCheckInterval();
            $deployServiceDto->activeChecksEnabled = $deployedService->getActiveChecks();
            $deployServiceDto->passiveChecksEnabled = $deployedService->getPassiveCheck();
            $deployServiceDto->volatilityEnabled = $deployedService->getVolatility();
            $deployServiceDto->notificationsEnabled = $deployedService->getNotificationsEnabled();
            $deployServiceDto->isContactAdditiveInheritance = $deployedService->isContactAdditiveInheritance();
            $deployServiceDto->isContactGroupAdditiveInheritance = $deployedService->isContactGroupAdditiveInheritance();
            $deployServiceDto->notificationInterval = $deployedService->getNotificationInterval();
            $deployServiceDto->notificationTimePeriodId = $deployedService->getNotificationTimePeriodId();
            $deployServiceDto->notificationTypes = $deployedService->getNotificationTypes();
            $deployServiceDto->firstNotificationDelay = $deployedService->getFirstNotificationDelay();
            $deployServiceDto->recoveryNotificationDelay = $deployedService->getRecoveryNotificationDelay();
            $deployServiceDto->acknowledgementTimeout = $deployedService->getAcknowledgementTimeout();
            $deployServiceDto->checkFreshness = $deployedService->getCheckFreshness();
            $deployServiceDto->freshnessThreshold = $deployedService->getFreshnessThreshold();
            $deployServiceDto->flapDetectionEnabled = $deployedService->getFlapDetectionEnabled();
            $deployServiceDto->lowFlapThreshold = $deployedService->getLowFlapThreshold();
            $deployServiceDto->highFlapThreshold = $deployedService->getHighFlapThreshold();
            $deployServiceDto->eventHandlerEnabled = $deployedService->getEventHandlerEnabled();
            $deployServiceDto->eventHandlerCommandId = $deployedService->getEventHandlerId();
            $deployServiceDto->eventHandlerArguments = $deployedService->getEventHandlerArguments();
            $deployServiceDto->graphTemplateId = $deployedService->getGraphTemplateId();
            $deployServiceDto->note = $deployedService->getNote();
            $deployServiceDto->noteUrl = $deployedService->getNoteUrl();
            $deployServiceDto->actionUrl = $deployedService->getActionUrl();
            $deployServiceDto->iconId = $deployedService->getIconId();
            $deployServiceDto->iconAlternative = $deployedService->getIconAlternativeText();
            $deployServiceDto->severityId = $deployedService->getSeverityId();
            $deployServiceDto->isActivated = $deployedService->isActivated();

            $response->services[] = $deployServiceDto;
        }

        return $response;
    }

    /**
     * @param array<array{parent_id:int,child_id:int,order:int}> $hostParents
     * @param int $hostId
     * @param AccessGroup[] $accessGroups
     *
     * @throws \Throwable
     *
     * @return Service[]
     */
    private function deployServicesInTransaction(array $hostParents, int $hostId, array $accessGroups = []): array
    {
        $deployedServices = [];

        $this->dataStorageEngine->startTransaction();
        try {
            foreach ($hostParents as $hostParent) {
                $serviceTemplates = $this->readServiceTemplateRepository->findByHostId($hostParent['parent_id']);
                foreach ($serviceTemplates as $serviceTemplate) {
                    $serviceNames = $this->readServiceRepository->findServiceNamesByHost($hostId);
                    if (
                        $serviceNames === null
                        || $serviceNames->contains(new TrimmedString($serviceTemplate->getAlias()))
                    ) {
                        continue;
                    }
                    $service = new NewService(
                        $serviceTemplate->getAlias(),
                        $hostId,
                        $serviceTemplate->getCommandId()
                    );
                    $service->setServiceTemplateParentId($serviceTemplate->getId());
                    $service->setActivated(true);
                    $serviceId = $this->writeServiceRepository->add($service);
                    $service = $this->readServiceRepository->findById($serviceId);
                    if ($service !== null) {
                        $deployedServices[] = $service;
                    }
                }
            }

            if ($accessGroups !== []) {
                foreach ($deployedServices as $deployedService) {
                    $this->writeRealTimeServiceRepository->addServiceToResourceAcls(
                        $hostId,
                        $deployedService->getId(),
                        $accessGroups
                    );
                }
            }

            $this->dataStorageEngine->commitTransaction();

            return $deployedServices;
        } catch (\Throwable $ex) {
            $this->error("Rollback of 'DeployServices' transaction", ['trace' => $ex->getTraceAsString()]);
            $this->dataStorageEngine->rollbackTransaction();

            throw $ex;
        }
    }
}
