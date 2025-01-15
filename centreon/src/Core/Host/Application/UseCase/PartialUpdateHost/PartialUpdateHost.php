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
use Core\Common\Application\Repository\ReadVaultRepositoryInterface;
use Core\Common\Application\Repository\WriteVaultRepositoryInterface;
use Core\Common\Application\Type\NoValue;
use Core\Common\Application\UseCase\VaultTrait;
use Core\Common\Infrastructure\Repository\AbstractVaultRepository;
use Core\Domain\Common\GeoCoords;
use Core\Host\Application\Converter\HostEventConverter;
use Core\Host\Application\Exception\HostException;
use Core\Host\Application\InheritanceManager;
use Core\Host\Application\Repository\ReadHostRepositoryInterface;
use Core\Host\Application\Repository\WriteHostRepositoryInterface;
use Core\Host\Domain\Model\Host;
use Core\Host\Domain\Model\SnmpVersion;
use Core\HostCategory\Application\Repository\ReadHostCategoryRepositoryInterface;
use Core\HostCategory\Application\Repository\WriteHostCategoryRepositoryInterface;
use Core\HostCategory\Domain\Model\HostCategory;
use Core\HostGroup\Application\Repository\ReadHostGroupRepositoryInterface;
use Core\HostGroup\Application\Repository\WriteHostGroupRepositoryInterface;
use Core\HostGroup\Domain\Model\HostGroup;
use Core\Macro\Application\Repository\ReadHostMacroRepositoryInterface;
use Core\Macro\Application\Repository\WriteHostMacroRepositoryInterface;
use Core\Macro\Domain\Model\Macro;
use Core\Macro\Domain\Model\MacroDifference;
use Core\Macro\Domain\Model\MacroManager;
use Core\MonitoringServer\Application\Repository\WriteMonitoringServerRepositoryInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;
use Core\Security\Vault\Domain\Model\VaultConfiguration;
use Utility\Difference\BasicDifference;

final class PartialUpdateHost
{
    use LoggerTrait,VaultTrait;
    private const VERTICAL_INHERITANCE_MODE = 1;

    /** @var AccessGroup[] */
    private array $accessGroups = [];

    public function __construct(
        private readonly WriteHostRepositoryInterface $writeHostRepository,
        private readonly ReadHostRepositoryInterface $readHostRepository,
        private readonly WriteMonitoringServerRepositoryInterface $writeMonitoringServerRepository,
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
        private readonly PartialUpdateHostValidation $validation,
        private readonly WriteVaultRepositoryInterface $writeVaultRepository,
        private readonly ReadVaultRepositoryInterface $readVaultRepository,
    ) {
        $this->writeVaultRepository->setCustomPath(AbstractVaultRepository::HOST_VAULT_PATH);
    }

