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

use App\MonitoringConfiguration\Domain\Aggregate\Host\CheckOptions;
use App\MonitoringConfiguration\Domain\Aggregate\Host\Host;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostName;
use App\MonitoringConfiguration\Domain\Aggregate\Host\SnmpCommunity;
use App\MonitoringConfiguration\Domain\Event\HostDuplicated;
use App\MonitoringConfiguration\Domain\Event\HostServicesDuplicationRequested;
use App\MonitoringConfiguration\Domain\Exception\HostAlreadyExistsException;
use App\MonitoringConfiguration\Domain\Exception\HostNotFoundException;
use App\MonitoringConfiguration\Domain\Repository\HostRepository;
use App\Security\Domain\Aggregate\UserId;
use App\Security\Domain\Repository\AccessGroupRepository;
use App\Security\Domain\Repository\ResourceAccessRepository;
use App\Shared\Application\Command\AsCommandHandler;
use App\Shared\Application\Vault\VaultCredentialReader;
use App\Shared\Application\Vault\VaultCredentialWriter;
use App\Shared\Domain\Event\EventBus;
use App\Shared\Domain\Vault\VaultCredentials;
use App\Shared\Domain\Vault\VaultKeyEnum;
use App\Shared\Domain\Vault\VaultPathEnum;
use App\Shared\Domain\VaultInterface;

