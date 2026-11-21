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

namespace App\AiConfiguration\Domain\Aggregate\AiProvider;

use App\Shared\Domain\Logging\Attribute\Sensitive;
use Webmozart\Assert\Assert;

/**
 * API token of an AI provider as it is persisted: the plain token, or a
 * reference ("secret::...") to the vault entry holding it. Only the token
 * storage creates a reference, and a plain token can not be one (see
 * ApiToken), so a reference always designates the provider's own entry.
 */
#[Sensitive]
final readonly class StoredApiToken
{
    public const VAULT_REFERENCE_PREFIX = 'secret::';

    public function __construct(
        public string $value,
    ) {
        Assert::lengthBetween($value, 1, 4096);
    }

    public static function plain(ApiToken $apiToken): self
    {
        return new self($apiToken->value);
    }

    public static function vaultReference(string $reference): self
    {
        Assert::startsWith($reference, self::VAULT_REFERENCE_PREFIX, 'A vault reference must start with "secret::".');

        return new self($reference);
    }

    public function isVaultReference(): bool
    {
        return str_starts_with($this->value, self::VAULT_REFERENCE_PREFIX);
    }

    /**
     * @throws \InvalidArgumentException when the token is a vault reference, to read from the vault instead
     */
    public function plainToken(): ApiToken
    {
        Assert::false($this->isVaultReference(), 'A vault reference must be read from the vault.');

        return new ApiToken($this->value);
    }
}
