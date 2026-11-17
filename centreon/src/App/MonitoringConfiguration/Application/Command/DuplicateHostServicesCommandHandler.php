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

use App\MonitoringConfiguration\Domain\Aggregate\Service\Service;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceCheckOptions;
use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacro;
use App\MonitoringConfiguration\Domain\Event\ServiceDuplicated;
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
 * Links the source's shared services to the copy and clones its exclusive ones, whatever the actor may see, with
 * their vaulted secrets re-minted. Failures propagate, see DuplicateHostServicesEventHandler.
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
     * @throws VaultWriteFailedException
     */
    public function __invoke(DuplicateHostServicesCommand $command): void
    {
        $sources = $this->findExclusiveServices($command);

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
                $newReferences = $this->reMintSecrets($source);
                // Recorded as soon as it is written, so it is purged even if building the clone fails.
                $mintedVaultUuid = $newReferences === [] ? null : $this->vault->extractUuid(array_values($newReferences)[0]);
                if ($mintedVaultUuid !== null) {
                    $mintedVaultUuids[] = $mintedVaultUuid;
                }

                $clones[] = $source->duplicate($command->newHostId, new ServiceCheckOptions(
                    $source->checkOptions->checkCommandId,
                    $source->checkOptions->args,
                    $this->copyMacros($source, $newReferences),
                ));
            }

            foreach ($clones as $clone) {
                $this->serviceRepository->add($clone);
                $this->eventBus->fire(new ServiceDuplicated($clone, $command->duplicatedBy->value));
            }

            // Scope the copy's services for the groups that already see the source, without waiting for centAcl.
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
     * The source's exclusive services, fully loaded. One that can no longer be loaded (deleted since it was listed,
     * or invalid stored data) is skipped, with a warning since the copy then lacks it.
     *
     * @return list<Service>
     */
    private function findExclusiveServices(DuplicateHostServicesCommand $command): array
    {
        $services = [];
        foreach ($this->serviceRepository->findIdsExclusivelyLinkedToHostId($command->sourceHostId)->toArray() as $serviceId) {
            $service = $this->serviceRepository->findOneOnHost($serviceId, $command->sourceHostId);
            if ($service instanceof Service) {
                $services[] = $service;

                continue;
            }

            $this->logger->warning('A service of the duplicated host could not be loaded and is not copied.', [
                'source_host_id' => $command->sourceHostId->value,
                'new_host_id' => $command->newHostId->value,
                'service_id' => $serviceId->value,
            ]);
        }

        return $services;
    }

    /**
     * The source's vaulted passwords written again into a new vault entry, by vault key, so the clone never shares
     * a path of its source. Empty with the vault off or nothing vaulted.
     *
     * @throws VaultWriteFailedException
     *
     * @return array<string, string>
     */
    private function reMintSecrets(Service $source): array
    {
        if (! $this->vault->isEnabled() || $source->getVaultUuid($this->vault) === null) {
            return [];
        }

        $credentials = VaultCredentials::empty();
        foreach ($source->checkOptions->macros as $macro) {
            if ($macro->isPassword && $this->vault->isVaultPath($macro->value)) {
                $credentials = $credentials->with(self::vaultKey($macro), $macro->value);
            }
        }

        try {
            return $this->vaultWriter->write(VaultPathEnum::MonitoringServices, $this->vaultReader->resolveAll($credentials));
        } catch (\Throwable $exception) {
            throw VaultWriteFailedException::forServiceCopy($source->id(), $exception);
        }
    }

    /**
     * The source's own macros as new rows of the clone. Every password macro of a re-minted key points to the new
     * entry, two macros of the same name included; any other value is copied as it is, as legacy and host
     * duplication do.
     *
     * @param array<string, string> $newReferences
     *
     * @return list<ServiceMacro>
     */
    private function copyMacros(Service $source, array $newReferences): array
    {
        return array_map(
            static fn (ServiceMacro $macro): ServiceMacro => $macro->copyWithoutIdentity(
                $macro->isPassword ? ($newReferences[self::vaultKey($macro)] ?? null) : null,
            ),
            $source->checkOptions->macros,
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
     * Best-effort: a purge or logging failure never replaces the original cause.
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