#[AsCommandHandler]
final readonly class DuplicateHostCommandHandler
{
    /**
     * Legacy bounds a duplication run to fewer than 1000 copies (DB-Func.php multipleHostInDB); we
     * reuse the same ceiling as the number of `_<n>` suffixes tried before giving up on a free name.
     */
    private const MAX_NAME_ATTEMPTS = 999;

    public function __construct(
        private HostRepository $repository,
        private ResourceAccessRepository $resourceAccessRepository,
        private AccessGroupRepository $accessGroupRepository,
        private VaultInterface $vault,
        private VaultCredentialReader $vaultReader,
        private VaultCredentialWriter $vaultWriter,
        private EventBus $eventBus,
    ) {
    }

    public function __invoke(DuplicateHostCommand $command): void
    {
        // ACL-scoped read: a host outside a restricted viewer's scope reads as not found, the same as
        // one that does not exist, so as not to leak existence (see HostRepository::findOne()).
        $source = $this->repository->findOne($command->hostId, $command->viewerId);
        if (! $source instanceof Host) {
            throw new HostNotFoundException([$command->hostId->value], 'id');
        }

        // A vaulted secret is stored as a `secret::` reference to the source's vault entry; copying it
        // verbatim would make the copy share the source's vault paths, so editing or deleting one
        // host's secret would silently hit the other. The source's secrets are re-minted into a fresh
        // entry so the copy owns its own paths (legacy duplicateHostSecretsInVault).
        [$snmpCommunity, $checkOptions] = $this->duplicateSecrets($source);

        $copy = new Host(
            id: null,
            name: $this->generateAvailableName($source->name),
            alias: $source->alias,
            address: $source->address,
            activated: $source->activated,
            pollerId: $source->pollerId,
            templateIds: $source->templateIds,
            hostGroupIds: $source->hostGroupIds,
            categoryIds: $source->categoryIds,
            parentHostIds: $source->parentHostIds,
            childHostIds: $source->childHostIds,
            snmpVersion: $source->snmpVersion,
            snmpCommunity: $snmpCommunity,
            timezoneId: $source->timezoneId,
            severityId: $source->severityId,
            extendedInformations: $source->extendedInformations,
            schedulingOptions: $source->schedulingOptions,
            dataProcessing: $source->dataProcessing,
            checkOptions: $checkOptions,
        );

        $this->repository->add($copy);

        // The copy inherits the source's ACL scope: its configuration relations
        // (acl_resources_host(ex)_relations) and its real-time centreon_acl rows. This mirrors legacy
        // (centreonACL::duplicateHostAcl + updateACL('DUP')).
        $this->resourceAccessRepository->duplicateHostAccess(
            sourceHostId: $command->hostId,
            newHostId: $copy->id(),
        );

        // Flag the centAcl cron so it recomputes the ACL scoping afterwards.
        $this->flagAclReload($command->viewerId);

        // Action log (with field detail) and the poller's `nagios_server.updated` flag are written
        // by the shared event handlers reacting to AggregateDuplicated.
        $this->eventBus->fire(new HostDuplicated($copy, $command->duplicatedBy));

        // The aggregate does not model services; they are duplicated by a legacy step delivered after
        // the commit (the copy must be visible to the legacy connection), like host creation deploys them.
        $this->eventBus->fire(new HostServicesDuplicationRequested(sourceHostId: $command->hostId, newHostId: $copy->id()));
    }

    /**
     * Re-mints the source host's vaulted secrets (its SNMP community and password macros) into a fresh
     * vault entry so the copy never shares the source's vault paths. The source stores them as
     * `secret::` references to its own entry; they are resolved back to plaintext and rewritten under a
     * new UUID, leaving fresh references on the copy. Every host secret lands under a single entry (one
     * UUID), the same layout as creation and legacy (duplicateHostSecretsInVault).
     *
     * @return array{0: ?SnmpCommunity, 1: CheckOptions}
     */
    private function duplicateSecrets(Host $source): array
    {
        // Vault off, or nothing vaulted: the stored values are plaintext (or empty) and copy correctly
        // as-is, with no entry to mint.
        if (! $this->vault->isEnabled() || $source->getVaultUuid($this->vault) === null) {
            return [$source->snmpCommunity, $source->checkOptions];
        }

        // Gather only the fields actually vaulted; a plaintext field (e.g. a vault enabled after the
        // source was created) is left untouched and copied verbatim below.
        $credentials = VaultCredentials::empty();

        $sourceSnmp = $source->snmpCommunity;
        if ($sourceSnmp instanceof SnmpCommunity && $this->vault->isVaultPath($sourceSnmp->value)) {
            $credentials = $credentials->with(VaultKeyEnum::HostSnmpCommunity, $sourceSnmp->value);
        }

        foreach ($source->checkOptions->macros as $macro) {
            if ($macro->isPassword && $this->vault->isVaultPath($macro->value)) {
                $credentials = $credentials->with($this->macroVaultKey($macro), $macro->value);
            }
        }

        // Resolve the source's references to plaintext, then write them under a fresh entry (null UUID)
        // so the copy's secrets share one new UUID, as creation and legacy keep them.
        $newReferences = $this->vaultWriter->write(
            VaultPathEnum::MonitoringHosts,
            $this->vaultReader->resolveAll($credentials),
        );

        // A field absent from the rewrite was not vaulted, so it keeps the source's value verbatim.
        $snmpKey = VaultKeyEnum::HostSnmpCommunity->value;
        $snmpCommunity = isset($newReferences[$snmpKey])
            ? new SnmpCommunity($newReferences[$snmpKey])
            : $source->snmpCommunity;

        $macros = array_map(
            function (HostMacro $macro) use ($newReferences): HostMacro {
                $key = $this->macroVaultKey($macro);
                if ($macro->isPassword && isset($newReferences[$key])) {
                    return new HostMacro($macro->name, $newReferences[$key], true, $macro->description);
                }

                return $macro;
            },
            $source->checkOptions->macros,
        );

        return [
            $snmpCommunity,
            new CheckOptions($source->checkOptions->checkCommandId, $source->checkOptions->args, $macros),
        ];
    }

    /**
     * The vault key for a password macro, `_HOST<NAME>`, matching creation and legacy.
     */
    private function macroVaultKey(HostMacro $macro): string
    {
        return '_HOST' . $macro->name->value;
    }

    /**
     * Appends the first free `_<n>` suffix, checked across hosts and host templates alike (they share
     * the `host` table and its name uniqueness). Unlike legacy, which silently skips a copy whose name
     * is taken, an exhausted range surfaces as a 409 rather than a no-op with no feedback.
     */
    private function generateAvailableName(HostName $sourceName): HostName
    {
        for ($index = 1; $index <= self::MAX_NAME_ATTEMPTS; $index++) {
            $candidate = $sourceName->value . '_' . $index;
            // The suffix only grows, so once it overflows the name length limit no suffix ever fits:
            // surface a distinct 409 instead of letting HostName throw an unmapped 500.
            if (mb_strlen($candidate) > HostName::MAX_LENGTH) {
                throw new HostAlreadyExistsException(
                    ['name' => $sourceName->value],
                    'The duplicated host name would exceed the maximum length.',
                );
            }

            $candidateName = new HostName($candidate);
            if (! $this->repository->isNameUsedByHostOrTemplate($candidateName)) {
                return $candidateName;
            }
        }

        throw new HostAlreadyExistsException(['name' => $sourceName->value]);
    }

    private function flagAclReload(?UserId $viewerId): void
    {
        // Admin: the copy may become visible through any group's resource-scoping rule.
        if (! $viewerId instanceof UserId) {
            $this->resourceAccessRepository->flagAllResourcesAsChanged();

            return;
        }

        $accessGroupIds = $this->accessGroupRepository->findActiveGroupIdsForUser($viewerId);
        if (count($accessGroupIds) > 0) {
            $this->accessGroupRepository->flagGroupsAsChanged($accessGroupIds);
        }
    }
}
