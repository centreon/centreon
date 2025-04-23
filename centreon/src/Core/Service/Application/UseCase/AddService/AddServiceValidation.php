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

namespace Core\Service\Application\UseCase\AddService;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Command\Application\Repository\ReadCommandRepositoryInterface;
use Core\Command\Domain\Model\CommandType;
use Core\Common\Domain\TrimmedString;
use Core\Host\Application\Repository\ReadHostRepositoryInterface;
use Core\PerformanceGraph\Application\Repository\ReadPerformanceGraphRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;
use Core\Service\Application\Exception\ServiceException;
use Core\Service\Application\Repository\ReadServiceRepositoryInterface;
use Core\Service\Domain\Model\Service;
use Core\ServiceCategory\Application\Repository\ReadServiceCategoryRepositoryInterface;
use Core\ServiceGroup\Application\Repository\ReadServiceGroupRepositoryInterface;
use Core\ServiceSeverity\Application\Repository\ReadServiceSeverityRepositoryInterface;
use Core\ServiceTemplate\Application\Repository\ReadServiceTemplateRepositoryInterface;
use Core\TimePeriod\Application\Repository\ReadTimePeriodRepositoryInterface;
use Core\ViewImg\Application\Repository\ReadViewImgRepositoryInterface;

class AddServiceValidation
{
    use LoggerTrait;

    /** @var AccessGroup[] */
    public array $accessGroups = [];

    public function __construct(
        private readonly ReadServiceTemplateRepositoryInterface $readServiceTemplateRepository,
        private readonly ReadServiceRepositoryInterface $readServiceRepository,
        private readonly ReadServiceSeverityRepositoryInterface $serviceSeverityRepository,
        private readonly ReadPerformanceGraphRepositoryInterface $performanceGraphRepository,
        private readonly ReadCommandRepositoryInterface $commandRepository,
        private readonly ReadTimePeriodRepositoryInterface $timePeriodRepository,
        private readonly ReadViewImgRepositoryInterface $imageRepository,
        private readonly ReadHostRepositoryInterface $readHostRepository,
        private readonly ReadServiceCategoryRepositoryInterface $readServiceCategoryRepository,
        private readonly ReadServiceGroupRepositoryInterface $readServiceGroupRepository,
        private readonly ContactInterface $user,
    ) {
    }

    /**
     * @param int|null $severityId
     *
     * @throws ServiceException
     * @throws \Throwable
     */
    public function assertIsValidSeverity(?int $severityId): void
    {
        if ($severityId !== null) {
            $exists = ($this->accessGroups === [])
                ? $this->serviceSeverityRepository->exists($severityId)
                : $this->serviceSeverityRepository->existsByAccessGroups($severityId, $this->accessGroups);

            if (! $exists) {
                $this->error('Service severity does not exist', ['severity_id' => $severityId]);

                throw ServiceException::idDoesNotExist('severity_id', $severityId);
            }
        }
    }

    /**
     * @param int|null $graphTemplateId
     *
     * @throws ServiceException
     * @throws \Throwable
     */
    public function assertIsValidPerformanceGraph(?int $graphTemplateId): void
    {
        if ($graphTemplateId !== null && ! $this->performanceGraphRepository->exists($graphTemplateId)) {
            $this->error('Performance graph does not exist', ['graph_template_id' => $graphTemplateId]);

            throw ServiceException::idDoesNotExist('graph_template_id', $graphTemplateId);
        }
    }

    /**
     * @param int $hostId
     *
     * @throws ServiceException
     */
    public function assertIsValidHost(int $hostId): void
    {
        $hostIdFound = $this->user->isAdmin()
                ? $this->readHostRepository->exists($hostId)
                : $this->readHostRepository->existsByAccessGroups($hostId, $this->accessGroups);
        if (false === $hostIdFound) {
            throw ServiceException::idDoesNotExist('host_id', $hostId);
        }
    }

    /**
     * @param list<int> $serviceCategoriesIds
     *
     * @throws ServiceException
     * @throws \Throwable
     */
    public function assertIsValidServiceCategories(array $serviceCategoriesIds): void
    {
        if ($serviceCategoriesIds === []) {

            return;
        }

        if ($this->user->isAdmin()) {
            $serviceCategoriesIdsFound = $this->readServiceCategoryRepository->findAllExistingIds(
                $serviceCategoriesIds
            );
        } else {
            $serviceCategoriesIdsFound = $this->readServiceCategoryRepository->findAllExistingIdsByAccessGroups(
                $serviceCategoriesIds,
                $this->accessGroups
            );
        }

        if ([] !== ($idsNotFound = array_diff($serviceCategoriesIds, $serviceCategoriesIdsFound))) {
            throw ServiceException::idsDoNotExist('service_categories', $idsNotFound);
        }
    }

