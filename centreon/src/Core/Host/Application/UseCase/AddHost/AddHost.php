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
use Core\Host\Application\Exception\HostException;
use Core\Host\Application\InheritanceManager;
use Core\Host\Application\Repository\ReadHostRepositoryInterface;
use Core\Host\Application\Repository\WriteHostRepositoryInterface;
use Core\Host\Application\Repository\WriteRealTimeHostRepositoryInterface;
use Core\HostCategory\Application\Repository\ReadHostCategoryRepositoryInterface;
use Core\HostCategory\Application\Repository\WriteHostCategoryRepositoryInterface;
use Core\HostGroup\Application\Repository\ReadHostGroupRepositoryInterface;
use Core\HostGroup\Application\Repository\WriteHostGroupRepositoryInterface;
use Core\HostTemplate\Application\Repository\ReadHostTemplateRepositoryInterface;
use Core\Macro\Application\Repository\ReadHostMacroRepositoryInterface;
use Core\Macro\Application\Repository\WriteHostMacroRepositoryInterface;
use Core\Macro\Domain\Model\Macro;
use Core\Macro\Domain\Model\MacroDifference;
use Core\Macro\Domain\Model\MacroManager;
use Core\MonitoringServer\Application\Repository\WriteMonitoringServerRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;

final class AddHost
{
    use LoggerTrait;

    public function __construct(
        private readonly WriteHostRepositoryInterface $writeHostRepository,
        private readonly ReadHostRepositoryInterface $readHostRepository,
        private readonly WriteMonitoringServerRepositoryInterface $writeMonitoringServerRepository,
        private readonly ReadHostTemplateRepositoryInterface $readHostTemplateRepository,
        private readonly ReadHostCategoryRepositoryInterface $readHostCategoryRepository,
        private readonly ReadHostGroupRepositoryInterface $readHostGroupRepository,
        private readonly WriteHostCategoryRepositoryInterface $writeHostCategoryRepository,
        private readonly WriteHostGroupRepositoryInterface $writeHostGroupRepository,
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly ReadHostMacroRepositoryInterface $readHostMacroRepository,
        private readonly ReadCommandMacroRepositoryInterface $readCommandMacroRepository,
        private readonly WriteHostMacroRepositoryInterface $writeHostMacroRepository,
        private readonly DataStorageEngineInterface $dataStorageEngine,
        private readonly OptionService $optionService,
        private readonly ContactInterface $user,
        private readonly AddHostValidation $validation,
        private readonly WriteRealTimeHostRepositoryInterface $writeRealTimeHostRepository,
    ) {
    }

