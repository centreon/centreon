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

use App\MonitoringConfiguration\Domain\Aggregate\HostMacro;
use App\Shared\Application\Vault\VaultCredentialWriter;
use App\Shared\Domain\Vault\VaultCredentials;
use App\Shared\Domain\Vault\VaultPathEnum;
use App\Shared\Domain\VaultInterface;

/**
 * Keeps a template's password macros in line with its own vault entry before the template is
 * persisted: every password macro is stored as a reference under the template's entry (the single
 * UUID it shares with its SNMP community), never under another template's or a host's.
 *
 * - a plaintext value is written to the template's entry;
 * - a reference to another entry (an inherited password a write copied) has its secret copied under
 *   the template's entry, never shared;
 * - a reference already in the template's entry is kept;
 * - a key the template no longer references is cleared from its entry.
 */
final readonly class HostTemplateMacroSecretsSynchronizer
{
    private const KEY_PREFIX = '_HOST';

    public function __construct(
        private VaultInterface $vault,
        private VaultCredentialWriter $vaultCredentialWriter,
    ) {
    }

    /**
     * @param list<HostMacro> $macros the macros the template will own, values in raw stored form
     * @param list<HostMacro> $previous the macros the template owned so far (empty on creation)
     * @param ?string $templateVaultUuid the template's vault entry, null when it has none yet (one is minted
     *                                   if a secret must be written)
     *
     * @return list<HostMacro> $macros with every password value to store
     */
    public function synchronize(array $macros, array $previous, ?string $templateVaultUuid): array
    {
        $previousKeys = $this->templateEntryKeys($previous, $templateVaultUuid);

        // Nothing to vault nor to purge: the vault is not reached at all.
        $hasPasswordMacro = array_any($macros, static fn (HostMacro $macro): bool => $macro->isPassword);
        if (! $hasPasswordMacro && $previousKeys === []) {
            return $macros;
        }

        if (! $this->vault->isEnabled()) {
            return $macros;
        }

        /** @var array<int, string> $toWrite plaintext indexed by position in $macros */
        $toWrite = [];
        /** @var array<string, true> $keptKeys keys of the template's entry still referenced as is */
        $keptKeys = [];
        /** @var array<string, int> $firstIndexByName */
        $firstIndexByName = [];
        foreach ($macros as $index => $macro) {
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

            if ($this->isInTemplateEntry($macro->value, $templateVaultUuid)) {
                $keptKeys[$this->keyOf($macro->value)] = true;

                continue;
            }

            $toWrite[$index] = $this->vault->isVaultPath($macro->value)
                ? $this->vault->resolve($macro->value)
                : $macro->value;
        }

        // A renamed macro keeps its reference, so its key may be the name another macro now
        // claims: that one would overwrite the secret the renamed macro still points to. Move the
        // renamed macro under its own name instead. Moving it claims a new key, which may in turn be
        // the one another renamed macro points to (chained renames): repeat until no key is claimed.
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
                    && $this->isInTemplateEntry($macro->value, $templateVaultUuid)
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
            if (! isset($claimedKeys[$key]) && ! $this->isKeptBy($key, $macros, $templateVaultUuid, $toWrite)) {
                $credentials = $credentials->clear($key);
            }
        }
        foreach ($toWrite as $index => $plaintext) {
            $credentials = $credentials->set($this->keyFor($macros[$index]), $plaintext);
        }

        $stored = $this->vaultCredentialWriter->persist(VaultPathEnum::MonitoringHosts, $credentials, $templateVaultUuid);

        foreach (array_keys($toWrite) as $index) {
            $macro = $macros[$index];
            $macros[$index] = $macro->withValue($stored[$this->keyFor($macro)], true);
        }

        return array_values($macros);
    }

    /**
     * @param list<HostMacro> $macros
     *
     * @return array<string, true> the keys of the template's entry the macros reference
     */
    private function templateEntryKeys(array $macros, ?string $templateVaultUuid): array
    {
        $keys = [];
        foreach ($macros as $macro) {
            if ($macro->isPassword && $this->isInTemplateEntry($macro->value, $templateVaultUuid)) {
                $keys[$this->keyOf($macro->value)] = true;
            }
        }

        return $keys;
    }

    /**
     * @param list<HostMacro> $macros
     * @param array<int, string> $toWrite
     */
    private function isKeptBy(string $key, array $macros, ?string $templateVaultUuid, array $toWrite): bool
    {
        foreach ($macros as $index => $macro) {
            if (
                ! isset($toWrite[$index])
                && $macro->isPassword
                && $this->isInTemplateEntry($macro->value, $templateVaultUuid)
                && $this->keyOf($macro->value) === $key
            ) {
                return true;
            }
        }

        return false;
    }

    private function isInTemplateEntry(string $value, ?string $templateVaultUuid): bool
    {
        return $templateVaultUuid !== null
            && $this->vault->isVaultPath($value)
            && $this->vault->extractUuid($value) === $templateVaultUuid;
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
