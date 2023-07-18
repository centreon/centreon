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

namespace Core\Host\Application\UseCase\AddHost;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Command\Application\Repository\ReadCommandRepositoryInterface;
use Core\Command\Domain\Model\CommandType;
use Core\Host\Application\Exception\HostException;
use Core\Host\Application\Repository\ReadHostRepositoryInterface;
use Core\Host\Domain\Model\Host;
use Core\HostCategory\Application\Repository\ReadHostCategoryRepositoryInterface;
use Core\HostSeverity\Application\Repository\ReadHostSeverityRepositoryInterface;
use Core\HostTemplate\Application\Repository\ReadHostTemplateRepositoryInterface;
use Core\MonitoringServer\Application\Repository\ReadMonitoringServerRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\TimePeriod\Application\Repository\ReadTimePeriodRepositoryInterface;
use Core\Timezone\Application\Repository\ReadTimezoneRepositoryInterface;
use Core\ViewImg\Application\Repository\ReadViewImgRepositoryInterface;

class AddHostValidation
{
    use LoggerTrait;

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
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly ContactInterface $user
    ) {
    }

    /**
     * Assert name is not already used.
     *
     * @param string $name
     *
     * @throws HostException|\Throwable
     */
    public function assertIsValidName(string $name): void
    {
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
        if (false === $this->readMonitoringServerRepository->exists($monitoringServerId)) {
            $this->error('Monitoring server does not exist', ['monitoringServerId' => $monitoringServerId]);

            throw HostException::idDoesNotExist('monitoringServerId', $monitoringServerId);
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
        if ($severityId !== null && false === $this->readHostSeverityRepository->exists($severityId) ) {
            $this->error('Host severity does not exist', ['severity_id' => $severityId]);

            throw HostException::idDoesNotExist('severityId', $severityId);
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
            $accessGroups = $this->readAccessGroupRepository->findByContact($this->user);
            $validCategoryIds = $this->readHostCategoryRepository->existByAccessGroups($categoryIds, $accessGroups);
        }

        if ([] !== ($invalidIds = array_diff($categoryIds, $validCategoryIds))) {
            throw HostException::idsDoNotExist('categories', $invalidIds);
        }
    }

    /**
     * Assert template IDs are valid.
     *
     * @param int[] $templateIds
     * @param int $hostTemplateId
     *
     * @throws HostException
     * @throws \Throwable
     */
    public function assertAreValidTemplates(array $templateIds, int $hostTemplateId): void
    {
        if (in_array($hostTemplateId, $templateIds, true)) {
            throw HostException::circularTemplateInheritance();
        }

        $validTemplateIds = $this->readHostTemplateRepository->exist($templateIds);

        if ([] !== ($invalidIds = array_diff($templateIds, $validTemplateIds))) {
            throw HostException::idsDoNotExist('templates', $invalidIds);
        }
    }
}
