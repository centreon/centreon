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

namespace Core\ServiceTemplate\Application\UseCase\AddServiceTemplate;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Command\Application\Repository\ReadCommandRepositoryInterface;
use Core\Command\Domain\Model\CommandType;
use Core\HostTemplate\Application\Repository\ReadHostTemplateRepositoryInterface;
use Core\PerformanceGraph\Application\Repository\ReadPerformanceGraphRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;
use Core\ServiceCategory\Application\Repository\ReadServiceCategoryRepositoryInterface;
use Core\ServiceGroup\Application\Repository\ReadServiceGroupRepositoryInterface;
use Core\ServiceSeverity\Application\Repository\ReadServiceSeverityRepositoryInterface;
use Core\ServiceTemplate\Application\Exception\ServiceTemplateException;
use Core\ServiceTemplate\Application\Repository\ReadServiceTemplateRepositoryInterface;
use Core\TimePeriod\Application\Repository\ReadTimePeriodRepositoryInterface;
use Core\ViewImg\Application\Repository\ReadViewImgRepositoryInterface;

class AddServiceTemplateValidation
{
    use LoggerTrait;

    /**
     * @param ReadServiceTemplateRepositoryInterface $readServiceTemplateRepository
     * @param ReadServiceSeverityRepositoryInterface $serviceSeverityRepository
     * @param ReadPerformanceGraphRepositoryInterface $performanceGraphRepository
     * @param ReadCommandRepositoryInterface $commandRepository
     * @param ReadTimePeriodRepositoryInterface $timePeriodRepository
     * @param ReadViewImgRepositoryInterface $imageRepository
     * @param ReadHostTemplateRepositoryInterface $readHostTemplateRepository
     * @param ReadServiceCategoryRepositoryInterface $readServiceCategoryRepository
     * @param ReadServiceGroupRepositoryInterface $readServiceGroupRepository
     * @param ContactInterface $user
     * @param AccessGroup[] $accessGroups
     */
    public function __construct(
        private readonly ReadServiceTemplateRepositoryInterface $readServiceTemplateRepository,
        private readonly ReadServiceSeverityRepositoryInterface $serviceSeverityRepository,
        private readonly ReadPerformanceGraphRepositoryInterface $performanceGraphRepository,
        private readonly ReadCommandRepositoryInterface $commandRepository,
        private readonly ReadTimePeriodRepositoryInterface $timePeriodRepository,
        private readonly ReadViewImgRepositoryInterface $imageRepository,
        private readonly ReadHostTemplateRepositoryInterface $readHostTemplateRepository,
        private readonly ReadServiceCategoryRepositoryInterface $readServiceCategoryRepository,
        private readonly ReadServiceGroupRepositoryInterface $readServiceGroupRepository,
        private readonly ContactInterface $user,
        public array $accessGroups = []
    ) {
    }

    /**
     * @param int|null $serviceTemplateId
     *
     * @throws ServiceTemplateException
     * @throws \Throwable
     */
    public function assertIsValidServiceTemplate(?int $serviceTemplateId): void
    {
        if ($serviceTemplateId !== null && ! $this->readServiceTemplateRepository->exists($serviceTemplateId)) {
            $this->error('Service template does not exist', ['service_template_id' => $serviceTemplateId]);

            throw ServiceTemplateException::idDoesNotExist('service_template_id', $serviceTemplateId);
        }
    }

    /**
     * @param int|null $commandId
     *
     * @throws ServiceTemplateException
     */
    public function assertIsValidCommand(?int $commandId): void
    {
        if (
            $commandId !== null
            && ! $this->commandRepository->existsByIdAndCommandType($commandId, CommandType::Check)
        ) {
            $this->error('Check command does not exist', ['check_command_id' => $commandId]);

            throw ServiceTemplateException::idDoesNotExist('check_command_id', $commandId);
        }
    }

    /**
     * @param int|null $eventHandlerId
     *
     * @throws ServiceTemplateException
     */
    public function assertIsValidEventHandler(?int $eventHandlerId): void
    {
        if ($eventHandlerId !== null && ! $this->commandRepository->exists($eventHandlerId)) {
            $this->error('Event handler command does not exist', ['event_handler_command_id' => $eventHandlerId]);

            throw ServiceTemplateException::idDoesNotExist('event_handler_command_id', $eventHandlerId);
        }
    }

