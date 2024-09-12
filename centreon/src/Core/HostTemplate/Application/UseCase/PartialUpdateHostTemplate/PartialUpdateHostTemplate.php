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

namespace Core\HostTemplate\Application\UseCase\PartialUpdateHostTemplate;

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
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Application\Common\UseCase\PresenterInterface;
use Core\Command\Domain\Model\CommandType;
use Core\CommandMacro\Application\Repository\ReadCommandMacroRepositoryInterface;
use Core\CommandMacro\Domain\Model\CommandMacro;
use Core\CommandMacro\Domain\Model\CommandMacroType;
use Core\Common\Application\Converter\YesNoDefaultConverter;
use Core\Common\Application\Type\NoValue;
use Core\Host\Application\Converter\HostEventConverter;
use Core\Host\Application\InheritanceManager;
use Core\Host\Domain\Model\SnmpVersion;
use Core\HostCategory\Application\Repository\ReadHostCategoryRepositoryInterface;
use Core\HostCategory\Application\Repository\WriteHostCategoryRepositoryInterface;
use Core\HostCategory\Domain\Model\HostCategory;
use Core\HostTemplate\Application\Exception\HostTemplateException;
use Core\HostTemplate\Application\Repository\ReadHostTemplateRepositoryInterface;
use Core\HostTemplate\Application\Repository\WriteHostTemplateRepositoryInterface;
use Core\HostTemplate\Domain\Model\HostTemplate;
use Core\Macro\Application\Repository\ReadHostMacroRepositoryInterface;
use Core\Macro\Application\Repository\WriteHostMacroRepositoryInterface;
use Core\Macro\Domain\Model\Macro;
use Core\Macro\Domain\Model\MacroDifference;
use Core\Macro\Domain\Model\MacroManager;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;
use Utility\Difference\BasicDifference;

final class PartialUpdateHostTemplate
{
    use LoggerTrait;

    /** @var AccessGroup[] */
    private array $accessGroups = [];

    public function __construct(
        private readonly ReadHostTemplateRepositoryInterface $readHostTemplateRepository,
        private readonly ReadHostMacroRepositoryInterface $readHostMacroRepository,
        private readonly ReadCommandMacroRepositoryInterface $readCommandMacroRepository,
        private readonly WriteHostMacroRepositoryInterface $writeHostMacroRepository,
        private readonly ReadHostCategoryRepositoryInterface $readHostCategoryRepository,
        private readonly WriteHostCategoryRepositoryInterface $writeHostCategoryRepository,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly WriteHostTemplateRepositoryInterface $writeHostTemplateRepository,
        private readonly PartialUpdateHostTemplateValidation $validation,
        private readonly OptionService $optionService,
        private readonly DataStorageEngineInterface $dataStorageEngine,
        private readonly ContactInterface $user,
    ) {
    }

