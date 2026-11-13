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

use App\MonitoringConfiguration\Domain\Aggregate\Host\HostId;
use App\MonitoringConfiguration\Domain\Aggregate\Service\Service;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceCheckOptions;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacro;
use App\MonitoringConfiguration\Domain\Aggregate\ServiceTemplate\ServiceTemplateId;
use App\MonitoringConfiguration\Domain\Event\ServiceDuplicated;
use App\MonitoringConfiguration\Domain\Exception\UnlicensedServiceTemplatesException;
use App\MonitoringConfiguration\Domain\Exception\VaultWriteFailedException;
use App\MonitoringConfiguration\Domain\Repository\ServiceRepository;
use App\Security\Domain\Repository\ResourceAccessRepository;
use App\Shared\Application\Command\AsCommandHandler;
use App\Shared\Application\Vault\VaultCredentialReader;
use App\Shared\Application\Vault\VaultCredentialWriter;
use App\Shared\Domain\Event\EventBus;
use App\Shared\Domain\Vault\VaultCredentials;
use App\Shared\Domain\Vault\VaultPathEnum;
use App\Shared\Domain\VaultInterface;
use Psr\Log\LoggerInterface;

/**
 * Gives the copy of a host the services of its source, as legacy multipleHostInDB does: a service the source shares
 * with another host or a host group is linked to the copy too, a service of the source alone is cloned onto it,
 * whatever the actor may see, with its vaulted secrets re-minted into a vault entry of its own.
 *
 * Failures propagate; the duplication path logs and swallows them, see DuplicateHostServicesEventHandler.
 */
