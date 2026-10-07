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

use App\MonitoringConfiguration\Domain\Aggregate\Host\HostMacro;
use App\Shared\Application\Vault\VaultCredentialWriter;
use App\Shared\Domain\Vault\VaultCredentials;
use App\Shared\Domain\Vault\VaultPathEnum;
use App\Shared\Domain\VaultInterface;

/**
 * Keeps a host's password macros in line with its own vault entry before the host is persisted:
 * every direct password macro must be stored as a reference under the host's entry (the single
 * UUID it shares with its SNMP community, like legacy), never under a template's or another host's.
 *
 * - a plaintext value is written to the host's entry;
 * - a reference to another entry has its secret copied under the host's entry — never shared, so
 *   editing or deleting the template cannot change or purge the host's secret. The only such
 *   references are those HostMacroChangesResolver carries over when an inherited password is
 *   promoted to direct with its stored value kept: the API rejects any submitted reference;
 * - a reference already in the host's entry is left untouched, even after a rename;
 * - an empty value is stored as is, nothing vaulted;
 * - a key of the host's entry no macro references any more (macro removed, reverted to inherited as
 *   redundant, or no longer a password) is deleted from the entry.
 *
 * Vault keys follow legacy: `_HOST<NAME>`, without the `$...$` wrapper.
 */
final readonly class HostMacroSecretsSynchronizer
{
    private const KEY_PREFIX = '_HOST';

    public function __construct(
        private VaultInterface $vault,
        private VaultCredentialWriter $vaultCredentialWriter,
    ) {
    }

    /**
     * @param list<HostMacro> $macros the direct macros the host will own, values in raw stored form
     * @param list<HostMacro> $previous the direct macros the host owned so far (empty on creation)
     * @param ?string $hostVaultUuid the host's vault entry, null when it has none yet (one is minted
     *                               if a secret must be written)
     *
     * @return list<HostMacro> $macros with every password value to store
     */
    public function synchronize(array $macros, array $previous, ?string $hostVaultUuid): array
    {
        $previousKeys = $this->hostEntryKeys($previous, $hostVaultUuid);

        // Nothing to vault nor to purge: leave the vault untouched (not even a feature-flag probe),
        // so a host without password macros never reaches the vault at all.
        $hasPasswordMacro = array_any($macros, static fn (HostMacro $macro): bool => $macro->isPassword);
        if (! $hasPasswordMacro && $previousKeys === []) {
            return $macros;
        }

        if (! $this->vault->isEnabled()) {
            return $macros;
        }

        /** @var array<int, string> $toWrite plaintext indexed by position in $macros */
        $toWrite = [];
        /** @var array<string, true> $keptKeys keys of the host's entry still referenced as is */
        $keptKeys = [];
        /** @var array<string, int> $firstIndexByName */
        $firstIndexByName = [];
        foreach ($macros as $index => $macro) {
            // A host holds one macro per name, the first one wins whatever its type (see CheckOptions).
            if (isset($firstIndexByName[$macro->name->value])) {
                continue;
            }
            $firstIndexByName[$macro->name->value] = $index;
            // An empty password is stored as is, nothing vaulted.
            if (! $macro->isPassword || $macro->value === '') {
                continue;
            }

            if ($this->isInHostEntry($macro->value, $hostVaultUuid)) {
                $keptKeys[$this->keyOf($macro->value)] = true;

                continue;
            }

            $toWrite[$index] = $this->vault->isVaultPath($macro->value)
                ? $this->vault->resolve($macro->value)
                : $macro->value;
        }

        // A renamed macro keeps its reference, so its key may be the name another macro now
        // claims: that one would overwrite the secret the renamed macro still points to. Move the
        // renamed macro under its own name instead.
        $claimedKeys = [];
        foreach (array_keys($toWrite) as $index) {
            $claimedKeys[$this->keyFor($macros[$index])] = true;
        }
        foreach ($macros as $index => $macro) {
            if (
                $firstIndexByName[$macro->name->value] === $index
                && $macro->isPassword
                && $this->isInHostEntry($macro->value, $hostVaultUuid)
                && isset($claimedKeys[$this->keyOf($macro->value)])
                && $this->keyOf($macro->value) !== $this->keyFor($macro)
            ) {
                $toWrite[$index] = $this->vault->resolve($macro->value);
                $claimedKeys[$this->keyFor($macro)] = true;
            }
        }

        $credentials = VaultCredentials::empty();
        foreach (array_keys($previousKeys) as $key) {
            if (! isset($claimedKeys[$key]) && ! $this->isKeptBy($key, $macros, $hostVaultUuid, $toWrite)) {
                $credentials = $credentials->clear($key);
            }
        }
        foreach ($toWrite as $index => $plaintext) {
            $credentials = $credentials->set($this->keyFor($macros[$index]), $plaintext);
        }

        $stored = $this->vaultCredentialWriter->persist(VaultPathEnum::MonitoringHosts, $credentials, $hostVaultUuid);

        foreach (array_keys($toWrite) as $index) {
            $macro = $macros[$index];
            $macros[$index] = $macro->withValue($stored[$this->keyFor($macro)], true);
        }

        return array_values($macros);
    }

    /**
     * @param list<HostMacro> $macros
     *
     * @return array<string, true> the keys of the host's entry the macros reference
     */
    private function hostEntryKeys(array $macros, ?string $hostVaultUuid): array
    {
        $keys = [];
        foreach ($macros as $macro) {
            if ($macro->isPassword && $this->isInHostEntry($macro->value, $hostVaultUuid)) {
                $keys[$this->keyOf($macro->value)] = true;
            }
        }

        return $keys;
    }

    /**
     * @param list<HostMacro> $macros
     * @param array<int, string> $toWrite
     */
    private function isKeptBy(string $key, array $macros, ?string $hostVaultUuid, array $toWrite): bool
    {
        foreach ($macros as $index => $macro) {
            if (
                ! isset($toWrite[$index])
                && $macro->isPassword
                && $this->isInHostEntry($macro->value, $hostVaultUuid)
                && $this->keyOf($macro->value) === $key
            ) {
                return true;
            }
        }

        return false;
    }

    private function isInHostEntry(string $value, ?string $hostVaultUuid): bool
    {
        return $hostVaultUuid !== null
            && $this->vault->isVaultPath($value)
            && $this->vault->extractUuid($value) === $hostVaultUuid;
    }

    private function keyFor(HostMacro $macro): string
    {
        return self::KEY_PREFIX . $macro->name->value;
    }

    /**
     * The key a `secret::<vault>::<path>/<uuid>::<key>` reference points to: its last segment.
     */
    private function keyOf(string $reference): string
    {
        $position = mb_strrpos($reference, '::');

        return $position === false ? $reference : mb_substr($reference, $position + 2);
    }
}