    /**
     * @param PresenterInterface $presenter
     * @param PartialUpdateHostTemplateRequest $request
     * @param int $hostTemplateId
     */
    public function __invoke(
        PartialUpdateHostTemplateRequest $request,
        PresenterInterface $presenter,
        int $hostTemplateId
    ): void {
        try {
            if (! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_HOSTS_TEMPLATES_READ_WRITE)) {
                $this->error(
                    "User doesn't have sufficient rights to edit host templates",
                    ['user_id' => $this->user->getId()]
                );
                $presenter->setResponseStatus(
                    new ForbiddenResponse(HostTemplateException::writeActionsNotAllowed())
                );

                return;
            }

            if (! $this->user->isAdmin()) {
                $this->accessGroups = $this->readAccessGroupRepository->findByContact($this->user);
                $this->validation->accessGroups = $this->accessGroups;
                $hostTemplate = $this->readHostTemplateRepository->findByIdAndAccessGroups(
                    $hostTemplateId,
                    $this->accessGroups
                );
            } else {
                $hostTemplate = $this->readHostTemplateRepository->findById($hostTemplateId);
            }

            if ($hostTemplate === null) {
                $this->error(
                    'Host template not found',
                    ['host_template_id' => $hostTemplateId]
                );
                $presenter->setResponseStatus(new NotFoundResponse('Host template'));

                return;
            }

            if ($hostTemplate->isLocked()) {
                $this->error(
                    'Host template is locked, partial update refused.',
                    ['host_template_id' => $hostTemplateId]
                );
                $presenter->setResponseStatus(
                    new InvalidArgumentResponse(HostTemplateException::hostIsLocked($hostTemplateId))
                );

                return;
            }

            $this->updatePropertiesInTransaction($request, $hostTemplate);

            $presenter->setResponseStatus(new NoContentResponse());
        } catch (HostTemplateException $ex) {
            $presenter->setResponseStatus(
                match ($ex->getCode()) {
                    HostTemplateException::CODE_CONFLICT => new ConflictResponse($ex),
                    default => new ErrorResponse($ex),
                }
            );
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (\Throwable $ex) {
            $presenter->setResponseStatus(new ErrorResponse(HostTemplateException::partialUpdateHostTemplate()));
            $this->error($ex->getMessage());
        }
    }

    /**
     * @param PartialUpdateHostTemplateRequest $request
     * @param HostTemplate $hostTemplate
     *
     * @throws \Throwable
     */
    private function updatePropertiesInTransaction(
        PartialUpdateHostTemplateRequest $request,
        HostTemplate $hostTemplate
    ): void {
        try {
            $this->dataStorageEngine->startTransaction();

            $this->updateHostTemplate($request, $hostTemplate);
            // Note: parent templates must be updated before macros for macro inheritance resolution
            $this->updateParentTemplates($request, $hostTemplate->getId());
            $this->updateMacros($request, $hostTemplate);
            $this->updateCategories($request, $hostTemplate);

            $this->dataStorageEngine->commitTransaction();
        } catch (\Throwable $ex) {
            $this->error("Rollback of 'PartialUpdateHostTemplate' transaction.");
            $this->dataStorageEngine->rollbackTransaction();

            throw $ex;
        }
    }

