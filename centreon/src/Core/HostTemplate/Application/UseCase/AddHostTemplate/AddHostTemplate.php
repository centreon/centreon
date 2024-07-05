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
use Core\Command\Domain\Model\CommandType;
use Core\CommandMacro\Application\Repository\ReadCommandMacroRepositoryInterface;
use Core\CommandMacro\Domain\Model\CommandMacro;
use Core\CommandMacro\Domain\Model\CommandMacroType;
use Core\Common\Application\Repository\ReadVaultRepositoryInterface;
use Core\Common\Application\Repository\WriteVaultRepositoryInterface;
use Core\Common\Application\UseCase\VaultTrait;
use Core\Common\Infrastructure\Repository\AbstractVaultRepository;
use Core\Host\Application\InheritanceManager;
use Core\HostCategory\Application\Repository\ReadHostCategoryRepositoryInterface;
use Core\HostCategory\Application\Repository\WriteHostCategoryRepositoryInterface;
use Core\HostTemplate\Application\Exception\HostTemplateException;
use Core\HostTemplate\Application\Repository\ReadHostTemplateRepositoryInterface;
use Core\HostTemplate\Application\Repository\WriteHostTemplateRepositoryInterface;
use Core\Macro\Application\Repository\ReadHostMacroRepositoryInterface;
use Core\Macro\Application\Repository\WriteHostMacroRepositoryInterface;
use Core\Macro\Domain\Model\Macro;
use Core\Macro\Domain\Model\MacroDifference;
use Core\Macro\Domain\Model\MacroManager;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\Vault\Domain\Model\VaultConfiguration;

final class AddHostTemplate
{
    use LoggerTrait,VaultTrait;

    public function __construct(
        private readonly WriteHostTemplateRepositoryInterface $writeHostTemplateRepository,
        private readonly ReadHostTemplateRepositoryInterface $readHostTemplateRepository,
        private readonly ReadHostCategoryRepositoryInterface $readHostCategoryRepository,
        private readonly WriteHostCategoryRepositoryInterface $writeHostCategoryRepository,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly ReadHostMacroRepositoryInterface $readHostMacroRepository,
        private readonly ReadCommandMacroRepositoryInterface $readCommandMacroRepository,
        private readonly WriteHostMacroRepositoryInterface $writeHostMacroRepository,
        private readonly DataStorageEngineInterface $dataStorageEngine,
        private readonly OptionService $optionService,
        private readonly ContactInterface $user,
        private readonly AddHostTemplateValidation $validation,
        private readonly WriteVaultRepositoryInterface $writeVaultRepository,
        private readonly ReadVaultRepositoryInterface $readVaultRepository,
    ) {
        $this->writeVaultRepository->setCustomPath(AbstractVaultRepository::HOST_VAULT_PATH);
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
                $this->linkParentTemplates($request, $hostTemplateId);
                $this->addMacros($request, $hostTemplateId);

                $this->dataStorageEngine->commitTransaction();
            } catch (\Throwable $ex) {
                $this->error("Rollback of 'Add Host Template' transaction.");
                $this->dataStorageEngine->rollbackTransaction();

                throw $ex;
            }

            $presenter->presentResponse(
                $this->createResponse($hostTemplateId, $request->templates)
            );
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
        $this->validation->assertIsValidName($request->name);
        $this->validation->assertIsValidSeverity($request->severityId);
        $this->validation->assertIsValidTimezone($request->timezoneId);
        $this->validation->assertIsValidTimePeriod($request->checkTimeperiodId, 'checkTimeperiodId');
        $this->validation->assertIsValidTimePeriod($request->notificationTimeperiodId, 'notificationTimeperiodId');
        $this->validation->assertIsValidCommand($request->checkCommandId, CommandType::Check, 'checkCommandId');
        $this->validation->assertIsValidCommand($request->eventHandlerCommandId, null, 'eventHandlerCommandId');
        $this->validation->assertIsValidIcon($request->iconId);

