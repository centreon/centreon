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

namespace App\AiConfiguration\Infrastructure\TokenStorage;

use App\AiConfiguration\Domain\Aggregate\AiProvider\ApiToken;
use App\AiConfiguration\Domain\Repository\AiProviderTokenStorage;
use App\Shared\Application\Vault\VaultCredentialWriter;
use App\Shared\Domain\Vault\VaultCredentials;
use App\Shared\Domain\Vault\VaultKeyEnum;
use App\Shared\Domain\Vault\VaultPathEnum;
use App\Shared\Domain\VaultInterface;

/**
 * Stores AI provider API tokens in the vault when it is enabled, and in the database otherwise.
 *
 * Each provider owns one vault entry holding its token: rotating the token rewrites that entry, so
 * the reference stored in the database does not change. With the vault disabled, the token is
 * kept as-is, like the other credentials of the platform.
 */
final readonly class VaultAiProviderTokenStorage implements AiProviderTokenStorage
{
    public function __construct(
        private VaultInterface $vault,
        private VaultCredentialWriter $vaultCredentialWriter,
    ) {
    }

    public function persist(ApiToken $token, ?ApiToken $previousToken): ApiToken
    {
        if (! $this->vault->isEnabled()) {
            return $token;
        }

        $uuid = $previousToken instanceof ApiToken && $previousToken->isStoredInVault()
            ? $this->vault->extractUuid($previousToken->value)
            : null;

        $stored = $this->vaultCredentialWriter->persist(
            VaultPathEnum::AiProviders,
            VaultCredentials::empty()->set(VaultKeyEnum::AiProviderApiToken, $token->value),
            $uuid,
        );

        return new ApiToken($stored[VaultKeyEnum::AiProviderApiToken->value]);
    }

    public function remove(ApiToken $token): void
    {
        if (! $token->isStoredInVault()) {
            // a plaintext token lives in the configuration row, deleted with it
            return;
        }

        $uuid = $this->vault->extractUuid($token->value);
        if ($uuid === null) {
            return;
        }

        $this->vaultCredentialWriter->delete(VaultPathEnum::AiProviders, $uuid);
    }
}