#[AsCommandHandler]
final readonly class DuplicateHostServicesCommandHandler
{
    public function __construct(
        private ServiceRepository $serviceRepository,
        private ResourceAccessRepository $resourceAccessRepository,
        private VaultInterface $vault,
        private VaultCredentialReader $vaultReader,
        private VaultCredentialWriter $vaultWriter,
        private EventBus $eventBus,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @throws UnlicensedServiceTemplatesException
     * @throws VaultWriteFailedException
     */
    public function __invoke(DuplicateHostServicesCommand $command): void
    {
        $sources = $this->findExclusiveServices($command->sourceHostId);
        $this->assertLicensed($sources, $command);

        /** @var list<string> $mintedVaultUuids */
        $mintedVaultUuids = [];
        try {
            foreach ($this->serviceRepository->findIdsSharedWithOthers($command->sourceHostId)->toArray() as $serviceId) {
                $this->serviceRepository->addHostLink($serviceId, $command->newHostId);
            }

            // Every secret is minted before the first clone is written: writing a service locks the poller of the
            // copy until the commit, which must not wait for the vault.
            $clones = [];
            foreach ($sources as $source) {
                $clone = $source->duplicate($command->newHostId, new ServiceCheckOptions(
                    $source->checkOptions->checkCommandId,
                    $source->checkOptions->args,
                    $this->copyMacros($source),
                ));

                // A clone holding no vaulted secret of its own shares nothing to purge.
                $cloneVaultUuid = $clone->getVaultUuid($this->vault);
                if ($cloneVaultUuid !== null && $cloneVaultUuid !== $source->getVaultUuid($this->vault)) {
                    $mintedVaultUuids[] = $cloneVaultUuid;
                }
                $clones[] = $clone;
            }

            foreach ($clones as $clone) {
                $this->serviceRepository->add($clone);
                $this->eventBus->fire(new ServiceDuplicated($clone, $command->duplicatedBy->value));
            }

            // Now that the copy has all its services, scope them in centreon_acl for the groups that already see
            // the source host, so a non-admin sees them without waiting for the centAcl cron (which only
            // recomputes the actor's groups, not every group that sees the source).
            $this->resourceAccessRepository->duplicateHostServiceAccess(
                sourceHostId: $command->sourceHostId,
                newHostId: $command->newHostId,
            );
        } catch (\Throwable $exception) {
            // The rows roll back with the command's transaction, the vault entries minted for the clones do not.
            $this->purgeMintedVaultEntries($mintedVaultUuids, $command);

            throw $exception;
        }
    }

    /**
     * The services of the source host alone, wholly loaded. A service deleted since it was listed has nothing
     * left to clone.
     *
     * @return list<Service>
     */
    private function findExclusiveServices(HostId $sourceHostId): array
    {
        $services = [];
        foreach ($this->serviceRepository->findExclusivelyLinkedToHostId($sourceHostId)->toArray() as $listed) {
            $service = $this->serviceRepository->findOneOnHost($listed->id(), $sourceHostId);
            if ($service instanceof Service) {
                $services[] = $service;
            }
        }

        return $services;
    }

    /**
     * A clone is a new service: it can only be given its source's template while the license allows it, as read
     * before the host copy counted against the license host limit. Checked before anything is written, so a
     * refusal leaves nothing behind, in the vault either.
     *
     * @param list<Service> $sources
     *
     * @throws UnlicensedServiceTemplatesException
     */
    private function assertLicensed(array $sources, DuplicateHostServicesCommand $command): void
    {
        $unlicensedIds = array_map(
            static fn (ServiceTemplateId $id): int => $id->value,
            $command->unlicensedServiceTemplateIds->toArray(),
        );
        foreach ($sources as $source) {
            if ($source->templateId instanceof ServiceTemplateId && in_array($source->templateId->value, $unlicensedIds, true)) {
                throw new UnlicensedServiceTemplatesException($source->templateId->value);
            }
        }
    }

    /**
     * The source's own macros as new rows of the clone. Its vaulted passwords are resolved and written again into
     * a new vault entry, and every password macro of the same key is pointed to it, two macros of the same name
     * included, so the clone never shares a path of its source. With the vault off, or for a plaintext value, the
     * value is copied as it is, as legacy and host duplication do.
     *
     * @throws VaultWriteFailedException
     *
     * @return list<ServiceMacro>
     */
    private function copyMacros(Service $source): array
    {
        $macros = $source->checkOptions->macros;
        if (! $this->vault->isEnabled() || $source->getVaultUuid($this->vault) === null) {
            return array_map(static fn (ServiceMacro $macro): ServiceMacro => $macro->copyWithoutIdentity(), $macros);
        }

        $credentials = VaultCredentials::empty();
        foreach ($macros as $macro) {
            if ($macro->isPassword && $this->vault->isVaultPath($macro->value)) {
                $credentials = $credentials->with(self::vaultKey($macro), $macro->value);
            }
        }

        try {
            $newReferences = $this->vaultWriter->write(VaultPathEnum::MonitoringServices, $this->vaultReader->resolveAll($credentials));
        } catch (\Throwable $exception) {
            throw VaultWriteFailedException::forService(null, $exception);
        }

        return array_map(
            static fn (ServiceMacro $macro): ServiceMacro => $macro->copyWithoutIdentity(
                $macro->isPassword ? ($newReferences[self::vaultKey($macro)] ?? null) : null,
            ),
            $macros,
        );
    }

    /**
     * The vault key of a password macro, `_SERVICE<NAME>`, matching creation and legacy.
     */
    private static function vaultKey(ServiceMacro $macro): string
    {
        return '_SERVICE' . $macro->name->value;
    }

    /**
     * Best-effort: a purge failure must never replace the original cause. It is recorded with the entry it leaves
     * behind, and the logging is itself guarded so a failing logger cannot mask the cause either.
     *
     * @param list<string> $vaultUuids
     */
    private function purgeMintedVaultEntries(array $vaultUuids, DuplicateHostServicesCommand $command): void
    {
        foreach ($vaultUuids as $vaultUuid) {
            try {
                $this->vaultWriter->delete(VaultPathEnum::MonitoringServices, $vaultUuid);
            } catch (\Throwable $purgeException) {
                try {
                    $this->logger->warning('The vault entry minted for a service of a failed host duplication could not be purged.', [
                        'source_host_id' => $command->sourceHostId->value,
                        'new_host_id' => $command->newHostId->value,
                        'vault_uuid' => $vaultUuid,
                        'exception' => $purgeException,
                    ]);
                } catch (\Throwable) {
                }
            }
        }
    }
}