        $inheritanceMode = $this->optionService->findSelectedOptions(['inheritance_mode']);
        $inheritanceMode = isset($inheritanceMode[0])
            ? (int) ($inheritanceMode[0])->getValue()
            : 0;

        if ($this->writeVaultRepository->isVaultConfigured() === true && $request->snmpCommunity !== '') {
            $vaultPaths = $this->writeVaultRepository->upsert(
                null,
                [VaultConfiguration::HOST_SNMP_COMMUNITY_KEY => $request->snmpCommunity]
            );
            $vaultPath = $vaultPaths[VaultConfiguration::HOST_SNMP_COMMUNITY_KEY];
            $this->uuid ??= $this->getUuidFromPath($vaultPath);
            $request->snmpCommunity = $vaultPath;
        }

        $newHostTemplate = NewHostTemplateFactory::create($request, $inheritanceMode);
        $hostTemplateId = $this->writeHostTemplateRepository->add($newHostTemplate);

        $this->info('AddHostTemplate: Adding new host template', ['host_template_id' => $hostTemplateId]);

        return $hostTemplateId;
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
        $categoryIds = array_unique($dto->categories);
        if ($categoryIds === []) {

            return;
        }

        $this->validation->assertAreValidCategories($categoryIds);

        $this->info(
            'AddHostTemplate: Linking host categories',
            ['host_template_id' => $hostTemplateId, 'category_ids' => $categoryIds]
        );

