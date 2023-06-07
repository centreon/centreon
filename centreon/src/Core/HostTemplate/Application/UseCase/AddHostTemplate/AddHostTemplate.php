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

use Assert\AssertionFailedException;
use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Option\OptionService;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\Application\Common\UseCase\ConflictResponse;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Command\Application\Repository\ReadCommandRepositoryInterface;
use Core\Common\Domain\CommandType;
use Core\HostCategory\Application\Repository\ReadHostCategoryRepositoryInterface;
use Core\HostCategory\Application\Repository\WriteHostCategoryRepositoryInterface;
use Core\HostSeverity\Application\Repository\ReadHostSeverityRepositoryInterface;
use Core\HostTemplate\Application\Exception\HostTemplateException;
use Core\HostTemplate\Application\Repository\ReadHostTemplateRepositoryInterface;
use Core\HostTemplate\Application\Repository\WriteHostTemplateRepositoryInterface;
use Core\HostTemplate\Domain\Model\HostTemplate;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\TimePeriod\Application\Repository\ReadTimePeriodRepositoryInterface;
use Core\Timezone\Application\Repository\ReadTimezoneRepositoryInterface;
use Core\ViewImg\Application\Repository\ReadViewImgRepositoryInterface;

final class AddHostTemplate
{
    use LoggerTrait;

    public function __construct(
        private readonly WriteHostTemplateRepositoryInterface $writeHostTemplateRepository,
        private readonly ReadHostTemplateRepositoryInterface $readHostTemplateRepository,
        private readonly ReadViewImgRepositoryInterface $readViewImgRepository,
        private readonly ReadTimePeriodRepositoryInterface $readTimePeriodRepository,
        private readonly ReadHostSeverityRepositoryInterface $readHostSeverityRepository,
        private readonly ReadTimezoneRepositoryInterface $readTimezoneRepository,
        private readonly ReadCommandRepositoryInterface $readCommandRepository,
        private readonly ReadHostCategoryRepositoryInterface $readHostCategoryRepository,
        private readonly WriteHostCategoryRepositoryInterface $writeHostCategoryRepository,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly DataStorageEngineInterface $dataStorageEngine,
        private readonly OptionService $optionService,
        private readonly ContactInterface $user
    ) {
    }

    /**
     * @param AddHostTemplateRequest $request
     * @param AddHostTemplatePresenterInterface $presenter
     */
    public function __invoke(AddHostTemplateRequest $request, AddHostTemplatePresenterInterface $presenter): void
    {
        try {
            if (! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_HOSTS_TEMPLATES_READ_WRITE)) {
                $this->error(
                    "User doesn't have sufficient rights to add a host template",
                    ['user_id' => $this->user->getId()]
                );
                $presenter->presentResponse(
                    new ForbiddenResponse(HostTemplateException::addNotAllowed()->getMessage())
                );

                return;
            }

            try {
                $this->dataStorageEngine->startTransaction();

                $hostTemplateId = $this->createHostTemplate($request);
                $this->linkHostCategories($request, $hostTemplateId);

                $this->dataStorageEngine->commitTransaction();
            } catch (\Throwable $ex) {
                $this->error("Rollback of 'Add Host Template' transaction.");
                $this->dataStorageEngine->rollbackTransaction();

                throw $ex;
            }

            $hostTemplate = $this->readHostTemplateRepository->findById($hostTemplateId);
            if (! $hostTemplate) {
                throw HostTemplateException::errorWhileRetrievingObject();
            }
            if ($this->user->isAdmin()) {
                $hostCategories = $this->readHostCategoryRepository->findByHost($hostTemplateId);
            } else {
                $accessGroups = $this->readAccessGroupRepository->findByContact($this->user);
                $hostCategories = $this->readHostCategoryRepository->findByHostAndAccessGroups(
                    $hostTemplateId,
                    $accessGroups
                );
            }

            $presenter->presentResponse(AddHostTemplateFactory::createResponse($hostTemplate, $hostCategories));
        } catch (AssertionFailedException|\ValueError $ex) {
            $presenter->presentResponse(new InvalidArgumentResponse($ex));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (HostTemplateException $ex) {
            $presenter->presentResponse(
                match ($ex->getCode()) {
                    HostTemplateException::CODE_CONFLICT => new ConflictResponse($ex),
                    default => new ErrorResponse($ex),
                }
            );
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (\Throwable $ex) {
            $presenter->presentResponse(
                new ErrorResponse(HostTemplateException::addHostTemplate())
            );
            $this->error((string) $ex);
        }
    }

    /**
     * Assert name is not already used.
     *
     * @param string $name
     *
     * @throws HostTemplateException
     */
    private function assertIsValidName(string $name): void
    {
        $formattedName = HostTemplate::formatName($name);
        if ($this->readHostTemplateRepository->existsByName($formattedName)) {
            $this->error('Host template name already exists', ['name' => $name, 'formattedName' => $formattedName]);

            throw HostTemplateException::nameAlreadyExists($formattedName, $name);
        }
    }

    /**
     * Assert icon ID is valid.
     *
     * @param ?int $iconId
     *
     * @throws HostTemplateException
     */
    private function assertIsValidIcon(?int $iconId): void
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
     * @throws HostTemplateException
     */
    private function assertIsValidTimePeriod(?int $timePeriodId, ?string $propertyName = null): void
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
     * @throws HostTemplateException
     */
    private function assertIsValidSeverity(?int $severityId): void
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
    private function assertIsValidTimezone(?int $timezoneId): void
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
    private function assertIsValidCommand(
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
    private function assertAreValidCategories(array $categoryIds): void
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
     * @param AddHostTemplateRequest $request
     *
     * @throws AssertionFailedException
     * @throws HostTemplateException
     * @throws \Throwable
     *
     * @return int
     */
    private function createHostTemplate(AddHostTemplateRequest $request): int
    {
        $this->assertIsValidName($request->name);
        $this->assertIsValidSeverity($request->severityId);
        $this->assertIsValidTimezone($request->timezoneId);
        $this->assertIsValidTimePeriod($request->checkTimeperiodId, 'checkTimeperiodId');
        $this->assertIsValidTimePeriod($request->notificationTimeperiodId, 'notificationTimeperiodId');
        $this->assertIsValidCommand($request->checkCommandId, CommandType::Check, 'checkCommandId');
        $this->assertIsValidCommand($request->eventHandlerCommandId, null, 'eventHandlerCommandId');
        $this->assertIsValidIcon($request->iconId);

        $inheritanceMode = $this->optionService->findSelectedOptions(['inheritance_mode']);
        $inheritanceMode = isset($inheritanceMode['inheritance_mode'])
            ? (int) $inheritanceMode['inheritance_mode']->getValue()
            : 0;

        $newHostTemplate = NewHostTemplateFactory::create($request, $inheritanceMode);

        $this->info('AddHostTemplate: Adding new host template', ['host_template' => $newHostTemplate]);

        return $this->writeHostTemplateRepository->add($newHostTemplate);
    }

    /**
     * @param AddHostTemplateRequest $dto
     * @param int $hostTemplateId
     *
     * @throws HostTemplateException
     * @throws \Throwable
     */
    private function linkHostCategories(AddHostTemplateRequest $dto, int $hostTemplateId): void
    {
        if ($dto->categories === []) {
            return;
        }

        $this->assertAreValidCategories($dto->categories);

        $this->info(
            'AddHostTemplate: Linking host categories',
            ['host_template_id' => $hostTemplateId, 'category_ids' => $dto->categories]
        );

        $this->writeHostCategoryRepository->linkToHost($hostTemplateId, $dto->categories);
    }
}
