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

namespace App\Shared\Domain;

interface VaultInterface
{
    /**
     * Prefix identifying a value that is a vault reference rather than a plaintext secret.
     */
    public const VAULT_PATH_PREFIX = 'secret::';

    /**
     * Whether the vault is enabled and configured for the given feature flag
     * (the flag is enabled AND a vault configuration exists).
     *
     * When false, callers must treat secrets as plaintext and must not resolve
     * from or write to the vault.
     */
    public function isEnabled(string $featureFlag = 'vault'): bool;

    /**
     * @return array<string, mixed>
     */
    public function read(string $path): array;

    /**
     * Whether $value is a vault reference (starts with the `secret::` prefix) rather
     * than a plaintext secret.
     */
    public function isVaultPath(string $value): bool;

    /**
     * Resolve a single-value vault path to its plaintext secret.
     *
     * Returns $value unchanged when it is not a vault path. Otherwise the credential is
     * read from the vault and addressed by the trailing `::<key>` path segment (same
     * addressing as the underlying read).
     *
     * @throws \Throwable when the vault cannot be read, or the credential is missing
     */
    public function resolve(string $value): string;

    /**
     * Extract the vault entry UUID carried by a `secret::` reference, or null when $value is not
     * a vault path.
     *
     * An update reuses a resource's existing vault entry by extracting its UUID from any of the
     * resource's stored references and passing it back to {@see self::writeMany()}, so the new
     * secrets land under the same entry instead of minting a fresh one.
     *
     * example: 'secret::hashicorp_vault::monitoring/hosts/3f2a-…::_HOSTSNMPCOMMUNITY' => '3f2a-…'
     */
    public function extractUuid(string $value): ?string;

    /**
     * Store $value in the vault under a new credential and return its `secret::` path.
     *
     * A null $uuid mints a fresh vault entry (a new UUID); pass an existing UUID to add
     * $key to that same entry.
     *
     * @param string $customPath vault sub-path of the owning domain (e.g. 'configuration/broker')
     *
     * @throws \Throwable when the secret cannot be written
     */
    public function write(string $customPath, string $key, string $value, ?string $uuid = null): string;

    /**
     * Store several secrets under a single vault entry (one UUID) and return each key's
     * `secret::` path.
     *
     * A null $uuid mints a fresh vault entry; pass an existing UUID to add the keys to it.
     *
     * On an existing entry, $deletes removes the named keys from it in the same operation (used to
     * drop credentials the caller cleared on update); the deleted keys are absent from the returned
     * map. $deletes is a no-op on a fresh entry.
     *
     * @param string $customPath vault sub-path of the owning domain (e.g. 'monitoring/hosts')
     * @param array<string, string> $secrets key => plaintext value
     * @param list<string> $deletes keys to remove from the entry
     *
     * @throws \Throwable when a secret cannot be written
     *
     * @return array<string, string> key => `secret::` path
     */
    public function writeMany(string $customPath, array $secrets, ?string $uuid = null, array $deletes = []): array;

    /**
     * Delete a resource's entire vault entry (all its keys), addressed by its UUID under the owning
     * domain's sub-path. Used when the resource itself is deleted.
     *
     * @param string $customPath vault sub-path of the owning domain (e.g. 'monitoring/hosts')
     *
     * @throws \Throwable when the entry cannot be deleted
     */
    public function delete(string $customPath, string $uuid): void;
}
