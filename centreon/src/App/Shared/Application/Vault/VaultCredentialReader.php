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
use App\Shared\Domain\VaultInterface;

/**
 * Resolves a resource's stored credentials back to plaintext: every value that is a vault
 * reference (`secret::…`) is read from the vault, every other value is returned untouched.
 *
 * Symmetric counterpart of {@see VaultCredentialWriter}; callers gate on
 * {@see VaultInterface::isEnabled()} before use.
 */
final readonly class VaultCredentialReader
{
    public function __construct(private VaultInterface $vault)
    {
    }

    public function resolveAll(VaultCredentials $credentials): VaultCredentials
    {
        $resolved = [];

        foreach ($credentials->toArray() as $name => $value) {
            $resolved[$name] = $this->vault->isVaultPath($value)
                ? $this->vault->resolve($value)
                : $value;
        }

        return VaultCredentials::fromArray($resolved);
    }
}