    /**
     * @param int|null $timePeriodId
     *
     * @throws ServiceTemplateException
     * @throws \Throwable
     */
    public function assertIsValidTimePeriod(?int $timePeriodId): void
    {
        if ($timePeriodId !== null && ! $this->timePeriodRepository->exists($timePeriodId)) {
            $this->error('Time period does not exist', ['check_timeperiod_id' => $timePeriodId]);

            throw ServiceTemplateException::idDoesNotExist('check_timeperiod_id', $timePeriodId);
        }
    }

    /**
     * @param int|null $iconId
     *
     * @throws ServiceTemplateException
     * @throws \Throwable
     */
    public function assertIsValidIcon(?int $iconId): void
    {
        if ($iconId !== null && ! $this->imageRepository->existsOne($iconId)) {
            $this->error('Icon does not exist', ['icon_id' => $iconId]);

            throw ServiceTemplateException::idDoesNotExist('icon_id', $iconId);
        }
    }

    /**
     * @param int|null $notificationTimePeriodId
     *
     * @throws ServiceTemplateException
     * @throws \Throwable
     */
    public function assertIsValidNotificationTimePeriod(?int $notificationTimePeriodId): void
    {
        if ($notificationTimePeriodId !== null && ! $this->timePeriodRepository->exists($notificationTimePeriodId)) {
            $this->error(
                'Notification time period does not exist',
                ['notification_timeperiod_id' => $notificationTimePeriodId]
            );

            throw ServiceTemplateException::idDoesNotExist('notification_timeperiod_id', $notificationTimePeriodId);
        }
    }

    /**
     * @param int|null $severityId
     *
     * @throws ServiceTemplateException
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

                throw ServiceTemplateException::idDoesNotExist('severity_id', $severityId);
            }
        }
    }

    /**
     * @param int|null $graphTemplateId
     *
     * @throws ServiceTemplateException
     * @throws \Throwable
     */
    public function assertIsValidPerformanceGraph(?int $graphTemplateId): void
    {
        if ($graphTemplateId !== null && ! $this->performanceGraphRepository->exists($graphTemplateId)) {
            $this->error('Performance graph does not exist', ['graph_template_id' => $graphTemplateId]);

            throw ServiceTemplateException::idDoesNotExist('graph_template_id', $graphTemplateId);
        }
    }

    /**
     * @param list<int> $hostTemplateIds
     *
     * @throws ServiceTemplateException
     */
    public function assertIsValidHostTemplates(array $hostTemplateIds): void
    {
        if (! empty($hostTemplateIds)) {
            $hostTemplateIds = array_unique($hostTemplateIds);
            $hostTemplateIdsFound = $this->readHostTemplateRepository->findAllExistingIds($hostTemplateIds);
            if ([] !== ($diff = array_diff($hostTemplateIds, $hostTemplateIdsFound))) {
                throw ServiceTemplateException::idsDoNotExist('host_templates', $diff);
            }
        }
    }

    /**
     * @param int[] $serviceCategoriesIds
     *
     * @throws ServiceTemplateException
     * @throws \Throwable
     */
    public function assertIsValidServiceCategories(array $serviceCategoriesIds): void
    {
        if (empty($serviceCategoriesIds)) {

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
            throw ServiceTemplateException::idsDoNotExist('service_categories', $idsNotFound);
        }
    }

    /**
     * @param ServiceGroupDto[] $serviceGroups
     * @param int[] $hostTemplateIds
     *
     * @throws ServiceTemplateException
     * @throws \Throwable
     */
    public function assertIsValidServiceGroups(array $serviceGroups, array $hostTemplateIds): void
    {
        if (empty($serviceGroups)) {

            return;
        }

        $serviceGroupIds = [];
        foreach ($serviceGroups as $serviceGroup) {
            if (false === in_array($serviceGroup->hostTemplateId, $hostTemplateIds, true)) {
                throw ServiceTemplateException::invalidServiceGroupAssociation();
            }
            $serviceGroupIds[] = $serviceGroup->serviceGroupId;
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
            throw ServiceTemplateException::idsDoNotExist('service_groups', $idsNotFound);
        }
    }
}
