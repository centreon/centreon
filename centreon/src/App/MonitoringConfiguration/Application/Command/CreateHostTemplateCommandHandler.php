<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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

namespace App\MonitoringConfiguration\Application\Command;

use App\MonitoringConfiguration\Application\Service\HostTemplateAdditiveInheritanceModeApplier;
use App\MonitoringConfiguration\Application\Service\HostTemplateLicenseGuard;
use App\MonitoringConfiguration\Application\Service\HostTemplateMacroSecretsSynchronizer;
use App\MonitoringConfiguration\Application\Service\LinkedServiceTemplateLicenseGuard;
use App\MonitoringConfiguration\Domain\Aggregate\Command\CommandId;
use App\MonitoringConfiguration\Domain\Aggregate\HostCategory\HostCategoryId;
use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityId;
use App\MonitoringConfiguration\Domain\Aggregate\HostSeverity\HostSeverityName;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateCheckOptions;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateMacro;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateMacroChange;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateSnmpCommunity;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateId;
use App\MonitoringConfiguration\Domain\Aggregate\Timezone\TimezoneId;
use App\MonitoringConfiguration\Domain\Aggregate\Timezone\TimezoneName;
use App\MonitoringConfiguration\Domain\Event\HostTemplateCreated;
use App\MonitoringConfiguration\Domain\Exception\DuplicateHostTemplateMacroNameException;
use App\MonitoringConfiguration\Domain\Exception\HostCategoryNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\HostSeverityNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\HostTemplateAlreadyExistsException;
use App\MonitoringConfiguration\Domain\Exception\HostTemplateMacroValueIsAVaultReferenceException;
use App\MonitoringConfiguration\Domain\Exception\HostTemplateNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\ServiceTemplateNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\TimezoneNotFoundException;
use App\MonitoringConfiguration\Domain\Exception\VaultWriteFailedException;
use App\MonitoringConfiguration\Domain\Repository\CommandRepository;
use App\MonitoringConfiguration\Domain\Repository\HostCategoryRepository;
use App\MonitoringConfiguration\Domain\Repository\HostSeverityRepository;
use App\MonitoringConfiguration\Domain\Repository\HostTemplateRepository;
use App\MonitoringConfiguration\Domain\Repository\ServiceTemplateRepository;
use App\MonitoringConfiguration\Domain\Repository\TimezoneRepository;
use App\MonitoringConfiguration\Domain\Service\HostTemplateMacroChangesResolver;
use App\MonitoringConfiguration\Domain\Service\InheritedHostMacrosResolver;
use App\Security\Domain\Aggregate\ActingUser;
use App\Security\Domain\Service\AccessScopeResolver;
use App\Shared\Application\Command\AsCommandHandler;
use App\Shared\Application\Vault\VaultCredentialWriter;
use App\Shared\Domain\Collection;
use App\Shared\Domain\Event\EventBus;
use App\Shared\Domain\Vault\VaultKeyEnum;
use App\Shared\Domain\Vault\VaultPathEnum;
use App\Shared\Domain\VaultInterface;
use Psr\Log\LoggerInterface;

