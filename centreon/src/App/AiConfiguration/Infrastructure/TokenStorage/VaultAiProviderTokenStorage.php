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
use App\AiConfiguration\Domain\Aggregate\AiProvider\StoredApiToken;
use App\Shared\Application\Vault\VaultCredentialWriter;
use App\Shared\Domain\Vault\VaultCredentials;
use App\Shared\Domain\Vault\VaultKeyEnum;
use App\Shared\Domain\Vault\VaultPathEnum;
use App\Shared\Domain\VaultInterface;

/**
 * Stores AI provider API tokens in the vault when it is enabled, and in the database otherwise.
 *
 * Each token gets a vault entry of its own: rotating it writes a new entry, and the replaced one
 * is removed once the configuration references the new one. With the vault disabled, the token
 * is kept as-is, like the other credentials of the platform.
 *
 * Every failure is thrown: LoggingAiProviderTokenStorage reports the ones the
 * configuration can live with.
 */
final readonly class VaultAiProviderTokenStorage
{
    public function __construct(
        private VaultInterface $vault,
        private VaultCredentialWriter $vaultCredentialWriter,
    ) {
    }

    /**
     * @throws \Throwable when the token cannot be written to the vault
     */
    public function persist(ApiToken $token): StoredApiToken
    {
        if (! $this->vault->isEnabled()) {
            return StoredApiToken::plain($token);
        }

        $stored = $this->vaultCredentialWriter->persist(
            VaultPathEnum::AiProviders,
            VaultCredentials::empty()->set(VaultKeyEnum::AiProviderApiToken, $token->value),
        );

        return StoredApiToken::vaultReference($stored[VaultKeyEnum::AiProviderApiToken->value]);
    }

    /**
     * Remove the vault entry of a token (no-op for a token kept in the database).
     *
     * @throws UnparsableVaultReferenceException when the vault reference can not be read
     * @throws \Throwable when the entry cannot be deleted
     */
    public function remove(StoredApiToken $token): void
    {
        if (! $token->isVaultReference()) {
            // a plaintext token lives in the configuration row
            return;
        }

        $uuid = $this->vault->extractUuid($token->value) ?? throw new UnparsableVaultReferenceException();
        $this->vaultCredentialWriter->delete(VaultPathEnum::AiProviders, $uuid);
    }
}