    /**
     * @param int|null $commandId
     * @param int|null $serviceTemplateId
     *
     * @throws ServiceException
     */
    public function assertIsValidCommandForOnPremPlatform(?int $commandId, ?int $serviceTemplateId): void
    {
        if ($commandId === null && $serviceTemplateId === null) {
            throw ServiceException::checkCommandCannotBeNull();
        }
        if ($commandId !== null && ! $this->commandRepository->existsByIdAndCommandType($commandId, CommandType::Check))
        {
            $this->error('The check command does not exist', ['check_command_id' => $commandId]);

            throw ServiceException::idDoesNotExist('check_command_id', $commandId);
        }
    }

    /**
     * @param int|null $eventHandlerId
     *
     * @throws ServiceException
     */
    public function assertIsValidEventHandler(?int $eventHandlerId): void
    {
        if ($eventHandlerId !== null && ! $this->commandRepository->exists($eventHandlerId)) {
            $this->error('Event handler command does not exist', ['event_handler_command_id' => $eventHandlerId]);

            throw ServiceException::idDoesNotExist('event_handler_command_id', $eventHandlerId);
        }
    }

    /**
     * @param int|null $timePeriodId
     *
     * @throws ServiceException
     * @throws \Throwable
     */
    public function assertIsValidTimePeriod(?int $timePeriodId): void
    {
        if ($timePeriodId !== null && ! $this->timePeriodRepository->exists($timePeriodId)) {
            $this->error('Time period does not exist', ['check_timeperiod_id' => $timePeriodId]);

            throw ServiceException::idDoesNotExist('check_timeperiod_id', $timePeriodId);
        }
    }

    /**
     * @param int|null $iconId
     *
     * @throws ServiceException
     * @throws \Throwable
     */
    public function assertIsValidIcon(?int $iconId): void
    {
        if ($iconId !== null && ! $this->imageRepository->existsOne($iconId)) {
            $this->error('Icon does not exist', ['icon_id' => $iconId]);

            throw ServiceException::idDoesNotExist('icon_id', $iconId);
        }
    }

    /**
     * @param int|null $notificationTimePeriodId
     *
     * @throws ServiceException
     * @throws \Throwable
     */
    public function assertIsValidNotificationTimePeriod(?int $notificationTimePeriodId): void
    {
        if ($notificationTimePeriodId !== null && ! $this->timePeriodRepository->exists($notificationTimePeriodId)) {
            $this->error(
                'Notification time period does not exist',
                ['notification_timeperiod_id' => $notificationTimePeriodId]
            );

            throw ServiceException::idDoesNotExist('notification_timeperiod_id', $notificationTimePeriodId);
        }
    }

    /**
     * @param int|null $serviceTemplateId
     *
     * @throws ServiceException
     * @throws \Throwable
     */
    public function assertIsValidServiceTemplate(?int $serviceTemplateId): void
    {
        if ($serviceTemplateId !== null && ! $this->readServiceTemplateRepository->exists($serviceTemplateId)) {
            $this->error('Service does not exist', ['service_template_id' => $serviceTemplateId]);

            throw ServiceException::idDoesNotExist('service_template_id', $serviceTemplateId);
        }
    }

    /**
     * @param AddServiceRequest $request
     *
     * @throws \Throwable
     */
    public function assertServiceName(AddServiceRequest $request): void
    {
        $nameToCheck = new TrimmedString(Service::formatName($request->name));

        $serviceNamesByHost = $this->readServiceRepository->findServiceNamesByHost($request->hostId);
        if ($serviceNamesByHost === null) {
            // Should not be called if this assertion is called after assertion on host IDs
            throw ServiceException::idDoesNotExist('host', $request->hostId);
        }

        if ($serviceNamesByHost->contains($nameToCheck)) {
            throw ServiceException::nameAlreadyExists((string) $nameToCheck, $request->hostId);
        }
    }

    /**
     * @param int[] $serviceGroupIds
     * @param int $hostId
     *
     * @throws ServiceException
     * @throws \Throwable
     */
    public function assertIsValidServiceGroups(array $serviceGroupIds, int $hostId): void
    {
        if ($serviceGroupIds === []) {

            return;
        }

        if ($this->user->isAdmin()) {
            $serviceGroupIdsFound = $this->readServiceGroupRepository->exist($serviceGroupIds);
        } else {
            $serviceGroupIdsFound = $this->readServiceGroupRepository->existByAccessGroups(
                $serviceGroupIds,
                $this->accessGroups
            );
        }

        if ([] !== ($idsNotFound = array_diff($serviceGroupIds, $serviceGroupIdsFound))) {
            throw ServiceException::idsDoNotExist('service_groups', $idsNotFound);
        }
    }
}