    /**
     * @param PartialUpdateHostTemplateRequest $request
     * @param HostTemplate $hostTemplate
     *
     * @throws \Throwable|AssertionFailedException|HostTemplateException
     */
    private function updateHostTemplate(PartialUpdateHostTemplateRequest $request, HostTemplate $hostTemplate): void
    {
        $this->info('PartialUpdateHostTemplate: update host template', ['host_template_id' => $hostTemplate->getId()]);

        $inheritanceMode = $this->optionService->findSelectedOptions(['inheritance_mode']);
        $inheritanceMode = isset($inheritanceMode[0])
            ? (int) $inheritanceMode[0]->getValue()
            : 0;

        if (! $request->name instanceOf NoValue) {
            $this->validation->assertIsValidName($request->name, $hostTemplate);
            $hostTemplate->setName($request->name);
        }

        if (! $request->alias instanceOf NoValue) {
            $hostTemplate->setAlias($request->alias);
        }

        if (! $request->snmpVersion instanceOf NoValue) {
            $hostTemplate->setSnmpVersion(
                $request->snmpVersion === ''
                    ? null
                    : SnmpVersion::from($request->snmpVersion)
            );
        }

        if (! $request->snmpCommunity instanceOf NoValue) {
            $hostTemplate->setSnmpCommunity($request->snmpCommunity);
        }

        if (! $request->timezoneId instanceOf NoValue) {
            $this->validation->assertIsValidTimezone($request->timezoneId);
            $hostTemplate->setTimezoneId($request->timezoneId);
        }

        if (! $request->severityId instanceOf NoValue) {
            $this->validation->assertIsValidSeverity($request->severityId);
            $hostTemplate->setSeverityId($request->severityId);
        }

        if (! $request->checkCommandId instanceOf NoValue) {
            $this->validation->assertIsValidCommand($request->checkCommandId, CommandType::Check, 'checkCommandId');
            $hostTemplate->setCheckCommandId($request->checkCommandId);
        }

        if (! $request->checkCommandArgs instanceOf NoValue) {
            $hostTemplate->setCheckCommandArgs($request->checkCommandArgs);
        }

        if (! $request->checkTimeperiodId instanceOf NoValue) {
            $this->validation->assertIsValidTimePeriod($request->checkTimeperiodId, 'checkTimeperiodId');
            $hostTemplate->setCheckTimeperiodId($request->checkTimeperiodId);
        }

        if (! $request->maxCheckAttempts instanceOf NoValue) {
            $hostTemplate->setMaxCheckAttempts($request->maxCheckAttempts);
        }

        if (! $request->normalCheckInterval instanceOf NoValue) {
            $hostTemplate->setNormalCheckInterval($request->normalCheckInterval);
        }

        if (! $request->retryCheckInterval instanceOf NoValue) {
            $hostTemplate->setRetryCheckInterval($request->retryCheckInterval);
        }

        if (! $request->activeCheckEnabled instanceOf NoValue) {
            $hostTemplate->setActiveCheckEnabled(YesNoDefaultConverter::fromScalar($request->activeCheckEnabled));
        }

        if (! $request->passiveCheckEnabled instanceOf NoValue) {
            $hostTemplate->setPassiveCheckEnabled(YesNoDefaultConverter::fromScalar($request->passiveCheckEnabled));
        }

        if (! $request->notificationEnabled instanceOf NoValue) {
            $hostTemplate->setNotificationEnabled(YesNoDefaultConverter::fromScalar($request->notificationEnabled));
        }

        if (! $request->notificationOptions instanceOf NoValue) {
            $hostTemplate->setNotificationOptions(
                $request->notificationOptions === null
                    ? []
                    : HostEventConverter::fromBitFlag($request->notificationOptions)
            );
        }

        if (! $request->notificationInterval instanceOf NoValue) {
            $hostTemplate->setNotificationInterval($request->notificationInterval);
        }

        if (! $request->notificationTimeperiodId instanceOf NoValue) {
            $this->validation->assertIsValidTimePeriod($request->notificationTimeperiodId, 'notificationTimeperiodId');
            $hostTemplate->setNotificationTimeperiodId($request->notificationTimeperiodId);
        }

        if (! $request->addInheritedContactGroup instanceOf NoValue) {
            $hostTemplate->setAddInheritedContactGroup(
                $inheritanceMode === 1 ? $request->addInheritedContactGroup : false
            );
        }

        if (! $request->addInheritedContact instanceOf NoValue) {
            $hostTemplate->setAddInheritedContact(
                $inheritanceMode === 1 ? $request->addInheritedContact : false
            );
        }

        if (! $request->firstNotificationDelay instanceOf NoValue) {
            $hostTemplate->setFirstNotificationDelay($request->firstNotificationDelay);
        }

        if (! $request->recoveryNotificationDelay instanceOf NoValue) {
            $hostTemplate->setRecoveryNotificationDelay($request->recoveryNotificationDelay);
        }

        if (! $request->acknowledgementTimeout instanceOf NoValue) {
            $hostTemplate->setAcknowledgementTimeout($request->acknowledgementTimeout);
        }

        if (! $request->freshnessChecked instanceOf NoValue) {
            $hostTemplate->setFreshnessChecked(YesNoDefaultConverter::fromScalar($request->freshnessChecked));
        }

        if (! $request->freshnessThreshold instanceOf NoValue) {
            $hostTemplate->setFreshnessThreshold($request->freshnessThreshold);
        }

        if (! $request->flapDetectionEnabled instanceOf NoValue) {
            $hostTemplate->setFlapDetectionEnabled(YesNoDefaultConverter::fromScalar($request->flapDetectionEnabled));
        }

        if (! $request->lowFlapThreshold instanceOf NoValue) {
            $hostTemplate->setLowFlapThreshold($request->lowFlapThreshold);
        }

        if (! $request->highFlapThreshold instanceOf NoValue) {
            $hostTemplate->setHighFlapThreshold($request->highFlapThreshold);
        }

        if (! $request->eventHandlerEnabled instanceOf NoValue) {
            $hostTemplate->setEventHandlerEnabled(YesNoDefaultConverter::fromScalar($request->eventHandlerEnabled));
        }

        if (! $request->eventHandlerCommandId instanceOf NoValue) {
            $this->validation->assertIsValidCommand($request->eventHandlerCommandId, null, 'eventHandlerCommandId');
            $hostTemplate->setEventHandlerCommandId($request->eventHandlerCommandId);
        }

        if (! $request->eventHandlerCommandArgs instanceOf NoValue) {
            $hostTemplate->setEventHandlerCommandArgs($request->eventHandlerCommandArgs);
        }

        if (! $request->noteUrl instanceOf NoValue) {
            $hostTemplate->setNoteUrl($request->noteUrl);
        }

        if (! $request->note instanceOf NoValue) {
            $hostTemplate->setNote($request->note);
        }

        if (! $request->actionUrl instanceOf NoValue) {
            $hostTemplate->setActionUrl($request->actionUrl);
        }

        if (! $request->iconId instanceOf NoValue) {
            $this->validation->assertIsValidIcon($request->iconId);
            $hostTemplate->setIconId($request->iconId);
        }

        if (! $request->iconAlternative instanceOf NoValue) {
            $hostTemplate->setIconAlternative($request->iconAlternative);
        }

        if (! $request->comment instanceOf NoValue) {
            $hostTemplate->setComment($request->comment);
        }

        $this->writeHostTemplateRepository->update($hostTemplate);
    }

