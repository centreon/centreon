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

namespace App\Shared\Application\Vault;

use App\Shared\Domain\Vault\VaultCredentials;
use App\Shared\Domain\Vault\VaultPathEnum;
use App\Shared\Domain\VaultInterface;

/**
 * Persists a resource's sensitive credentials to the vault under a single entry and returns the
 * `name => value` map to store on the resource: the freshly-minted `secret::` path for each
 * vaulted credential, the original value untouched for the rest (empty or already a vault path).
 *
 * This service is policy-free: callers MUST first check {@see VaultInterface::isEnabled()} for
 * their own feature flag and, when the vault is disabled, persist the plaintext values directly
 * without calling this writer.
 */
final readonly class VaultCredentialWriter
{
    public function __construct(private VaultInterface $vault)
    {
    }

    /**
     * @param VaultPathEnum $path the owning domain's vault sub-path
     * @param VaultCredentials $credentials the resource's credentials (plaintext, empty, or paths)
     * @param string|null $uuid null mints a fresh vault entry; an existing UUID adds to that entry
     *
     * @throws \Throwable when a credential cannot be written
     *
     * @return array<string, string> name => value to persist on the resource
     */
    public function persist(VaultPathEnum $path, VaultCredentials $credentials, ?string $uuid = null): array
    {
        $toVault = $credentials->plaintextOnly();

        if ($toVault->isEmpty()) {
            return $credentials->toArray();
        }

        $paths = $this->vault->writeMany($path->value, $toVault->toArray(), $uuid);

        return array_merge($credentials->toArray(), $paths);
    }
}
