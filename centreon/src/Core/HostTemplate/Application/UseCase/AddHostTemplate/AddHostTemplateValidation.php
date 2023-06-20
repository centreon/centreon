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

namespace Core\HostTemplate\Application\UseCase\AddHostTemplate;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Command\Application\Repository\ReadCommandRepositoryInterface;
use Core\Common\Domain\CommandType;
use Core\HostCategory\Application\Repository\ReadHostCategoryRepositoryInterface;
use Core\HostSeverity\Application\Repository\ReadHostSeverityRepositoryInterface;
use Core\HostTemplate\Application\Exception\HostTemplateException;
use Core\HostTemplate\Application\Repository\ReadHostTemplateRepositoryInterface;
use Core\HostTemplate\Domain\Model\HostTemplate;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\TimePeriod\Application\Repository\ReadTimePeriodRepositoryInterface;
use Core\Timezone\Application\Repository\ReadTimezoneRepositoryInterface;
use Core\ViewImg\Application\Repository\ReadViewImgRepositoryInterface;

class AddHostTemplateValidation
{
    use LoggerTrait;

    public function __construct(
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
     * @throws HostTemplateException|\Throwable
     */
    public function assertIsValidName(string $name): void
    {
        $formattedName = HostTemplate::formatName($name);
        if ($this->readHostTemplateRepository->existsByName($formattedName)) {
            $this->error('Host template name already exists', compact('name', 'formattedName'));

            throw HostTemplateException::nameAlreadyExists($formattedName, $name);
        }
    }

    /**
     * Assert icon ID is valid.
     *
     * @param ?int $iconId
     *
     * @throws HostTemplateException|\Throwable
     */
    public function assertIsValidIcon(?int $iconId): void
    {
        if ($iconId !== null && false === $this->readViewImgRepository->existsOne($iconId)) {
            $this->error('Icon does not exist', ['icon_id' => $iconId]);

            throw HostTemplateException::idDoesNotExist('iconId', $iconId);
        }
    }

    /**
     * Assert time period ID is valid.
     *
     * @param ?int $timePeriodId
     * @param ?string $propertyName
     *
     * @throws HostTemplateException|\Throwable
     */
    public function assertIsValidTimePeriod(?int $timePeriodId, ?string $propertyName = null): void
    {
        if ($timePeriodId !== null && false === $this->readTimePeriodRepository->exists($timePeriodId) ) {
            $this->error('Time period does not exist', ['time_period_id' => $timePeriodId]);

            throw HostTemplateException::idDoesNotExist($propertyName ?? 'timePeriodId', $timePeriodId);
        }
    }

    /**
     * Assert host severity ID is valid.
     *
     * @param ?int $severityId
     *
     * @throws HostTemplateException|\Throwable
     */
    public function assertIsValidSeverity(?int $severityId): void
    {
        if ($severityId !== null && false === $this->readHostSeverityRepository->exists($severityId) ) {
            $this->error('Host severity does not exist', ['severity_id' => $severityId]);

            throw HostTemplateException::idDoesNotExist('severityId', $severityId);
        }
    }

    /**
     * Assert timezone ID is valid.
     *
     * @param ?int $timezoneId
     *
     * @throws HostTemplateException
     */
    public function assertIsValidTimezone(?int $timezoneId): void
    {
        if ($timezoneId !== null && false === $this->readTimezoneRepository->exists($timezoneId) ) {
            $this->error('Timezone does not exist', ['timezone_id' => $timezoneId]);

            throw HostTemplateException::idDoesNotExist('timezoneId', $timezoneId);
        }
    }

    /**
     * Assert command ID is valid.
     *
     * @param ?int $commandId
     * @param ?CommandType $commandType
     * @param ?string $propertyName
     *
     * @throws HostTemplateException
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

            throw HostTemplateException::idDoesNotExist($propertyName ?? 'commandId', $commandId);
        }
        if (
            $commandType !== null
            && false === $this->readCommandRepository->existsByIdAndCommandType($commandId, $commandType)
        ) {
            $this->error('Command does not exist', ['command_id' => $commandId, 'command_type' => $commandType]);

            throw HostTemplateException::idDoesNotExist($propertyName ?? 'commandId', $commandId);
        }
    }

    /**
     * Assert category IDs are valid.
     *
     * @param int[] $categoryIds
     *
     * @throws HostTemplateException
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
            throw HostTemplateException::idsDoNotExist('categories', $invalidIds);
        }
    }

    /**
     * Assert template IDs are valid.
     *
     * @param int[] $templateIds
     * @param int $hostTemplateId
     *
     * @throws HostTemplateException
     * @throws \Throwable
     */
    public function assertAreValidTemplates(array $templateIds, int $hostTemplateId): void
    {
        if (in_array($hostTemplateId, $templateIds, true)) {
            throw HostTemplateException::circularTemplateInheritance();
        }

        $validTemplateIds = $this->readHostTemplateRepository->exist($templateIds);

        if ([] !== ($invalidIds = array_diff($templateIds, $validTemplateIds))) {
            throw HostTemplateException::idsDoNotExist('templates', $invalidIds);
        }
    }
}
