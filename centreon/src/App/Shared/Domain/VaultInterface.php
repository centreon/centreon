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
    public const OPENID_CLIENT_ID_KEY = '_OPENID_CLIENT_ID';
    public const OPENID_CLIENT_SECRET_KEY = '_OPENID_CLIENT_SECRET';

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
}