    /**
     * @param PartialUpdateHostRequest $request
     * @param PresenterInterface $presenter
     * @param int $hostId
     */
    public function __invoke(
        PartialUpdateHostRequest $request,
        PresenterInterface $presenter,
        int $hostId
    ): void
    {
        try {
            if (! $this->user->hasTopologyRole(Contact::ROLE_CONFIGURATION_HOSTS_WRITE)) {
                $this->error(
                    "User doesn't have sufficient rights to edit a host",
                    ['user_id' => $this->user->getId()]
                );
                $presenter->setResponseStatus(
                    new ForbiddenResponse(HostException::editNotAllowed())
                );

                return;
            }

            if (! $this->user->isAdmin()) {
                $this->accessGroups = $this->readAccessGroupRepository->findByContact($this->user);
                $this->validation->accessGroups = $this->accessGroups;
            }

            if (
                (
                    ! $this->user->isAdmin()
                    && ! $this->readHostRepository->existsByAccessGroups($hostId, $this->accessGroups)
                )
                || ! ($host = $this->readHostRepository->findById($hostId))
            ) {
                $this->error(
                    'Host not found',
                    ['host_id' => $hostId]
                );
                $presenter->setResponseStatus(new NotFoundResponse('Host'));

                return;
            }

            $this->updatePropertiesInTransaction($request, $host);

            $presenter->setResponseStatus(new NoContentResponse());
        } catch (HostException $ex) {
            $presenter->setResponseStatus(
                match ($ex->getCode()) {
                    HostException::CODE_CONFLICT => new ConflictResponse($ex),
                    default => new ErrorResponse($ex),
                }
            );
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (AssertionFailedException $ex) {
            $presenter->setResponseStatus(new InvalidArgumentResponse($ex));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (\Throwable $ex) {
            $presenter->setResponseStatus(new ErrorResponse(HostException::editHost()));
            $this->error((string) $ex);
        }
    }

    private function updatePropertiesInTransaction(PartialUpdateHostRequest $request, Host $host): void
    {
        try {
            $this->dataStorageEngine->startTransaction();

            if ($this->writeVaultRepository->isVaultConfigured()) {
                $this->retrieveHostUuidFromVault($host);
            }

            $previousMonitoringServer = $host->getMonitoringServerId();
            $this->updateHost($request, $host);
            $this->updateHostCategories($request, $host);
            $this->updateHostGroups($request, $host);
            $this->updateParentTemplates($request, $host);
            // Note: parent templates must be updated before macros for macro inheritance resolution
            $this->updateMacros($request, $host);

            $this->writeMonitoringServerRepository->notifyConfigurationChange($host->getMonitoringServerId());
            if ($previousMonitoringServer !== $host->getMonitoringServerId()) {
                // Monitoring server has changed, notify previous monitoring server of configuration changes.
                 $this->writeMonitoringServerRepository->notifyConfigurationChange($previousMonitoringServer);
            }

            $this->dataStorageEngine->commitTransaction();
        } catch (\Throwable $ex) {
            $this->error("Rollback of 'PartialUpdateHost' transaction", ['trace' => $ex->getTraceAsString()]);
            $this->dataStorageEngine->rollbackTransaction();

            throw $ex;
        }
    }

    /**
     * @param PartialUpdateHostRequest $dto
     * @param Host $host
     *
     * @throws \Throwable|AssertionFailedException|HostException
     */
    private function updateHost(PartialUpdateHostRequest $dto, Host $host): void
    {
        $this->info('PartialUpdateHost: update host', ['host_id' => $host->getId()]);

        $inheritanceMode = $this->optionService->findSelectedOptions(['inheritance_mode']);
        $inheritanceMode = isset($inheritanceMode[0])
            ? (int) $inheritanceMode[0]->getValue()
            : null;

        if (! $dto->name instanceOf NoValue) {
            $this->validation->assertIsValidName($dto->name, $host);
            $host->setName($dto->name);
        }

        if (! $dto->address instanceOf NoValue) {
            $host->setAddress($dto->address);
        }

        if (! $dto->monitoringServerId instanceOf NoValue) {
            $this->validation->assertIsValidMonitoringServer($dto->monitoringServerId);
            $host->setMonitoringServerId($dto->monitoringServerId);
        }

        if (! $dto->alias instanceOf NoValue) {
            $host->setAlias($dto->alias ?? '');
        }

        if (! $dto->snmpCommunity instanceOf NoValue) {
            $host->setSnmpCommunity($dto->snmpCommunity ?? '');
        }

        if (! $dto->noteUrl instanceOf NoValue) {
            $host->setNoteUrl($dto->noteUrl ?? '');
        }

        if (! $dto->note instanceOf NoValue) {
            $host->setNote($dto->note ?? '');
        }

        if (! $dto->actionUrl instanceOf NoValue) {
            $host->setActionUrl($dto->actionUrl ?? '');
        }

        if (! $dto->iconId instanceOf NoValue) {
            $this->validation->assertIsValidIcon($dto->iconId);
            $host->setIconId($dto->iconId);
        }

        if (! $dto->iconAlternative instanceOf NoValue) {
            $host->setIconAlternative($dto->iconAlternative ?? '');
        }

        if (! $dto->comment instanceOf NoValue) {
            $host->setComment($dto->comment ?? '');
        }

        if (! $dto->checkCommandArgs instanceOf NoValue) {
            $host->setCheckCommandArgs($dto->checkCommandArgs);
        }

        if (! $dto->eventHandlerCommandArgs instanceOf NoValue) {
            $host->setEventHandlerCommandArgs($dto->eventHandlerCommandArgs);
        }

        if (! $dto->timezoneId instanceOf NoValue) {
            $this->validation->assertIsValidTimezone($dto->timezoneId);
            $host->setTimezoneId($dto->timezoneId);
        }

        if (! $dto->severityId instanceOf NoValue) {
            $this->validation->assertIsValidSeverity($dto->severityId);
            $host->setSeverityId($dto->severityId);
        }

        if (! $dto->checkCommandId instanceOf NoValue) {
            $this->validation->assertIsValidCommand($dto->checkCommandId, CommandType::Check, 'checkCommandId');
            $host->setCheckCommandId($dto->checkCommandId);
        }

        if (! $dto->checkTimeperiodId instanceOf NoValue) {
            $this->validation->assertIsValidTimePeriod($dto->checkTimeperiodId, 'checkTimeperiodId');
            $host->setCheckTimeperiodId($dto->checkTimeperiodId);
        }

        if (! $dto->notificationTimeperiodId instanceOf NoValue) {
            $this->validation->assertIsValidTimePeriod($dto->notificationTimeperiodId, 'notificationTimeperiodId');
            $host->setNotificationTimeperiodId($dto->notificationTimeperiodId);
        }

        if (! $dto->eventHandlerCommandId instanceOf NoValue) {
            $this->validation->assertIsValidCommand($dto->eventHandlerCommandId, null, 'eventHandlerCommandId');
            $host->setEventHandlerCommandId($dto->eventHandlerCommandId);
        }

        if (! $dto->maxCheckAttempts instanceOf NoValue) {
            $host->setMaxCheckAttempts($dto->maxCheckAttempts);
        }

        if (! $dto->normalCheckInterval instanceOf NoValue) {
            $host->setNormalCheckInterval($dto->normalCheckInterval);
        }

        if (! $dto->retryCheckInterval instanceOf NoValue) {
            $host->setRetryCheckInterval($dto->retryCheckInterval);
        }

        if (! $dto->notificationInterval instanceOf NoValue) {
            $host->setNotificationInterval($dto->notificationInterval);
        }

        if (! $dto->firstNotificationDelay instanceOf NoValue) {
            $host->setFirstNotificationDelay($dto->firstNotificationDelay);
        }

        if (! $dto->recoveryNotificationDelay instanceOf NoValue) {
            $host->setRecoveryNotificationDelay($dto->recoveryNotificationDelay);
        }

        if (! $dto->acknowledgementTimeout instanceOf NoValue) {
            $host->setAcknowledgementTimeout($dto->acknowledgementTimeout);
        }

        if (! $dto->freshnessThreshold instanceOf NoValue) {
            $host->setFreshnessThreshold($dto->freshnessThreshold);
        }

        if (! $dto->lowFlapThreshold instanceOf NoValue) {
            $host->setLowFlapThreshold($dto->lowFlapThreshold);
        }

        if (! $dto->highFlapThreshold instanceOf NoValue) {
            $host->setHighFlapThreshold($dto->highFlapThreshold);
        }

        if (! $dto->isActivated instanceOf NoValue) {
            $host->setIsActivated($dto->isActivated);
        }

        if (! $dto->activeCheckEnabled instanceOf NoValue) {
            $host->setActiveCheckEnabled(YesNoDefaultConverter::fromScalar($dto->activeCheckEnabled));
        }

        if (! $dto->passiveCheckEnabled instanceOf NoValue) {
            $host->setPassiveCheckEnabled(YesNoDefaultConverter::fromScalar($dto->passiveCheckEnabled));
        }

        if (! $dto->notificationEnabled instanceOf NoValue) {
            $host->setNotificationEnabled(YesNoDefaultConverter::fromScalar($dto->notificationEnabled));
        }

        if (! $dto->freshnessChecked instanceOf NoValue) {
            $host->setFreshnessChecked(YesNoDefaultConverter::fromScalar($dto->freshnessChecked));
        }

        if (! $dto->flapDetectionEnabled instanceOf NoValue) {
            $host->setFlapDetectionEnabled(YesNoDefaultConverter::fromScalar($dto->flapDetectionEnabled));
        }

        if (! $dto->eventHandlerEnabled instanceOf NoValue) {
            $host->setEventHandlerEnabled(YesNoDefaultConverter::fromScalar($dto->eventHandlerEnabled));
        }

        if (! $dto->snmpVersion instanceOf NoValue) {
            $host->setSnmpVersion(
                $dto->snmpVersion === '' || $dto->snmpVersion === null
                    ? null
                    : SnmpVersion::from($dto->snmpVersion)
            );
        }

        if (! $dto->geoCoordinates instanceOf NoValue) {
            $host->setGeoCoordinates(
                $dto->geoCoordinates === '' || $dto->geoCoordinates === null
                    ? null
                    : GeoCoords::fromString($dto->geoCoordinates)
            );
        }

        if (! $dto->notificationOptions instanceOf NoValue) {
            $host->setNotificationOptions(
                $dto->notificationOptions === null
                    ? []
                    : HostEventConverter::fromBitFlag($dto->notificationOptions)
            );
        }

        if (! $dto->addInheritedContactGroup instanceOf NoValue) {
            $host->setAddInheritedContactGroup(
                $inheritanceMode === self::VERTICAL_INHERITANCE_MODE ? $dto->addInheritedContactGroup : false
            );
        }

        if (! $dto->addInheritedContact instanceOf NoValue) {
            $host->setAddInheritedContact(
                $inheritanceMode === self::VERTICAL_INHERITANCE_MODE ? $dto->addInheritedContact : false
            );
        }

        if ($this->writeVaultRepository->isVaultConfigured() && ! $dto->snmpCommunity instanceOf NoValue) {
            $vaultPaths = $this->writeVaultRepository->upsert(
                $this->uuid ?? null,
                [VaultConfiguration::HOST_SNMP_COMMUNITY_KEY => $host->getSnmpCommunity()]
            );
            $this->uuid ??= $this->getUuidFromPath($vaultPaths[VaultConfiguration::HOST_SNMP_COMMUNITY_KEY]);
            $host->setSnmpCommunity($vaultPaths[VaultConfiguration::HOST_SNMP_COMMUNITY_KEY]);
        }

        $this->writeHostRepository->update($host);
    }

    /**
     * @param PartialUpdateHostRequest $dto
     * @param Host $host
     *
     * @throws \Throwable|AssertionFailedException|HostException
     */
    private function updateHostCategories(PartialUpdateHostRequest $dto, Host $host): void
    {
        $this->info(
            'PartialUpdateHost: update categories',
            ['host_id' => $host->getId(), 'categories' => $dto->categories]
        );

        if ($dto->categories instanceOf NoValue) {
            $this->info('Categories not provided, nothing to update');

            return;
        }

        $categoryIds = array_unique($dto->categories);
        $this->validation->assertAreValidCategories($categoryIds);

        if ($this->user->isAdmin()) {
            $originalCategories = $this->readHostCategoryRepository->findByHost($host->getId());
        } else {
            $originalCategories = $this->readHostCategoryRepository->findByHostAndAccessGroups(
                $host->getId(),
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

        $this->writeHostCategoryRepository->linkToHost($host->getId(), $addedCategories);
        $this->writeHostCategoryRepository->unlinkFromHost($host->getId(), $removedCategories);
    }

    /**
     * @param PartialUpdateHostRequest $dto
     * @param Host $host
     *
     * @throws HostException
     * @throws \Throwable
     */
    private function updateHostGroups(PartialUpdateHostRequest $dto, Host $host): void
    {
        $this->info(
            'PartialUpdateHost: update groups',
            ['host_id' => $host->getId(), 'groups' => $dto->groups]
        );

        if ($dto->groups instanceOf NoValue) {
            $this->info('Groups not provided, nothing to update');

            return;
        }

        $groupIds = array_unique($dto->groups);
        $this->validation->assertAreValidGroups($groupIds);

        if ($this->user->isAdmin()) {
            $originalGroups = $this->readHostGroupRepository->findByHost($host->getId());
        } else {
            $originalGroups = $this->readHostGroupRepository->findByHostAndAccessGroups(
                $host->getId(),
                $this->accessGroups
            );
        }

        $originalGroupIds = array_map(
            static fn(HostGroup $group): int => $group->getId(),
            $originalGroups
        );

        $groupDiff = new BasicDifference($originalGroupIds, $groupIds);
        $addedGroups = $groupDiff->getAdded();
        $removedGroups = $groupDiff->getRemoved();

        $this->writeHostGroupRepository->linkToHost($host->getId(), $addedGroups);
        $this->writeHostGroupRepository->unlinkFromHost($host->getId(), $removedGroups);
    }

    /**
     * @param PartialUpdateHostRequest $dto
     * @param Host $host
     *
     * @throws \Throwable|HostException
     */
    private function updateParentTemplates(PartialUpdateHostRequest $dto, Host $host): void
    {
        $this->info(
            'PartialUpdateHost: Update parent templates',
            ['host_id' => $host->getId(), 'template_ids' => $dto->templates]
        );

        if ($dto->templates instanceOf NoValue) {
            $this->info('Parent templates not provided, nothing to update');

            return;
        }

        /** @var int[] $parentTemplateIds */
        $parentTemplateIds = array_unique($dto->templates);
        $this->validation->assertAreValidTemplates($parentTemplateIds, $host->getId());

        $this->info('Remove parent templates from a host', ['host_id' => $host->getId()]);
        $this->writeHostRepository->deleteParents($host->getId());

        foreach ($parentTemplateIds as $order => $templateId) {
            $this->info('Add a parent template to a host', [
                'host_id' => $host->getId(), 'parent_id' => $templateId, 'order' => $order,
            ]);
            $this->writeHostRepository->addParent($host->getId(), $templateId, $order);
        }
    }

    /**
     * @param PartialUpdateHostRequest $dto
     * @param Host $host
     *
     * @throws \Throwable
     */
    private function updateMacros(PartialUpdateHostRequest $dto, Host $host): void
    {
        $this->info(
            'PartialUpdateHost: update macros',
            ['host_id' => $host->getId(), 'macros' => $dto->macros]
        );

        if ($dto->macros instanceOf NoValue) {
            $this->info('Macros not provided, nothing to update');

            return;
        }

        /**
         * @var array<string,Macro> $directMacros
         * @var array<string,Macro> $inheritedMacros
         * @var array<string,CommandMacro> $commandMacros
         */
        [$directMacros, $inheritedMacros, $commandMacros] = $this->findOriginalMacros($host);

        $macros = [];
        foreach ($dto->macros as $data) {
            $macro = HostMacroFactory::create($data, $host->getId(), $directMacros, $inheritedMacros);
            $macros[$macro->getName()] = $macro;
        }

        $macrosDiff = new MacroDifference();
        $macrosDiff->compute($directMacros, $inheritedMacros, $commandMacros, $macros);

        MacroManager::setOrder($macrosDiff, $macros, $directMacros);

        foreach ($macrosDiff->removedMacros as $macro) {
            $this->updateMacroInVault($macro, 'DELETE');
            $this->writeHostMacroRepository->delete($macro);
        }

        foreach ($macrosDiff->updatedMacros as $macro) {
            $macro = $this->updateMacroInVault($macro, 'INSERT');
            $this->writeHostMacroRepository->update($macro);
        }

        foreach ($macrosDiff->addedMacros as $macro) {
            if ($macro->getDescription() === '') {
                $macro->setDescription(
                    isset($commandMacros[$macro->getName()])
                    ? $commandMacros[$macro->getName()]->getDescription()
                    : ''
                );
            }
            $macro = $this->updateMacroInVault($macro, 'INSERT');
            $this->writeHostMacroRepository->add($macro);
        }
    }

    /**
     * Find macros of a host:
     *  - macros linked directly,
     *  - macros linked through template inheritance,
     *  - macros linked through command inheritance.
     *
     * @param Host $host
     *
     * @throws \Throwable
     *
     * @return array{
     *      array<string,Macro>,
     *      array<string,Macro>,
     *      array<string,CommandMacro>
     * }
     */
    private function findOriginalMacros(Host $host): array
    {
        $templateParents = $this->readHostRepository->findParents($host->getId());
        $inheritanceLine = InheritanceManager::findInheritanceLine($host->getId(), $templateParents);
        $existingHostMacros = $this->readHostMacroRepository->findByHostIds(array_merge([$host->getId()], $inheritanceLine));

        [$directMacros, $inheritedMacros] = Macro::resolveInheritance(
            $existingHostMacros,
            $inheritanceLine,
            $host->getId()
        );

        /** @var array<string,CommandMacro> $commandMacros */
        $commandMacros = [];
        if ($host->getCheckCommandId() !== null) {
            $existingCommandMacros = $this->readCommandMacroRepository->findByCommandIdAndType(
                $host->getCheckCommandId(),
                CommandMacroType::Host
            );

            $commandMacros = MacroManager::resolveInheritanceForCommandMacro($existingCommandMacros);
        }

        return [
            $this->writeVaultRepository->isVaultConfigured()
                ? $this->retrieveMacrosVaultValues($directMacros)
                : $directMacros,
            $this->writeVaultRepository->isVaultConfigured()
                ? $this->retrieveMacrosVaultValues($inheritedMacros)
                : $inheritedMacros,
            $commandMacros,
        ];
    }

    /**
     * @param Host $host
     *
     * @throws \Throwable
     */
    private function retrieveHostUuidFromVault(Host $host): void
    {
        $this->uuid = $this->getUuidFromPath($host->getSnmpCommunity());
        if (null === $this->uuid) {
            $macros = $this->readHostMacroRepository->findByHostId($host->getId());
            foreach ($macros as $macro) {
                if (
                    $macro->isPassword() === true
                    && null !== ($this->uuid = $this->getUuidFromPath($macro->getValue()))
                ) {

                    break;
                }
            }
        }
    }

    /**
     * Upsert or delete macro for vault storage and return macro with updated value (aka vaultPath).
     *
     * @param Macro $macro
     * @param string $action
     *
     * @throws \Throwable
     *
     * @return Macro
     */
    private function updateMacroInVault(Macro $macro, string $action): Macro
    {
        if ($this->writeVaultRepository->isVaultConfigured() && $macro->isPassword() === true) {
            $macroPrefixedName = '_HOST' . $macro->getName();
            $vaultPaths = $this->writeVaultRepository->upsert(
                $this->uuid ?? null,
                $action === 'INSERT' ? [$macroPrefixedName => $macro->getValue()] : [],
                $action === 'DELETE' ? [$macroPrefixedName => $macro->getValue()] : [],
            );
            $this->uuid ??= $this->getUuidFromPath($vaultPaths[$macroPrefixedName]);

            $inVaultMacro = new Macro($macro->getOwnerId(), $macro->getName(), $vaultPaths[$macroPrefixedName]);
            $inVaultMacro->setDescription($macro->getDescription());
            $inVaultMacro->setIsPassword($macro->isPassword());
            $inVaultMacro->setOrder($macro->getOrder());

            return $inVaultMacro;
        }

        return $macro;
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
            if (false === $macro->isPassword() || false === $this->isAVaultPath($macro->getValue())) {
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
