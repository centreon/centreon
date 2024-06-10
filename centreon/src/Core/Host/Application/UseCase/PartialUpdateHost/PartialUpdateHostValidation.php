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

namespace Core\Host\Application\UseCase\PartialUpdateHost;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Command\Application\Repository\ReadCommandRepositoryInterface;
use Core\Command\Domain\Model\CommandType;
use Core\Host\Application\Exception\HostException;
use Core\Host\Application\InheritanceManager;
use Core\Host\Application\Repository\ReadHostRepositoryInterface;
use Core\Host\Domain\Model\Host;
use Core\HostCategory\Application\Repository\ReadHostCategoryRepositoryInterface;
use Core\HostGroup\Application\Repository\ReadHostGroupRepositoryInterface;
use Core\HostSeverity\Application\Repository\ReadHostSeverityRepositoryInterface;
use Core\HostTemplate\Application\Repository\ReadHostTemplateRepositoryInterface;
use Core\MonitoringServer\Application\Repository\ReadMonitoringServerRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;
use Core\TimePeriod\Application\Repository\ReadTimePeriodRepositoryInterface;
use Core\Timezone\Application\Repository\ReadTimezoneRepositoryInterface;
use Core\ViewImg\Application\Repository\ReadViewImgRepositoryInterface;

class PartialUpdateHostValidation
{
    use LoggerTrait;

    /**
     * @param ReadHostRepositoryInterface $readHostRepository
     * @param ReadMonitoringServerRepositoryInterface $readMonitoringServerRepository
     * @param ReadHostTemplateRepositoryInterface $readHostTemplateRepository
     * @param ReadViewImgRepositoryInterface $readViewImgRepository
     * @param ReadTimePeriodRepositoryInterface $readTimePeriodRepository
     * @param ReadHostSeverityRepositoryInterface $readHostSeverityRepository
     * @param ReadTimezoneRepositoryInterface $readTimezoneRepository
     * @param ReadCommandRepositoryInterface $readCommandRepository
     * @param ReadHostCategoryRepositoryInterface $readHostCategoryRepository
     * @param ReadHostGroupRepositoryInterface $readHostGroupRepository
     * @param InheritanceManager $inheritanceManager
     * @param ContactInterface $user
     * @param AccessGroup[] $accessGroups
     */
    public function __construct(
        private readonly ReadHostRepositoryInterface $readHostRepository,
        private readonly ReadMonitoringServerRepositoryInterface $readMonitoringServerRepository,
        private readonly ReadHostTemplateRepositoryInterface $readHostTemplateRepository,
        private readonly ReadViewImgRepositoryInterface $readViewImgRepository,
        private readonly ReadTimePeriodRepositoryInterface $readTimePeriodRepository,
        private readonly ReadHostSeverityRepositoryInterface $readHostSeverityRepository,
        private readonly ReadTimezoneRepositoryInterface $readTimezoneRepository,
        private readonly ReadCommandRepositoryInterface $readCommandRepository,
        private readonly ReadHostCategoryRepositoryInterface $readHostCategoryRepository,
        private readonly ReadHostGroupRepositoryInterface $readHostGroupRepository,
        private readonly InheritanceManager $inheritanceManager,
        private readonly ContactInterface $user,
        public array $accessGroups = [],
    ) {
    }

    /**
     * Assert name is not already used.
     *
     * @param string $name
     * @param Host $host
     *
     * @throws HostException|\Throwable
     */
    public function assertIsValidName(string $name, Host $host): void
    {
        if ($host->isNameIdentical($name)) {

            return;
        }
        $formattedName = Host::formatName($name);
        if ($this->readHostRepository->existsByName($formattedName)) {
            $this->error('Host name already exists', compact('name', 'formattedName'));

            throw HostException::nameAlreadyExists($formattedName, $name);
        }

        if (str_starts_with($name, '_Module_')) {
            throw HostException::nameIsInvalid();
        }
    }

    /**
     * Assert monitoring server ID is valid.
     *
     * @param int $monitoringServerId
     *
     * @throws HostException|\Throwable
     */
    public function assertIsValidMonitoringServer(int $monitoringServerId): void
    {
        if ($monitoringServerId !== null) {
            $exists = ($this->accessGroups === [])
                ? $this->readMonitoringServerRepository->exists($monitoringServerId)
                : $this->readMonitoringServerRepository->existsByAccessGroups($monitoringServerId, $this->accessGroups);

            if (! $exists) {
                $this->error('Monitoring server does not exist', ['monitoringServerId' => $monitoringServerId]);

                throw HostException::idDoesNotExist('monitoringServerId', $monitoringServerId);
            }
        }
    }

    /**
     * Assert icon ID is valid.
     *
     * @param ?int $iconId
     *
     * @throws HostException|\Throwable
     */
    public function assertIsValidIcon(?int $iconId): void
    {
        if ($iconId !== null && false === $this->readViewImgRepository->existsOne($iconId)) {
            $this->error('Icon does not exist', ['icon_id' => $iconId]);

            throw HostException::idDoesNotExist('iconId', $iconId);
        }
    }