#[AsCommandHandler]
final readonly class CreateHostTemplateCommandHandler
{
    public function __construct(
        private HostTemplateRepository $repository,
        private ServiceTemplateRepository $serviceTemplateRepository,
        private HostCategoryRepository $hostCategoryRepository,
        private HostSeverityRepository $hostSeverityRepository,
        private TimezoneRepository $timezoneRepository,
        private CommandRepository $commandRepository,
        private HostTemplateLicenseGuard $templateLicenseGuard,
        private LinkedServiceTemplateLicenseGuard $serviceTemplateLicenseGuard,
        private InheritedHostMacrosResolver $inheritedHostMacrosResolver,
        private HostTemplateMacroChangesResolver $macroChangesResolver,
        private HostTemplateMacroSecretsSynchronizer $macroSecretsSynchronizer,
        private HostTemplateAdditiveInheritanceModeApplier $additiveInheritanceModeApplier,
        private AccessScopeResolver $accessScopes,
        private VaultInterface $vault,
        private VaultCredentialWriter $vaultWriter,
        private EventBus $eventBus,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(CreateHostTemplateCommand $command): HostTemplate
    {
        // References first: a restricted actor must not tell a taken name (409) from a reference
        // out of their scope.
        $this->assertTemplatesExist($command->templateIds);
        $this->templateLicenseGuard->assertLicensed($command->templateIds);
        $this->assertServiceTemplatesExist($command->linkedServiceTemplateIds);
        $this->serviceTemplateLicenseGuard->assertLicensed($command->linkedServiceTemplateIds);
        $this->assertCategoriesExist($command->categoryIds, $command->actor);
        $this->assertSeverityExists($command->severityId, $command->actor);
        $this->assertTimezoneExists($command->timezoneId);
        if ($command->checkCommandId instanceof CommandId) {
            $this->commandRepository->getById($command->checkCommandId);
        }

        if ($this->repository->isNameUsedByHostOrTemplate($command->name)) {
            throw HostTemplateAlreadyExistsException::forName($command->name->value);
        }

        $macros = $this->resolveMacros($command);

        // A failure after the first vault write purges the entry minted here.
        $mintedVaultUuid = null;
        try {
            try {
                $snmpCommunity = $this->vaultOrPlaintext($command->snmpCommunity);
                $mintedVaultUuid = $this->vaultUuidOf($snmpCommunity);
                $macros = $this->macroSecretsSynchronizer->synchronize($macros, [], $mintedVaultUuid);
                $mintedVaultUuid ??= $this->vaultUuidOfMacros($macros);
            } catch (\Throwable $exception) {
                throw VaultWriteFailedException::forNewHostTemplate($command->name, $exception);
            }

            $template = HostTemplate::create(
                name: $command->name,
                alias: $command->alias,
                checkOptions: new HostTemplateCheckOptions(
                    $command->checkCommandId,
                    $command->checkCommandArgs,
                    new Collection($macros, HostTemplateMacro::class),
                ),
                parentTemplateIds: $command->templateIds,
                snmpCommunity: $snmpCommunity,
                snmpVersion: $command->snmpVersion,
                timezoneId: $command->timezoneId,
                severityId: $command->severityId,
                extendedInformations: $command->extendedInformations,
                schedulingOptions: $command->schedulingOptions,
                dataProcessing: $command->dataProcessing,
                notifications: $this->additiveInheritanceModeApplier->apply($command->notifications),
                categoryIds: $command->categoryIds,
                linkedServiceTemplateIds: $command->linkedServiceTemplateIds,
            );

            $this->repository->add($template);

            $this->eventBus->fire(new HostTemplateCreated($template, $command->actor->userId->value));
        } catch (\Throwable $exception) {
            if ($mintedVaultUuid !== null) {
                $this->purgeMintedVaultEntry($mintedVaultUuid);
            }

            throw $exception;
        }

        return $template;
    }

    /**
     * @return list<HostTemplateMacro>
     */
    private function resolveMacros(CreateHostTemplateCommand $command): array
    {
        /** @var list<HostTemplateMacroChange> $changes */
        $changes = array_values($command->macroChanges->toArray());

        $references = array_values(array_map(
            static fn (HostTemplateMacroChange $change): string => $change->name->value,
            array_filter(
                $changes,
                fn (HostTemplateMacroChange $change): bool => $change->value !== null && $this->vault->isVaultPath($change->value),
            ),
        ));
        if ($references !== []) {
            throw new HostTemplateMacroValueIsAVaultReferenceException($references);
        }

        $inherited = $this->inheritedHostMacrosResolver->resolve($command->templateIds, $command->checkCommandId);
        $macros = $this->macroChangesResolver->resolve($changes, [], $inherited);

        $names = array_map(static fn (HostTemplateMacro $macro): string => $macro->name->value, $macros);
        $duplicates = array_keys(array_filter(array_count_values($names), static fn (int $count): bool => $count > 1));
        if ($duplicates !== []) {
            throw new DuplicateHostTemplateMacroNameException(array_map(strval(...), $duplicates));
        }

        return $macros;
    }

    /**
     * With a vault, the column holds the `secret::` reference, so the community's length bound
     * measures that.
     */
    private function vaultOrPlaintext(?string $snmpCommunity): ?HostTemplateSnmpCommunity
    {
        if ($snmpCommunity === null) {
            return null;
        }

        if (! $this->vault->isEnabled()) {
            return new HostTemplateSnmpCommunity($snmpCommunity);
        }

        return new HostTemplateSnmpCommunity($this->vault->write(
            VaultPathEnum::MonitoringHosts->value,
            VaultKeyEnum::HostSnmpCommunity->value,
            $snmpCommunity,
        ));
    }

    private function vaultUuidOf(?HostTemplateSnmpCommunity $snmpCommunity): ?string
    {
        return $snmpCommunity instanceof HostTemplateSnmpCommunity && $this->vault->isVaultPath($snmpCommunity->value)
            ? $this->vault->extractUuid($snmpCommunity->value)
            : null;
    }

    /**
     * @param list<HostTemplateMacro> $macros
     */
    private function vaultUuidOfMacros(array $macros): ?string
    {
        $vaulted = array_find(
            $macros,
            fn (HostTemplateMacro $macro): bool => $macro->isPassword && $this->vault->isVaultPath($macro->value),
        );

        return $vaulted instanceof HostTemplateMacro ? $this->vault->extractUuid($vaulted->value) : null;
    }

    /**
     * Best effort: the purge failure is logged, never thrown over the original cause.
     */
    private function purgeMintedVaultEntry(string $uuid): void
    {
        try {
            $this->vaultWriter->delete(VaultPathEnum::MonitoringHosts, $uuid);
        } catch (\Throwable $purgeException) {
            try {
                $this->logger->warning('The vault entry minted for a failed host template creation could not be purged.', [
                    'vault_uuid' => $uuid,
                    'exception' => $purgeException,
                ]);
            } catch (\Throwable) {
            }
        }
    }

    /**
     * Host templates are never ACL-scoped.
     *
     * @param Collection<HostTemplateId> $templateIds
     */
    private function assertTemplatesExist(Collection $templateIds): void
    {
        $missingIds = array_values(array_unique(array_diff(
            array_map(static fn (HostTemplateId $id): int => $id->value, $templateIds->toArray()),
            array_keys($this->repository->findNamesByIds($templateIds)->toArray()),
        )));

        if ($missingIds !== []) {
            throw new HostTemplateNotFoundException($missingIds);
        }
    }

    /**
     * @param Collection<ServiceTemplateId> $serviceTemplateIds
     */
    private function assertServiceTemplatesExist(Collection $serviceTemplateIds): void
    {
        $missingIds = array_values(array_unique(array_diff(
            array_map(static fn (ServiceTemplateId $id): int => $id->value, $serviceTemplateIds->toArray()),
            array_keys($this->serviceTemplateRepository->findNamesByIds($serviceTemplateIds)->toArray()),
        )));

        if ($missingIds !== []) {
            throw new ServiceTemplateNotFoundException($missingIds);
        }
    }

    /**
     * @param Collection<HostCategoryId> $categoryIds
     */
    private function assertCategoriesExist(Collection $categoryIds, ActingUser $actor): void
    {
        if (count($categoryIds) === 0) {
            return;
        }

        $missingIds = $this->accessScopes->hostCategories($actor)->missing(
            $categoryIds,
            array_keys($this->hostCategoryRepository->findNamesByIds($categoryIds)->toArray()),
        );

        if ($missingIds !== []) {
            throw new HostCategoryNotFoundException($missingIds);
        }
    }

    private function assertSeverityExists(?HostSeverityId $severityId, ActingUser $actor): void
    {
        if (! $severityId instanceof HostSeverityId) {
            return;
        }

        $missingIds = $this->accessScopes->hostSeverities($actor)->missing(
            [$severityId],
            $this->hostSeverityRepository->findNameById($severityId) instanceof HostSeverityName ? [$severityId->value] : [],
        );

        if ($missingIds !== []) {
            throw new HostSeverityNotFoundException($severityId->value);
        }
    }

    private function assertTimezoneExists(?TimezoneId $timezoneId): void
    {
        if ($timezoneId instanceof TimezoneId && ! $this->timezoneRepository->findNameById($timezoneId) instanceof TimezoneName) {
            throw new TimezoneNotFoundException($timezoneId->value);
        }
    }
}
