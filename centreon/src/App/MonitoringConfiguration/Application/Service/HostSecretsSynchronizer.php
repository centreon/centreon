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

namespace App\MonitoringConfiguration\Application\Service;

use App\MonitoringConfiguration\Domain\Aggregate\Host\Host;
use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacro;
use App\MonitoringConfiguration\Domain\Aggregate\Host\SnmpCommunity;
use App\MonitoringConfiguration\Domain\Exception\VaultWriteFailedException;
use App\Shared\Application\Vault\VaultCredentialWriter;
use App\Shared\Domain\NoValue;
use App\Shared\Domain\Vault\VaultCredentials;
use App\Shared\Domain\Vault\VaultKeyEnum;
use App\Shared\Domain\Vault\VaultPathEnum;
use App\Shared\Domain\VaultInterface;

/**
 * Keeps the secrets of a host, its SNMP community and its password macros, in line with its own vault
 * entry before the host is saved. Legacy keeps them all under one entry: the one the host already has
 * is reused, and a new one is minted once, by whichever secret is written first.
 *
 * - the community given (a plaintext value) is written to the entry; a community removed has its key
 *   removed. A reference to the vault is never trusted from the caller: the host keeps the community it
 *   has;
 * - the macros go through {@see HostMacroSecretsSynchronizer}, with the entry the community may just
 *   have created;
 * - without a vault, nothing is vaulted and the community is stored as is.
 *
 * Like the full update of a host, the vault is written before the host is saved and is outside its
 * transaction: when saving fails afterwards, the entry keeps what was written.
 */
final readonly class HostSecretsSynchronizer
{
    public function __construct(
        private VaultInterface $vault,
        private VaultCredentialWriter $vaultCredentialWriter,
        private HostMacroSecretsSynchronizer $macroSynchronizer,
    ) {
    }

    /**
     * @param Host $before the host as it is stored
     * @param Host $after the host the update gives, its macros in the form they were submitted
     * @param NoValue|string|null $community what the update says about the SNMP community
     * @param bool $macrosChanged whether the update touches the macros
     * @param bool $vaultWritten set to true once a vault write has succeeded, so the caller can report that the
     *                           vault may diverge from the database if the host is not saved afterwards
     *
     * @param-out bool $vaultWritten
     *
     * @throws VaultWriteFailedException
     */
    public function synchronize(Host $before, Host $after, NoValue|string|null $community, bool $macrosChanged, bool &$vaultWritten): SynchronizedHost
    {
        if (! $this->vault->isEnabled()) {
            return new SynchronizedHost($this->withCommunityAsIs($after, $community), false);
        }

        $vaultUuid = $before->getVaultUuid($this->vault);
        $secretRewritten = false;

        $snmpCommunity = $after->snmpCommunity;
        if ($community === null) {
            $snmpCommunity = null;
            if ($vaultUuid !== null && $before->snmpCommunity instanceof SnmpCommunity && $this->vault->isVaultPath($before->snmpCommunity->value)) {
                $this->write($before, VaultCredentials::empty()->clear(VaultKeyEnum::HostSnmpCommunity), $vaultUuid, $vaultWritten);
            }
        } elseif (is_string($community) && ! $this->vault->isVaultPath($community)) {
            $stored = $this->write($before, VaultCredentials::empty()->set(VaultKeyEnum::HostSnmpCommunity, $community), $vaultUuid, $vaultWritten);
            $snmpCommunity = new SnmpCommunity($stored[VaultKeyEnum::HostSnmpCommunity->value]);
            $vaultUuid = $this->vault->extractUuid($snmpCommunity->value);
            $secretRewritten = true;
        }

        $macros = $after->checkOptions->macros;
        if ($macrosChanged) {
            $submitted = array_values($macros);
            $current = array_values($before->checkOptions->macros);
            $involvesAPassword = array_any([...$submitted, ...$current], static fn (HostMacro $macro): bool => $macro->isPassword);

            try {
                $macros = $this->macroSynchronizer->synchronize($submitted, $current, $vaultUuid);
            } catch (\Throwable $exception) {
                throw VaultWriteFailedException::forHost($before->id(), $exception);
            }

            $vaultWritten = $vaultWritten || $involvesAPassword;
            $secretRewritten = $secretRewritten || array_any(
                $macros,
                static fn (HostMacro $macro, int $index): bool => $macro->value !== $submitted[$index]->value,
            );
        }

        return new SynchronizedHost(
            $after->with(snmpCommunity: $snmpCommunity, checkOptions: $after->checkOptions->with(macros: $macros)),
            $secretRewritten,
        );
    }

    private function withCommunityAsIs(Host $host, NoValue|string|null $community): Host
    {
        if ($community instanceof NoValue) {
            return $host;
        }

        return $host->with(snmpCommunity: $community === null ? null : new SnmpCommunity($community));
    }

    /**
     * @param-out bool $vaultWritten
     *
     * @return array<string, string> the reference stored for each key written
     */
    private function write(Host $host, VaultCredentials $credentials, ?string $vaultUuid, bool &$vaultWritten): array
    {
        try {
            $stored = $this->vaultCredentialWriter->persist(VaultPathEnum::MonitoringHosts, $credentials, $vaultUuid);
        } catch (\Throwable $exception) {
            throw VaultWriteFailedException::forHost($host->id(), $exception);
        }

        $vaultWritten = true;

        return $stored;
    }
}