        $this->writeHostCategoryRepository->linkToHost($hostTemplateId, $categoryIds);
    }

    /**
     * @param AddHostTemplateRequest $dto
     * @param int $hostTemplateId
     *
     * @throws HostTemplateException
     * @throws \Throwable
     */
    private function linkParentTemplates(AddHostTemplateRequest $dto, int $hostTemplateId): void
    {
        $parentTemplateIds = array_unique($dto->templates);

        if ($parentTemplateIds === []) {
            return;
        }

        $this->validation->assertAreValidTemplates($parentTemplateIds, $hostTemplateId);

        $this->info(
            'AddHostTemplate: Linking parent templates',
            ['host_template_id' => $hostTemplateId, 'template_ids' => $parentTemplateIds]
        );

        foreach ($parentTemplateIds as $order => $templateId) {
            $this->writeHostTemplateRepository->addParent($hostTemplateId, $templateId, $order);
        }
    }

    /**
     * @param int[] $templateIds
     *
     * @throws HostTemplateException
     * @throws \Throwable
     *
     * @return array<array{id:int,name:string}>
     */
    private function findParentTemplates(array $templateIds): array
    {
        $templateNames = $this->readHostTemplateRepository->findNamesByIds($templateIds);

        $parentTemplates = [];
        foreach ($templateIds as $templateId) {
            $parentTemplates[] = [
                'id' => $templateId,
                'name' => $templateNames[$templateId],
            ];
        }

        return $parentTemplates;
    }

    /**
     * @param AddHostTemplateRequest $dto
     * @param int $hostTemplateId
     *
     * @throws \Throwable
     */
    private function addMacros(AddHostTemplateRequest $dto, int $hostTemplateId): void
    {
        $this->info(
            'AddHostTemplate: Add macros',
            ['host_template_id' => $hostTemplateId, 'macros' => $dto->macros]
        );

        /**
         * @var array<string,Macro> $inheritedMacros
         * @var array<string,CommandMacro> $commandMacros
         */
        [$inheritedMacros, $commandMacros]
            = $this->findAllInheritedMacros($hostTemplateId, $dto->checkCommandId);

        $macros = [];
        foreach ($dto->macros as $data) {
            $macro = HostMacroFactory::create($data, $hostTemplateId, $inheritedMacros);
            $macros[$macro->getName()] = $macro;
        }

        $macrosDiff = new MacroDifference();
        $macrosDiff->compute([], $inheritedMacros, $commandMacros, $macros);

        MacroManager::setOrder($macrosDiff, $macros, []);

        foreach ($macrosDiff->addedMacros as $macro) {
            if ($macro->getDescription() === '') {
                $macro->setDescription(
                    isset($commandMacros[$macro->getName()])
                    ? $commandMacros[$macro->getName()]->getDescription()
                    : ''
                );
            }

            if ($this->writeVaultRepository->isVaultConfigured() === true && $macro->isPassword() === true) {
                $vaultPaths = $this->writeVaultRepository->upsert(
                    $this->uuid ?? null,
                    ['_HOST' . $macro->getName() => $macro->getValue()],
                );
                $vaultPath = $vaultPaths['_HOST' . $macro->getName()];
                $inVaultMacro = new Macro($macro->getOwnerId(), $macro->getName(), $vaultPath);
                $inVaultMacro->setDescription($macro->getDescription());
                $inVaultMacro->setIsPassword($macro->isPassword());
                $inVaultMacro->setOrder($macro->getOrder());
                $macro = $inVaultMacro;
            }

            $this->writeHostMacroRepository->add($macro);
        }

    }

    /**
     * Find macros of a host template:
     * macros linked through template inheritance, macros linked through command inheritance.
     *
     * @param int $hostTemplateId
     * @param ?int $checkCommandId
     *
     * @throws \Throwable
     *
     * @return array{
     *      array<string,Macro>,
     *      array<string,CommandMacro>
     * }
     */
    private function findAllInheritedMacros(int $hostTemplateId, ?int $checkCommandId): array
    {
        $templateParents = $this->readHostTemplateRepository->findParents($hostTemplateId);
        $inheritanceLine = InheritanceManager::findInheritanceLine($hostTemplateId, $templateParents);
        $existingHostMacros = $this->readHostMacroRepository->findByHostIds($inheritanceLine);

        [, $inheritedMacros] = Macro::resolveInheritance(
            $existingHostMacros,
            $inheritanceLine,
            $hostTemplateId
        );

        /** @var array<string,CommandMacro> $commandMacros */
        $commandMacros = [];
        if ($checkCommandId !== null) {
            $existingCommandMacros = $this->readCommandMacroRepository->findByCommandIdAndType(
                $checkCommandId,
                CommandMacroType::Host
            );

            $commandMacros = MacroManager::resolveInheritanceForCommandMacro($existingCommandMacros);
        }

        return [
            $this->writeVaultRepository->isVaultConfigured()
                ? $this->retrieveMacrosVaultValues($inheritedMacros)
                : $inheritedMacros,
            $commandMacros,
        ];
    }

    /**
     * @param int $hostTemplateId
     * @param int[] $parentTemplateIds
     *
     * @throws AssertionFailedException
     * @throws HostTemplateException
     * @throws \Throwable
     *
     * @return AddHostTemplateResponse
     */
    private function createResponse(int $hostTemplateId, array $parentTemplateIds): AddHostTemplateResponse
    {
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
        $parentTemplates = $this->findParentTemplates($parentTemplateIds);
        $macros = $this->readHostMacroRepository->findByHostId($hostTemplateId);

        return AddHostTemplateFactory::createResponse($hostTemplate, $hostCategories, $parentTemplates, $macros);
    }

    /**
     * @param array<string,Macro> $macros
     *
     * @throws \Throwable
     *
     * @return array<string,Macro>
     */
    private function retrieveMacrosVaultValues(array $macros): array
    {
        $updatedMacros = [];
        foreach ($macros as $key => $macro) {
            if (false === $macro->isPassword()) {
                $updatedMacros[$key] = $macro;
                continue;
            }

            $vaultData = $this->readVaultRepository->findFromPath($macro->getValue());
            $vaultKey = '_HOST' . $macro->getName();
            if (isset($vaultData[$vaultKey])) {
                $inVaultMacro = new Macro($macro->getOwnerId(),$macro->getName(), $vaultData[$vaultKey]);
                $inVaultMacro->setDescription($macro->getDescription());
                $inVaultMacro->setIsPassword($macro->isPassword());
                $inVaultMacro->setOrder($macro->getOrder());

                $updatedMacros[$key] = $inVaultMacro;
            }
        }

        return $updatedMacros;
    }
}
