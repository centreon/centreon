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

namespace App\Shared\Domain\Vault;

use App\Shared\Domain\Logging\Attribute\Sensitive;
use App\Shared\Domain\VaultInterface;

/**
 * Immutable set of secret credentials as a `name => value` map, each entry carrying a lifecycle
 * {@see VaultCredentialStateEnum} (set / cleared / unchanged).
 *
 * A key is either a well-known {@see VaultKeyEnum} or a dynamic string (e.g. a password-type macro
 * name). A value may be plaintext, an empty string, or already a vault reference (`secret::…`).
 * This is the single currency exchanged with {@see \App\Shared\Application\Vault\VaultCredentialWriter}
 * and {@see \App\Shared\Application\Vault\VaultCredentialReader}.
 *
 * Two ways to build it:
 * - {@see self::fromArray()} / {@see self::with()}: the create-shaped, value-convention builders. The
 *   state of each entry is derived from its value (empty => cleared, `secret::…` => unchanged, other
 *   => set).
 * - {@see self::empty()} + {@see self::set()} / {@see self::clear()} / {@see self::keep()}: the
 *   update-shaped builders where the caller states each entry's intent explicitly, so a PATCH/PUT
 *   consumer can tell a value it left untouched apart from one it emptied. A field the caller did
 *   not provide is simply not added to the map.
 *
 * The values may be plaintext secrets, so the whole object is masked when it flows through the
 * logging pipeline.
 */
#[Sensitive]
final readonly class VaultCredentials
{
    /**
     * @param array<string, string> $credentials name => value
     * @param array<string, VaultCredentialStateEnum> $states name => lifecycle state (same keys as $credentials)
     */
    private function __construct(
        private array $credentials,
        private array $states,
    ) {
    }

    /**
     * Value-convention builder: each entry's {@see VaultCredentialStateEnum} is derived from its value.
     *
     * @param array<string, string> $credentials name => value (plaintext, empty, or a vault path)
     */
    public static function fromArray(array $credentials): self
    {
        $states = [];
        foreach ($credentials as $name => $value) {
            $states[$name] = self::deriveState($value);
        }

        return new self($credentials, $states);
    }

    public static function empty(): self
    {
        return new self([], []);
    }

    /**
     * Return a copy with $key set to $value, its state derived from the value (value convention).
     */
    public function with(string|VaultKeyEnum $key, string $value): self
    {
        return $this->withEntry($key, $value, self::deriveState($value));
    }

    /**
     * Return a copy marking $key as a new plaintext value to write to the vault.
     */
    public function set(string|VaultKeyEnum $key, string $value): self
    {
        return $this->withEntry($key, $value, VaultCredentialStateEnum::Set);
    }

    /**
     * Return a copy marking $key as explicitly cleared: its key is deleted from the vault entry and
     * dropped from the map {@see \App\Shared\Application\Vault\VaultCredentialWriter::persist()} returns.
     */
    public function clear(string|VaultKeyEnum $key): self
    {
        return $this->withEntry($key, '', VaultCredentialStateEnum::Cleared);
    }

    /**
     * Return a copy marking $key as untouched: its stored value (plaintext or `secret::` reference)
     * is carried through as-is, never written to nor deleted from the vault.
     */
    public function keep(string|VaultKeyEnum $key, string $value): self
    {
        return $this->withEntry($key, $value, VaultCredentialStateEnum::Unchanged);
    }

    /**
     * Return a copy keeping only the values that must actually be written to the vault:
     * non-empty plaintext that is not already a vault reference.
     *
     * This is the single place the "skip empty / skip already-vaulted" rule lives.
     */
    public function plaintextOnly(): self
    {
        $credentials = [];
        $states = [];
        foreach ($this->credentials as $name => $value) {
            if ($value !== '' && ! str_starts_with($value, VaultInterface::VAULT_PATH_PREFIX)) {
                $credentials[$name] = $value;
                $states[$name] = $this->states[$name];
            }
        }

        return new self($credentials, $states);
    }

    /**
     * The entries to write to the vault: those marked {@see VaultCredentialStateEnum::Set} whose value is
     * a non-empty plaintext (not already a vault reference).
     *
     * @return array<string, string> name => plaintext value
     */
    public function toInsert(): array
    {
        $inserts = [];
        foreach ($this->credentials as $name => $value) {
            if (
                $this->states[$name] === VaultCredentialStateEnum::Set
                && $value !== ''
                && ! str_starts_with($value, VaultInterface::VAULT_PATH_PREFIX)
            ) {
                $inserts[$name] = $value;
            }
        }

        return $inserts;
    }

    /**
     * The keys marked {@see VaultCredentialStateEnum::Cleared}: to be deleted from the vault entry.
     *
     * @return list<string>
     */
    public function clearedKeys(): array
    {
        $keys = [];
        foreach ($this->states as $name => $state) {
            if ($state === VaultCredentialStateEnum::Cleared) {
                $keys[] = $name;
            }
        }

        return $keys;
    }

    public function isEmpty(): bool
    {
        return $this->credentials === [];
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return $this->credentials;
    }

    private function withEntry(string|VaultKeyEnum $key, string $value, VaultCredentialStateEnum $state): self
    {
        $name = $key instanceof VaultKeyEnum ? $key->value : $key;

        return new self(
            [...$this->credentials, $name => $value],
            [...$this->states, $name => $state],
        );
    }

    private static function deriveState(string $value): VaultCredentialStateEnum
    {
        if ($value === '') {
            return VaultCredentialStateEnum::Cleared;
        }

        if (str_starts_with($value, VaultInterface::VAULT_PATH_PREFIX)) {
            return VaultCredentialStateEnum::Unchanged;
        }

        return VaultCredentialStateEnum::Set;
    }
}
