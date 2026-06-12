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

use App\AiConfiguration\Domain\Aggregate\AiProvider\ApiToken;
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

    public function persist(ApiToken $token, ?ApiToken $previousToken): ApiToken
    {
        $this->persistedTokens[] = $token->value;

        return new ApiToken(self::STORED_PREFIX . $token->value);
    }

    public function remove(ApiToken $token): void
    {
        $this->removedTokens[] = $token->value;
    }
}
