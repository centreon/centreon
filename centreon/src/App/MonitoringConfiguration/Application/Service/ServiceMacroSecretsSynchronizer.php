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
 * Moves the password macros of a service being created to the vault before the service is persisted: they share
 * one new entry under `monitoring/services`, and each macro keeps the `secret::` reference to its key.
 *
 * - a plaintext value is written to the new entry;
 * - a reference to another entry has its secret copied under the new entry, never shared, so editing or deleting
 *   the template cannot change or purge the service's secret. The only such references are those
 *   ServiceMacroChangesResolver carries over when an inherited password is promoted with its stored value kept:
 *   the API refuses any submitted reference;
 * - an empty value is stored as is, nothing vaulted.
 *
 * Vault keys follow legacy: `_SERVICE<NAME>`, without the `$...$` wrapper. A service holds one macro per name
 * (duplicates are refused before), so each key is claimed once.
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
     * @param list<ServiceMacro> $macros the own macros of the service being created, values in raw form
     *
     * @return list<ServiceMacro> $macros with every vaulted password replaced by its reference
     */
    public function synchronize(array $macros): array
    {
        // Nothing to vault: leave the vault untouched (not even a feature-flag probe), so a service without
        // password macros never reaches the vault at all.
        if (! array_any($macros, static fn (ServiceMacro $macro): bool => $macro->isPassword)) {
            return $macros;
        }

        if (! $this->vault->isEnabled()) {
            return $macros;
        }

        /** @var array<int, string> $toWrite plaintext indexed by position in $macros */
        $toWrite = [];
        foreach ($macros as $index => $macro) {
            if ($macro->isPassword && $macro->value !== '') {
                $toWrite[$index] = $this->vault->isVaultPath($macro->value)
                    ? $this->vault->resolve($macro->value)
                    : $macro->value;
            }
        }

        if ($toWrite === []) {
            return $macros;
        }

        $credentials = VaultCredentials::empty();
        foreach ($toWrite as $index => $plaintext) {
            $credentials = $credentials->set($this->keyFor($macros[$index]), $plaintext);
        }

        $stored = $this->vaultCredentialWriter->persist(VaultPathEnum::MonitoringServices, $credentials);

        foreach (array_keys($toWrite) as $index) {
            $macro = $macros[$index];
            $macros[$index] = $macro->withValue($stored[$this->keyFor($macro)], true);
        }

        return array_values($macros);
    }

    private function keyFor(ServiceMacro $macro): string
    {
        return self::KEY_PREFIX . $macro->name->value;
    }
}