    /**
     * @param PartialUpdateHostTemplateRequest $request
     * @param HostTemplate $hostTemplate
     *
     * @throws \Throwable
     */
    private function updateMacros(PartialUpdateHostTemplateRequest $request, HostTemplate $hostTemplate): void
    {
        $this->info(
            'PartialUpdateHostTemplate: update macros',
            ['host_template_id' => $hostTemplate->getId(), 'macros' => $request->macros]
        );

        if ($request->macros instanceOf NoValue) {
            $this->info('Macros not provided, nothing to update');

            return;
        }

        /**
         * @var array<string,Macro> $directMacros
         * @var array<string,Macro> $inheritedMacros
         * @var array<string,CommandMacro> $commandMacros
         */
        [$directMacros, $inheritedMacros, $commandMacros] = $this->findOriginalMacros($hostTemplate);

        $macros = [];
        foreach ($request->macros as $data) {
            $macro = HostMacroFactory::create($data, $hostTemplate->getId(), $directMacros, $inheritedMacros);
            $macros[$macro->getName()] = $macro;
        }

        $macrosDiff = new MacroDifference();
        $macrosDiff->compute($directMacros, $inheritedMacros, $commandMacros, $macros);

        MacroManager::setOrder($macrosDiff, $macros, $directMacros);

        foreach ($macrosDiff->removedMacros as $macro) {
            $this->writeHostMacroRepository->delete($macro);
        }

        foreach ($macrosDiff->updatedMacros as $macro) {
            $this->writeHostMacroRepository->update($macro);
        }

        foreach ($macrosDiff->addedMacros as $macro) {
            if ($macro->getDescription() === '') {
                $commandMacro = $commandMacros[$macro->getName()] ?? null;
                $macro->setDescription(
                    $commandMacro ? $commandMacro->getDescription() : ''
                );
            }
            $this->writeHostMacroRepository->add($macro);
        }
    }