    /**
     * @param AddHostRequest $request
     * @param AddHostPresenterInterface $presenter
     */
    public function __invoke(AddHostRequest $request, AddHostPresenterInterface $presenter): void
    {
        try {
            if (! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_HOSTS_WRITE)) {
                $this->error(
                    "User doesn't have sufficient rights to add a host",
                    ['user_id' => $this->user->getId()]
                );
                $presenter->presentResponse(
                    new ForbiddenResponse(HostException::addNotAllowed()->getMessage())
                );

                return;
            }

            $accessGroups = [];

            if (! $this->user->isAdmin()) {
                $accessGroups = $this->readAccessGroupRepository->findByContact($this->user);
            }

            try {
                $this->dataStorageEngine->startTransaction();

                $hostId = $this->createHost($request);
                $this->linkHostCategories($request, $hostId);
                $this->linkHostGroups($request, $hostId);
                $this->linkParentTemplates($request, $hostId);
                $this->addMacros($request, $hostId);
                if ($accessGroups !== []) {
                    $this->writeRealTimeHostRepository->addHostToResourceAcls($hostId, $accessGroups);
                }
                $this->writeMonitoringServerRepository->notifyConfigurationChange($request->monitoringServerId);

                $this->dataStorageEngine->commitTransaction();
            } catch (\Throwable $ex) {
                $this->error("Rollback of 'Add Host' transaction.");
                $this->dataStorageEngine->rollbackTransaction();

                throw $ex;
            }

            $presenter->presentResponse(
                $this->createResponse($hostId, $request->templates)
            );
        } catch (AssertionFailedException|\ValueError $ex) {
            $presenter->presentResponse(new InvalidArgumentResponse($ex));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (HostException $ex) {
            $presenter->presentResponse(
                match ($ex->getCode()) {
                    HostException::CODE_CONFLICT => new ConflictResponse($ex),
                    default => new ErrorResponse($ex),
                }
            );
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (\Throwable $ex) {
            $presenter->presentResponse(
                new ErrorResponse(HostException::addHost())
            );
            $this->error((string) $ex);
        }
    }

    /**
     * @param AddHostRequest $request
     *
     * @throws AssertionFailedException
     * @throws HostException
     * @throws \Throwable
     *
     * @return int
     */
    private function createHost(AddHostRequest $request): int
    {
        $this->validation->assertIsValidName($request->name);
        $this->validation->assertIsValidMonitoringServer($request->monitoringServerId);
        $this->validation->assertIsValidSeverity($request->severityId);
        $this->validation->assertIsValidTimezone($request->timezoneId);
        $this->validation->assertIsValidTimePeriod($request->checkTimeperiodId, 'checkTimeperiodId');
        $this->validation->assertIsValidTimePeriod($request->notificationTimeperiodId, 'notificationTimeperiodId');
        $this->validation->assertIsValidCommand($request->checkCommandId, CommandType::Check, 'checkCommandId');
        $this->validation->assertIsValidCommand($request->eventHandlerCommandId, null, 'eventHandlerCommandId');
        $this->validation->assertIsValidIcon($request->iconId);

        $inheritanceMode = $this->optionService->findSelectedOptions(['inheritance_mode']);
        $inheritanceMode = isset($inheritanceMode[0])
            ? (int) $inheritanceMode[0]->getValue()
            : 0;

        $newHost = NewHostFactory::create($request, $inheritanceMode);
        $hostId = $this->writeHostRepository->add($newHost);

        $this->info('AddHost: Adding new host', ['host_id' => $hostId]);

        return $hostId;
    }

    /**
     * @param AddHostRequest $dto
     * @param int $hostId
     *
     * @throws HostException
     * @throws \Throwable
     */
    private function linkHostCategories(AddHostRequest $dto, int $hostId): void
    {
        $categoryIds = array_unique($dto->categories);
        if ($categoryIds === []) {

            return;
        }

        $this->validation->assertAreValidCategories($categoryIds);

        $this->info(
            'AddHost: Linking host categories',
            ['host_id' => $hostId, 'category_ids' => $categoryIds]
        );

        $this->writeHostCategoryRepository->linkToHost($hostId, $categoryIds);
    }

    /**
     * @param AddHostRequest $dto
     * @param int $hostId
     *
     * @throws HostException
     * @throws \Throwable
     */
    private function linkHostGroups(AddHostRequest $dto, int $hostId): void
    {
        $groupIds = array_unique($dto->groups);
        if ($groupIds === []) {

            return;
        }

        $this->validation->assertAreValidGroups($groupIds);

        $this->info(
            'AddHost: Linking host groups',
            ['host_id' => $hostId, 'group_ids' => $groupIds]
        );

        $this->writeHostGroupRepository->linkToHost($hostId, $groupIds);
    }

    /**
     * @param AddHostRequest $dto
     * @param int $hostId
     *
     * @throws HostException
     * @throws \Throwable
     */
    private function linkParentTemplates(AddHostRequest $dto, int $hostId): void
    {
        $parentTemplateIds = array_unique($dto->templates);

        if ($parentTemplateIds === []) {
            return;
        }

        $this->validation->assertAreValidTemplates($parentTemplateIds, $hostId);

        $this->info(
            'AddHost: Linking parent templates',
            ['host_id' => $hostId, 'template_ids' => $parentTemplateIds]
        );

        foreach ($parentTemplateIds as $order => $templateId) {
            $this->writeHostRepository->addParent($hostId, $templateId, $order);
        }
    }

    /**
     * @param int[] $templateIds
     *
     * @throws HostException
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
     * @param AddHostRequest $dto
     * @param int $hostId
     *
     * @throws \Throwable
     */
    private function addMacros(AddHostRequest $dto, int $hostId): void
    {
        $this->info(
            'AddHost: Add macros',
            ['host_template_id' => $hostId, 'macros' => $dto->macros]
        );

        /**
         * @var array<string,Macro> $inheritedMacros
         * @var array<string,CommandMacro> $commandMacros
         */
        [$inheritedMacros, $commandMacros]
            = $this->findAllInheritedMacros($hostId, $dto->checkCommandId);

        $macros = [];
        foreach ($dto->macros as $data) {
            $macro = HostMacroFactory::create($data, $hostId, $inheritedMacros);
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
            $this->writeHostMacroRepository->add($macro);
        }

    }

    /**
     * Find macros of a host:
     * macros linked through template inheritance, macros linked through command inheritance.
     *
     * @param int $hostId
     * @param ?int $checkCommandId
     *
     * @throws \Throwable
     *
     * @return array{
     *      array<string,Macro>,
     *      array<string,CommandMacro>
     * }
     */
    private function findAllInheritedMacros(int $hostId, ?int $checkCommandId): array
    {
        $templateParents = $this->readHostRepository->findParents($hostId);
        $inheritanceLine = InheritanceManager::findInheritanceLine($hostId, $templateParents);
        $existingHostMacros = $this->readHostMacroRepository->findByHostIds($inheritanceLine);

        [, $inheritedMacros] = Macro::resolveInheritance(
            $existingHostMacros,
            $inheritanceLine,
            $hostId
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

        return [$inheritedMacros, $commandMacros];
    }

    /**
     * @param int $hostId
     * @param int[] $parentTemplateIds
     *
     * @throws AssertionFailedException
     * @throws HostException
     * @throws \Throwable
     *
     * @return AddHostResponse
     */
    private function createResponse(int $hostId, array $parentTemplateIds): AddHostResponse
    {
        $host = $this->readHostRepository->findById($hostId);
        if (! $host) {
            throw HostException::errorWhileRetrievingObject();
        }
        if ($this->user->isAdmin()) {
            $hostCategories = $this->readHostCategoryRepository->findByHost($hostId);
            $hostGroups = $this->readHostGroupRepository->findByHost($hostId);
        } else {
            $accessGroups = $this->readAccessGroupRepository->findByContact($this->user);
            $hostCategories = $this->readHostCategoryRepository->findByHostAndAccessGroups(
                $hostId,
                $accessGroups
            );
            $hostGroups = $this->readHostGroupRepository->findByHostAndAccessGroups($hostId, $accessGroups);
        }
        $parentTemplates = $this->findParentTemplates($parentTemplateIds);
        $macros = $this->readHostMacroRepository->findByHostId($hostId);

        return AddHostFactory::createResponse($host, $hostCategories, $parentTemplates, $macros, $hostGroups);
    }
}
