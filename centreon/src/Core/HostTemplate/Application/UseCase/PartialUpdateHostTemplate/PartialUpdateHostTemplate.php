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

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\Application\Common\UseCase\ConflictResponse;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use Core\Application\Common\UseCase\PresenterInterface;
use Core\CommandMacro\Application\Repository\ReadCommandMacroRepositoryInterface;
use Core\CommandMacro\Domain\Model\CommandMacro;
use Core\CommandMacro\Domain\Model\CommandMacroType;
use Core\Common\Application\Type\NoValue;
use Core\Host\Application\InheritanceManager;
use Core\HostCategory\Application\Repository\ReadHostCategoryRepositoryInterface;
use Core\HostCategory\Application\Repository\WriteHostCategoryRepositoryInterface;
use Core\HostCategory\Domain\Model\HostCategory;
use Core\HostMacro\Application\Repository\ReadHostMacroRepositoryInterface;
use Core\HostMacro\Application\Repository\WriteHostMacroRepositoryInterface;
use Core\HostMacro\Domain\Model\HostMacro;
use Core\HostMacro\Domain\Model\HostMacroDifference;
use Core\HostTemplate\Application\Exception\HostTemplateException;
use Core\HostTemplate\Application\Repository\ReadHostTemplateRepositoryInterface;
use Core\HostTemplate\Application\Repository\WriteHostTemplateRepositoryInterface;
use Core\HostTemplate\Domain\Model\HostTemplate;
use Core\HostTemplate\Domain\Model\MacroManager;
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
        private readonly InheritanceManager $inheritanceManager,
        private readonly DataStorageEngineInterface $dataStorageEngine,
        private readonly ContactInterface $user,
    ) {
    }

    /**
     * @param PresenterInterface $presenter
     * @param PartialUpdateHostTemplateRequest $request
     * @param int $hostTemplateId
     */
    public function __invoke(PartialUpdateHostTemplateRequest $request, PresenterInterface $presenter, int $hostTemplateId): void
    {
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

            if (! ($hostTemplate = $this->readHostTemplateRepository->findById($hostTemplateId))) {
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

            if (! $this->user->isAdmin()) {
                $this->accessGroups = $this->readAccessGroupRepository->findByContact($this->user);
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
    private function updatePropertiesInTransaction(PartialUpdateHostTemplateRequest $request, HostTemplate $hostTemplate): void
    {
        try {
            $this->dataStorageEngine->startTransaction();

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
         * @var array<string,HostMacro> $directMacros
         * @var array<string,HostMacro> $inheritedMacros
         * @var array<string,CommandMacro> $commandMacros
         */
        [$directMacros, $inheritedMacros, $commandMacros] = $this->findOriginalMacros($hostTemplate);

        $macros = [];
        foreach ($request->macros as $data) {
            $macro = HostMacroFactory::create($data, $hostTemplate->getId(), $directMacros, $inheritedMacros);
            $macros[$macro->getName()] = $macro;
        }

        $macrosDiff = new HostMacroDifference();
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
     *      array<string,HostMacro>,
     *      array<string,HostMacro>,
     *      array<string,CommandMacro>
     * }
     */
    private function findOriginalMacros(HostTemplate $hostTemplate): array
    {
        $templateParents = $this->readHostTemplateRepository->findParents($hostTemplate->getId());
        $inheritanceLine = InheritanceManager::findInheritanceLine($hostTemplate->getId(), $templateParents);
        $existingHostMacros
            = $this->readHostMacroRepository->findByHostIds(array_merge([$hostTemplate->getId()], $inheritanceLine));

        [$directMacros, $inheritedMacros] = MacroManager::resolveInheritanceForHostMacro(
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

        $this->assertAreValidCategories($categoryIds);

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
            $validCategoryIds
                = $this->readHostCategoryRepository->existByAccessGroups($categoryIds, $this->accessGroups);
        }

        if ([] !== ($invalidIds = array_diff($categoryIds, $validCategoryIds))) {
            throw HostTemplateException::idsDoNotExist('categories', $invalidIds);
        }
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

        $this->assertAreValidTemplates($parentTemplateIds, $hostTemplateId);

        $this->info('Remove parent templates from a host template', ['child_id' => $hostTemplateId]);
        $this->writeHostTemplateRepository->deleteParents($hostTemplateId);

        foreach ($parentTemplateIds as $order => $templateId) {
            $this->info('Add a parent template to a host template', [
                'child_id' => $hostTemplateId, 'parent_id' => $templateId, 'order' => $order,
            ]);
            $this->writeHostTemplateRepository->addParent($hostTemplateId, $templateId, $order);
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
    private function assertAreValidTemplates(array $templateIds, int $hostTemplateId): void
    {
        if ($templateIds === []) {

            return;
        }

        $validTemplateIds = $this->readHostTemplateRepository->exist($templateIds);

        if ([] !== ($invalidIds = array_diff($templateIds, $validTemplateIds))) {
            throw HostTemplateException::idsDoNotExist('templates', $invalidIds);
        }

        if (
            in_array($hostTemplateId, $templateIds, true)
            || false === $this->inheritanceManager->isValidInheritanceTree($hostTemplateId, $templateIds)
        ) {
            throw HostTemplateException::circularTemplateInheritance();
        }
    }
}