    /**
     * Find macros of a host template:
     *  - macros linked directly,
     *  - macros linked through template inheritance,
     *  - macros linked through command inheritance.
     *
     * @param HostTemplate $hostTemplate
     *
     * @throws \Throwable
     *
     * @return array{
     *      array<string,Macro>,
     *      array<string,Macro>,
     *      array<string,CommandMacro>
     * }
     */
    private function findOriginalMacros(HostTemplate $hostTemplate): array
    {
        $templateParents = $this->readHostTemplateRepository->findParents($hostTemplate->getId());
        $inheritanceLine = InheritanceManager::findInheritanceLine($hostTemplate->getId(), $templateParents);
        $existingHostMacros
            = $this->readHostMacroRepository->findByHostIds(array_merge([$hostTemplate->getId()], $inheritanceLine));

        [$directMacros, $inheritedMacros] = Macro::resolveInheritance(
            $existingHostMacros,
            $inheritanceLine,
            $hostTemplate->getId()
        );

        /** @var array<string,CommandMacro> */
        $commandMacros = [];
        if ($hostTemplate->getCheckCommandId() !== null) {
            $existingCommandMacros = $this->readCommandMacroRepository->findByCommandIdAndType(
                $hostTemplate->getCheckCommandId(),
                CommandMacroType::Host
            );

            $commandMacros = MacroManager::resolveInheritanceForCommandMacro($existingCommandMacros);
        }

        return [$directMacros, $inheritedMacros, $commandMacros];
    }

    private function updateCategories(PartialUpdateHostTemplateRequest $request, HostTemplate $hostTemplate): void
    {
        $this->info(
            'PartialUpdateHostTemplate: update categories',
            ['host_template_id' => $hostTemplate->getId(), 'categories' => $request->categories]
        );

        if ($request->categories instanceOf NoValue) {
            $this->info('Categories not provided, nothing to update');

            return;
        }

        $categoryIds = array_unique($request->categories);

        $this->validation->assertAreValidCategories($categoryIds);

        if ($this->user->isAdmin()) {
            $originalCategories = $this->readHostCategoryRepository->findByHost($hostTemplate->getId());
        } else {
            $originalCategories = $this->readHostCategoryRepository->findByHostAndAccessGroups(
                $hostTemplate->getId(),
                $this->accessGroups
            );
        }

        $originalCategoryIds = array_map(
            static fn(HostCategory $category): int => $category->getId(),
            $originalCategories
        );

        $categoryDiff = new BasicDifference($originalCategoryIds, $categoryIds);
        $addedCategories = $categoryDiff->getAdded();
        $removedCategories = $categoryDiff->getRemoved();

        $this->writeHostCategoryRepository->linkToHost($hostTemplate->getId(), $addedCategories);
        $this->writeHostCategoryRepository->unlinkFromHost($hostTemplate->getId(), $removedCategories);
    }

    /**
     * @param PartialUpdateHostTemplateRequest $dto
     * @param int $hostTemplateId
     *
     * @throws HostTemplateException
     * @throws \Throwable
     */
    private function updateParentTemplates(PartialUpdateHostTemplateRequest $dto, int $hostTemplateId): void
    {
        $this->info(
            'PartialUpdateHostTemplate: Update parent templates',
            ['host_template_id' => $hostTemplateId, 'template_ids' => $dto->templates]
        );

        if ($dto->templates instanceOf NoValue) {
            $this->info('Parent templates not provided, nothing to update');

            return;
        }

        /** @var int[] $parentTemplateIds */
        $parentTemplateIds = array_unique($dto->templates);

        $this->validation->assertAreValidTemplates($parentTemplateIds, $hostTemplateId);

        $this->info('Remove parent templates from a host template', ['child_id' => $hostTemplateId]);
        $this->writeHostTemplateRepository->deleteParents($hostTemplateId);

        foreach ($parentTemplateIds as $order => $templateId) {
            $this->info('Add a parent template to a host template', [
                'child_id' => $hostTemplateId, 'parent_id' => $templateId, 'order' => $order,
            ]);
            $this->writeHostTemplateRepository->addParent($hostTemplateId, $templateId, $order);
        }
    }
}
