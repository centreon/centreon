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

namespace Tests\App\AiConfiguration\Infrastructure\Double;

use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderId;
use App\AiConfiguration\Domain\Aggregate\AiProvider\ApiToken;
use App\AiConfiguration\Domain\Aggregate\AiProvider\StoredApiToken;
use App\AiConfiguration\Domain\Repository\AiProviderTokenStorage;

/**
 * Marks persisted tokens with a prefix so tests can verify the storage was used.
 */
final class FakeAiProviderTokenStorage implements AiProviderTokenStorage
{
    public const STORED_PREFIX = 'stored::';

    /** @var list<string> */
    public array $persistedTokens = [];

    /** @var list<string> */
    public array $removedTokens = [];

    /** @var list<string> */
    public array $discardedTokens = [];

    public bool $persistThrows = false;

    public bool $removeThrows = false;

    public function persist(ApiToken $token): StoredApiToken
    {
        if ($this->persistThrows) {
            throw new \RuntimeException('Unable to write the token to the vault');
        }

        $this->persistedTokens[] = $token->value;

        return new StoredApiToken(self::STORED_PREFIX . $token->value);
    }

    public function remove(StoredApiToken $token, AiProviderId $aiProviderId): void
    {
        if ($this->removeThrows) {
            throw new \RuntimeException('Unable to remove the token from the vault');
        }

        $this->removedTokens[] = $token->value;
    }

    public function discard(StoredApiToken $token, ?AiProviderId $aiProviderId): void
    {
        $this->discardedTokens[] = $token->value;
    }
}
