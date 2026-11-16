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
use App\Shared\Domain\Collection;
use App\Shared\Domain\Vault\VaultCredentials;
use App\Shared\Domain\Vault\VaultPathEnum;
use App\Shared\Domain\VaultInterface;

/**
 * Keeps a service's password macros under its own vault entry (`monitoring/services`, keys `_SERVICE<NAME>`):
 *
 * - a plaintext value is written to the service's entry, a new one when it has none yet;
 * - a reference to another entry has its secret copied under the service's entry, never shared, so editing or
 *   deleting the template cannot change or purge the service's secret. The only such references are those
 *   ServiceMacroChangesResolver carries over when an inherited password is promoted with its stored value kept:
 *   the API refuses any submitted reference;
 * - a reference already in the service's entry, under the key of the macro's name, is left untouched; after a
 *   rename, the secret is written again under the new name and the old key deleted;
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
     * @param Collection<ServiceMacro> $ownMacros the own macros the service will hold, values in raw stored form
     * @param Collection<ServiceMacro> $previous the own macros the service held so far (empty on creation)
     * @param ?string $serviceVaultUuid the service's vault entry, null when it has none yet (one is minted if a
     *                                  secret must be written)
     */
    public function synchronize(Collection $ownMacros, Collection $previous, ?string $serviceVaultUuid): SynchronizedServiceMacros
    {
        // Positional from here on: the writes are tracked by index.
        $macros = array_values($ownMacros->toArray());
        $previousKeys = $this->serviceEntryKeys(array_values($previous->toArray()), $serviceVaultUuid);

        // Nothing to vault nor to purge: leave the vault untouched (not even a feature-flag probe), so a service
        // without password macros never reaches the vault at all.
        $hasPasswordMacro = array_any($macros, static fn (ServiceMacro $macro): bool => $macro->isPassword);
        if (! $hasPasswordMacro && $previousKeys === []) {
            return new SynchronizedServiceMacros(new Collection($macros, ServiceMacro::class), false);
        }

        if (! $this->vault->isEnabled()) {
            return new SynchronizedServiceMacros(new Collection($macros, ServiceMacro::class), false);
        }

        /** @var array<int, string> $toWrite plaintext indexed by position in $macros */
        $toWrite = [];
        /** @var array<string, true> $seenNames */
        $seenNames = [];
        foreach ($macros as $index => $macro) {
            // Duplicates are refused on write; a legacy service may still hold some: the first one wins.
            if (isset($seenNames[$macro->name->value])) {
                continue;
            }
            $seenNames[$macro->name->value] = true;
            if (! $macro->isPassword) {
                continue;
            }
            // An empty password is stored as is, nothing vaulted.
            if ($macro->value === '') {
                continue;
            }
            // Every secret is read here, before the single write below, so renames and swaps cannot overwrite a key
            // another macro still has to read.
            if ($this->isInServiceEntry($macro->value, $serviceVaultUuid) && $this->keyOf($macro->value) === $this->keyFor($macro)) {
                continue;
            }

            $toWrite[$index] = $this->vault->isVaultPath($macro->value)
                ? $this->vault->resolve($macro->value)
                : $macro->value;
        }

        $writtenKeys = [];
        foreach (array_keys($toWrite) as $index) {
            $writtenKeys[$this->keyFor($macros[$index])] = true;
        }
        $keptKeys = $this->serviceEntryKeys(array_values(array_diff_key($macros, $toWrite)), $serviceVaultUuid);

        $credentials = VaultCredentials::empty();
        foreach (array_keys($previousKeys) as $key) {
            if (! isset($writtenKeys[$key]) && ! isset($keptKeys[$key])) {
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

        // The same test persist() makes before reaching the vault.
        $vaultWritten = $credentials->toInsert() !== [] || $credentials->clearedKeys() !== [];

        return new SynchronizedServiceMacros(new Collection(array_values($macros), ServiceMacro::class), $vaultWritten);
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