    /**
     * Assert time period ID is valid.
     *
     * @param ?int $timePeriodId
     * @param ?string $propertyName
     *
     * @throws HostException|\Throwable
     */
    public function assertIsValidTimePeriod(?int $timePeriodId, ?string $propertyName = null): void
    {
        if ($timePeriodId !== null && false === $this->readTimePeriodRepository->exists($timePeriodId) ) {
            $this->error('Time period does not exist', ['time_period_id' => $timePeriodId]);

            throw HostException::idDoesNotExist($propertyName ?? 'timePeriodId', $timePeriodId);
        }
    }

    /**
     * Assert host severity ID is valid.
     *
     * @param ?int $severityId
     *
     * @throws HostException|\Throwable
     */
    public function assertIsValidSeverity(?int $severityId): void
    {
        if ($severityId !== null) {
            $exists = ($this->accessGroups === [])
                ? $this->readHostSeverityRepository->exists($severityId)
                : $this->readHostSeverityRepository->existsByAccessGroups($severityId, $this->accessGroups);

            if (! $exists) {
                $this->error('Host severity does not exist', ['severity_id' => $severityId]);

                throw HostException::idDoesNotExist('severityId', $severityId);
            }
        }
    }

    /**
     * Assert timezone ID is valid.
     *
     * @param ?int $timezoneId
     *
     * @throws HostException
     */
    public function assertIsValidTimezone(?int $timezoneId): void
    {
        if ($timezoneId !== null && false === $this->readTimezoneRepository->exists($timezoneId) ) {
            $this->error('Timezone does not exist', ['timezone_id' => $timezoneId]);

            throw HostException::idDoesNotExist('timezoneId', $timezoneId);
        }
    }

    /**
     * Assert command ID is valid.
     *
     * @param ?int $commandId
     * @param ?CommandType $commandType
     * @param ?string $propertyName
     *
     * @throws HostException
     */
    public function assertIsValidCommand(
        ?int $commandId,
        ?CommandType $commandType = null,
        ?string $propertyName = null
    ): void {
        if ($commandId === null) {
            return;
        }

        if ($commandType === null && false === $this->readCommandRepository->exists($commandId)) {
            $this->error('Command does not exist', ['command_id' => $commandId]);

            throw HostException::idDoesNotExist($propertyName ?? 'commandId', $commandId);
        }
        if (
            $commandType !== null
            && false === $this->readCommandRepository->existsByIdAndCommandType($commandId, $commandType)
        ) {
            $this->error('Command does not exist', ['command_id' => $commandId, 'command_type' => $commandType]);

            throw HostException::idDoesNotExist($propertyName ?? 'commandId', $commandId);
        }
    }

    /**
     * Assert category IDs are valid.
     *
     * @param int[] $categoryIds
     *
     * @throws HostException
     * @throws \Throwable
     */
    public function assertAreValidCategories(array $categoryIds): void
    {
        if ($this->user->isAdmin()) {
            $validCategoryIds = $this->readHostCategoryRepository->exist($categoryIds);
        } else {
            $validCategoryIds = $this->readHostCategoryRepository->existByAccessGroups($categoryIds, $this->accessGroups);
        }

        if ([] !== ($invalidIds = array_diff($categoryIds, $validCategoryIds))) {
            throw HostException::idsDoNotExist('categories', $invalidIds);
        }
    }

    /**
     * Assert group IDs are valid.
     *
     * @param int[] $groupIds
     *
     * @throws HostException
     * @throws \Throwable
     */
    public function assertAreValidGroups(array $groupIds): void
    {
        if ($this->user->isAdmin()) {
            $validGroupIds = $this->readHostGroupRepository->exist($groupIds);
        } else {
            $validGroupIds = $this->readHostGroupRepository->existByAccessGroups($groupIds, $this->accessGroups);
        }

        if ([] !== ($invalidIds = array_diff($groupIds, $validGroupIds))) {
            throw HostException::idsDoNotExist('groups', $invalidIds);
        }
    }

    /**
     * Assert template IDs are valid.
     *
     * @param int[] $templateIds
     * @param int $hostId
     *
     * @throws HostException
     * @throws \Throwable
     */
    public function assertAreValidTemplates(array $templateIds, int $hostId): void
    {
         if ($templateIds === []) {

            return;
        }

        $validTemplateIds = $this->readHostTemplateRepository->exist($templateIds);

        if ([] !== ($invalidIds = array_diff($templateIds, $validTemplateIds))) {
            throw HostException::idsDoNotExist('templates', $invalidIds);
        }

        if (
            in_array($hostId, $templateIds, true)
            || false === $this->inheritanceManager->isValidInheritanceTree($hostId, $templateIds)
            ) {
            throw HostException::circularTemplateInheritance();
        }
    }
}
