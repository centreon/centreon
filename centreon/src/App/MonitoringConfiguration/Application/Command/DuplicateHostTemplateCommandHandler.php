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

use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Host\SnmpCommunity;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplate;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateName;
use App\MonitoringConfiguration\Domain\Event\HostTemplateDuplicated;
use App\MonitoringConfiguration\Domain\Exception\DuplicatedHostTemplateNameTooLongException;
use App\MonitoringConfiguration\Domain\Exception\HostTemplateAlreadyExistsException;
use App\MonitoringConfiguration\Domain\Exception\HostTemplateLockedException;
use App\MonitoringConfiguration\Domain\Exception\HostTemplateNotFoundException;
use App\MonitoringConfiguration\Domain\Repository\HostTemplateRepository;
use App\Shared\Application\Command\AsCommandHandler;
use App\Shared\Application\Vault\VaultCredentialReader;
use App\Shared\Application\Vault\VaultCredentialWriter;
use App\Shared\Domain\Collection;
use App\Shared\Domain\Event\EventBus;
use App\Shared\Domain\Vault\VaultCredentials;
use App\Shared\Domain\Vault\VaultKeyEnum;
use App\Shared\Domain\Vault\VaultPathEnum;
use App\Shared\Domain\VaultInterface;

#[AsCommandHandler]
final readonly class DuplicateHostTemplateCommandHandler
{
    private const MAX_NAME_ATTEMPTS = 999;

    public function __construct(
        private HostTemplateRepository $repository,
        private VaultInterface $vault,
        private VaultCredentialReader $vaultReader,
        private VaultCredentialWriter $vaultWriter,
        private EventBus $eventBus,
    ) {
    }

    public function __invoke(DuplicateHostTemplateCommand $command): void
    {
        $source = $this->repository->findOne($command->id);
        if (! $source instanceof HostTemplate) {
            throw new HostTemplateNotFoundException([$command->id->value], 'id');
        }

        if ($source->isLocked) {
            throw HostTemplateLockedException::forId($command->id);
        }

        // Before the vault write: a refused name must not leave a minted entry behind.
        $newName = $command->name ?? $this->generateAvailableName($source->name);
        if ($command->name instanceof HostTemplateName && $this->repository->isNameUsedByHostOrTemplate($newName)) {
            throw HostTemplateAlreadyExistsException::forName($newName->value);
        }

        [$snmpCommunity, $macros] = $this->duplicateSecrets($source);
        $copy = $source->duplicate($newName, $snmpCommunity, $macros);

        try {
            $this->repository->addCopyOf($copy, $command->id);
            $this->eventBus->fire(new HostTemplateDuplicated($copy, $command->actor->userId->value));
        } catch (\Throwable $exception) {
            $this->purgeMintedVaultEntry($source, $copy);

            throw $exception;
        }
    }

    /**
     * The copy gets its own vault entry: copied verbatim, its `secret::` references would share the
     * source's entry. Every secret lands under one new UUID.
     *
     * @return array{0: ?SnmpCommunity, 1: Collection<HostMacro>}
     */
    private function duplicateSecrets(HostTemplate $source): array
    {
        if (! $this->vault->isEnabled() || $source->getVaultUuid($this->vault) === null) {
            return [
                $source->snmpCommunity,
                new Collection(
                    array_map(static fn (HostMacro $macro): HostMacro => $macro->copyWithoutIdentity(), $source->macros->toArray()),
                    HostMacro::class,
                ),
            ];
        }

        $credentials = VaultCredentials::empty();
        $sourceSnmp = $source->snmpCommunity;
        if ($sourceSnmp instanceof SnmpCommunity && $this->vault->isVaultPath($sourceSnmp->value)) {
            $credentials = $credentials->with(VaultKeyEnum::HostSnmpCommunity, $sourceSnmp->value);
        }

        foreach ($source->macros as $macro) {
            if ($macro->isPassword && $this->vault->isVaultPath($macro->value)) {
                $credentials = $credentials->with($this->macroVaultKey($macro), $macro->value);
            }
        }

        $newReferences = $this->vaultWriter->write(
            VaultPathEnum::MonitoringHosts,
            $this->vaultReader->resolveAll($credentials),
        );

        $snmpKey = VaultKeyEnum::HostSnmpCommunity->value;
        $snmpCommunity = isset($newReferences[$snmpKey])
            ? new SnmpCommunity($newReferences[$snmpKey])
            : $source->snmpCommunity;

        $macros = array_map(
            function (HostMacro $macro) use ($newReferences): HostMacro {
                $key = $this->macroVaultKey($macro);

                return $macro->copyWithoutIdentity($macro->isPassword && isset($newReferences[$key]) ? $newReferences[$key] : null);
            },
            $source->macros->toArray(),
        );

        return [$snmpCommunity, new Collection($macros, HostMacro::class)];
    }

    /**
     * Best effort: the original failure is what the caller must see, an orphan entry is harmless.
     */
    private function purgeMintedVaultEntry(HostTemplate $source, HostTemplate $copy): void
    {
        try {
            $copyUuid = $copy->getVaultUuid($this->vault);
            if ($copyUuid !== null && $copyUuid !== $source->getVaultUuid($this->vault)) {
                $this->vaultWriter->delete(VaultPathEnum::MonitoringHosts, $copyUuid);
            }
        } catch (\Throwable) {
        }
    }

    private function macroVaultKey(HostMacro $macro): string
    {
        return '_HOST' . $macro->name->value;
    }

    private function generateAvailableName(HostTemplateName $sourceName): HostTemplateName
    {
        for ($index = 1; $index <= self::MAX_NAME_ATTEMPTS; $index++) {
            $candidate = $sourceName->value . '_' . $index;
            // The suffix only grows: once it overflows, no later one fits either.
            if (mb_strlen($candidate) > HostTemplateName::MAX_LENGTH) {
                throw DuplicatedHostTemplateNameTooLongException::forSourceName($sourceName->value);
            }

            $candidateName = new HostTemplateName($candidate);
            if (! $this->repository->isNameUsedByHostOrTemplate($candidateName)) {
                return $candidateName;
            }
        }

        throw HostTemplateAlreadyExistsException::forName($sourceName->value);
    }
}
