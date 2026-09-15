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

use App\Shared\Domain\VaultInterface;

/**
 * Immutable set of secret credentials as a `name => value` map.
 *
 * A key is either a well-known {@see VaultKeyEnum} or a dynamic string (e.g. a password-type macro
 * name). A value may be plaintext, an empty string, or already a vault reference (`secret::…`).
 * This is the single currency exchanged with {@see \App\Shared\Application\Vault\VaultCredentialWriter}
 * and {@see \App\Shared\Application\Vault\VaultCredentialReader}.
 */
final readonly class VaultCredentials
{
    /**
     * @param array<string, string> $credentials name => value
     */
    private function __construct(private array $credentials)
    {
    }

    /**
     * @param array<string, string> $credentials name => value (plaintext, empty, or a vault path)
     */
    public static function fromArray(array $credentials): self
    {
        return new self($credentials);
    }

    /**
     * Return a copy with $key set to $value (replacing any existing entry for that key).
     */
    public function with(string|VaultKeyEnum $key, string $value): self
    {
        $name = $key instanceof VaultKeyEnum ? $key->value : $key;

        return new self([...$this->credentials, $name => $value]);
    }

    /**
     * Return a copy keeping only the values that must actually be written to the vault:
     * non-empty plaintext that is not already a vault reference.
     *
     * This is the single place the "skip empty / skip already-vaulted" rule lives.
     */
    public function plaintextOnly(): self
    {
        $filtered = array_filter(
            $this->credentials,
            static fn (string $value): bool => $value !== ''
                && ! str_starts_with($value, VaultInterface::VAULT_PATH_PREFIX),
        );

        return new self($filtered);
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
}
