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

use App\MonitoringConfiguration\Domain\Aggregate\Service\ServiceMacro;
use App\Shared\Application\Vault\VaultCredentialWriter;
use App\Shared\Domain\Vault\VaultCredentials;
use App\Shared\Domain\Vault\VaultPathEnum;
use App\Shared\Domain\VaultInterface;

/**
 * Keeps a service's password macros in line with its own vault entry before the service is persisted: every own
 * password macro is stored as a reference under the service's entry (one entry per service, shared by its
 * password macros, under `monitoring/services`), never under a template's or another service's.
 *
 * - a plaintext value is written to the service's entry, a new one when it has none yet;
 * - a reference to another entry has its secret copied under the service's entry, never shared, so editing or
 *   deleting the template cannot change or purge the service's secret. The only such references are those
 *   ServiceMacroChangesResolver carries over when an inherited password is promoted with its stored value kept:
 *   the API refuses any submitted reference;
 * - a reference already in the service's entry is left untouched, even after a rename;
 * - an empty value is stored as is, nothing vaulted;
 * - a key of the service's entry no macro references any more (macro removed, reverted to inherited as
 *   redundant, or no longer a password) is deleted from the entry.
 *
 * Vault keys follow legacy: `_SERVICE<NAME>`, without the `$...$` wrapper.
 */
final readonly class ServiceMacroSecretsSynchronizer
{
    private const KEY_PREFIX = '_SERVICE';

    public function __construct(
        private VaultInterface $vault,
        private VaultCredentialWriter $vaultCredentialWriter,
    ) {
    }

    /**
     * @param list<ServiceMacro> $macros the own macros the service will hold, values in raw stored form
     * @param list<ServiceMacro> $previous the own macros the service held so far (empty on creation)
     * @param ?string $serviceVaultUuid the service's vault entry, null when it has none yet (one is minted if a
     *                                  secret must be written)
     *
     * @return list<ServiceMacro> $macros with every password value to store
     */
    public function synchronize(array $macros, array $previous, ?string $serviceVaultUuid): array
    {
        $previousKeys = $this->serviceEntryKeys($previous, $serviceVaultUuid);

        // Nothing to vault nor to purge: leave the vault untouched (not even a feature-flag probe), so a service
        // without password macros never reaches the vault at all.
        $hasPasswordMacro = array_any($macros, static fn (ServiceMacro $macro): bool => $macro->isPassword);
        if (! $hasPasswordMacro && $previousKeys === []) {
            return $macros;
        }

        if (! $this->vault->isEnabled()) {
            return $macros;
        }

        /** @var array<int, string> $toWrite plaintext indexed by position in $macros */
        $toWrite = [];
        /** @var array<string, int> $firstIndexByName */
        $firstIndexByName = [];
        foreach ($macros as $index => $macro) {
            // Duplicates are refused on write; a legacy service may still hold some: the first one wins.
            if (isset($firstIndexByName[$macro->name->value])) {
                continue;
            }
            $firstIndexByName[$macro->name->value] = $index;
            // An empty password is stored as is, nothing vaulted.
            if (! $macro->isPassword) {
                continue;
            }
            if ($macro->value === '') {
                continue;
            }

            if ($this->isInServiceEntry($macro->value, $serviceVaultUuid)) {
                continue;
            }

            $toWrite[$index] = $this->vault->isVaultPath($macro->value)
                ? $this->vault->resolve($macro->value)
                : $macro->value;
        }

        // A renamed macro keeps its reference, so its key may be the name another macro now claims: that one would
        // overwrite the secret the renamed macro still points to. Move the renamed macro under its own name instead.
        // Moving it claims a new key, which may in turn be the one another renamed macro points to (chained
        // renames): repeat until no key is claimed.
        $claimedKeys = [];
        foreach (array_keys($toWrite) as $index) {
            $claimedKeys[$this->keyFor($macros[$index])] = true;
        }
        do {
            $claimedKeyCount = count($claimedKeys);
            foreach ($macros as $index => $macro) {
                if (
                    $firstIndexByName[$macro->name->value] === $index
                    && ! isset($toWrite[$index])
                    && $macro->isPassword
                    && $this->isInServiceEntry($macro->value, $serviceVaultUuid)
                    && isset($claimedKeys[$this->keyOf($macro->value)])
                    && $this->keyOf($macro->value) !== $this->keyFor($macro)
                ) {
                    $toWrite[$index] = $this->vault->resolve($macro->value);
                    $claimedKeys[$this->keyFor($macro)] = true;
                }
            }
        } while (count($claimedKeys) > $claimedKeyCount);

        $credentials = VaultCredentials::empty();
        foreach (array_keys($previousKeys) as $key) {
            if (! isset($claimedKeys[$key]) && ! $this->isKeptBy($key, $macros, $serviceVaultUuid, $toWrite)) {
                $credentials = $credentials->clear($key);
            }
        }
        foreach ($toWrite as $index => $plaintext) {
            $credentials = $credentials->set($this->keyFor($macros[$index]), $plaintext);
        }

        $stored = $this->vaultCredentialWriter->persist(VaultPathEnum::MonitoringServices, $credentials, $serviceVaultUuid);

        foreach (array_keys($toWrite) as $index) {
            $macro = $macros[$index];
            $macros[$index] = $macro->withValue($stored[$this->keyFor($macro)], true);
        }

        return array_values($macros);
    }

    /**
     * @param list<ServiceMacro> $macros
     *
     * @return array<string, true> the keys of the service's entry the macros reference
     */
    private function serviceEntryKeys(array $macros, ?string $serviceVaultUuid): array
    {
        $keys = [];
        foreach ($macros as $macro) {
            if ($macro->isPassword && $this->isInServiceEntry($macro->value, $serviceVaultUuid)) {
                $keys[$this->keyOf($macro->value)] = true;
            }
        }

        return $keys;
    }

    /**
     * @param list<ServiceMacro> $macros
     * @param array<int, string> $toWrite
     */
    private function isKeptBy(string $key, array $macros, ?string $serviceVaultUuid, array $toWrite): bool
    {
        return array_any(
            $macros,
            fn (ServiceMacro $macro, int $index): bool => ! isset($toWrite[$index])
                && $macro->isPassword
                && $this->isInServiceEntry($macro->value, $serviceVaultUuid)
                && $this->keyOf($macro->value) === $key,
        );
    }

    private function isInServiceEntry(string $value, ?string $serviceVaultUuid): bool
    {
        return $serviceVaultUuid !== null
            && $this->vault->isVaultPath($value)
            && $this->vault->extractUuid($value) === $serviceVaultUuid;
    }

    private function keyFor(ServiceMacro $macro): string
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
