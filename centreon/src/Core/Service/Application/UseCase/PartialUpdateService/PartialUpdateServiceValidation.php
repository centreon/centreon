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

namespace Core\Service\Application\UseCase\PartialUpdateService;

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

class PartialUpdateServiceValidation
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
     * @param string $name
     * @param Service $service
     *
     * @throws \Throwable
     */
    public function assertIsValidName(string $name, Service $service): void
    {
        if ($service->isNameIdentical($name)) {

            return;
        }

        $nameToCheck = new TrimmedString(Service::formatName($name));

        $serviceNamesByHost = $this->readServiceRepository->findServiceNamesByHost($service->getHostId());
        if ($serviceNamesByHost === null) {
            // Should not be called if this assertion is called after assertion on host IDs
            throw ServiceException::idDoesNotExist('host', $service->getHostId());
        }

        if ($serviceNamesByHost->contains($nameToCheck)) {
            throw ServiceException::nameAlreadyExists((string) $nameToCheck, $service->getHostId());
        }
    }

    /**
     * @param int|null $serviceTemplateId
     *
     * @throws ServiceException
     * @throws \Throwable
     */
    public function assertIsValidTemplate(?int $serviceTemplateId): void
    {
        if ($serviceTemplateId !== null && ! $this->readServiceTemplateRepository->exists($serviceTemplateId)) {
            $this->error('Service does not exist', ['service_template_id' => $serviceTemplateId]);

            throw ServiceException::idDoesNotExist('service_template_id', $serviceTemplateId);
        }
    }

    /**
     * @param int|null $commandId
     * @param int|null $serviceTemplateId
     *
     * @throws ServiceException
     */
    public function assertIsValidCommand(?int $commandId, ?int $serviceTemplateId): void
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
     * @param int|null $graphTemplateId
     *
     * @throws ServiceException
     * @throws \Throwable
     */
    public function assertIsValidGraphTemplate(?int $graphTemplateId): void
    {
        if ($graphTemplateId !== null && ! $this->performanceGraphRepository->exists($graphTemplateId)) {
            $this->error('Performance graph does not exist', ['graph_template_id' => $graphTemplateId]);

            throw ServiceException::idDoesNotExist('graph_template_id', $graphTemplateId);
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
     * @param ?string $propertyName
     *
     * @throws ServiceException
     * @throws \Throwable
     */
    public function assertIsValidTimePeriod(?int $timePeriodId, ?string $propertyName = null): void
    {
        if ($timePeriodId !== null && ! $this->timePeriodRepository->exists($timePeriodId)) {
            $this->error('Time period does not exist', [$propertyName ?? 'timeperiod' => $timePeriodId]);

            throw ServiceException::idDoesNotExist($propertyName ?? 'timeperiod', $timePeriodId);
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
     * @param list<int> $categoriesIds
     *
     * @throws ServiceException
     * @throws \Throwable
     */
    public function assertAreValidCategories(array $categoriesIds): void
    {
        if ($this->user->isAdmin()) {
            $categoriesIdsFound = $this->readServiceCategoryRepository->findAllExistingIds(
                $categoriesIds
            );
        } else {
            $categoriesIdsFound = $this->readServiceCategoryRepository->findAllExistingIdsByAccessGroups(
                $categoriesIds,
                $this->accessGroups
            );
        }

        if ([] !== ($idsNotFound = array_diff($categoriesIds, $categoriesIdsFound))) {
            throw ServiceException::idsDoNotExist('service_categories', $idsNotFound);
        }
    }

    /**
     * @param int[] $groupIds
     *
     * @throws ServiceException
     * @throws \Throwable
     */
    public function assertAreValidGroups(array $groupIds): void
    {
        if ($groupIds === []) {

            return;
        }

        if ($this->user->isAdmin()) {
            $groupIdsFound = $this->readServiceGroupRepository->exist($groupIds);
        } else {
            $groupIdsFound = $this->readServiceGroupRepository->existByAccessGroups(
                $groupIds,
                $this->accessGroups
            );
        }

        if ([] !== ($idsNotFound = array_diff($groupIds, $groupIdsFound))) {
            throw ServiceException::idsDoNotExist('service_groups', $idsNotFound);
        }
    }
}
