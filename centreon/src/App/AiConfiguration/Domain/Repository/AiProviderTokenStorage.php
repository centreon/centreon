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

namespace App\AiConfiguration\Domain\Repository;

use App\AiConfiguration\Domain\Aggregate\AiProvider\ApiToken;

/**
 * Port abstracting where AI provider API tokens are persisted.
 *
 * The default implementation keeps the token as-is (stored in the database).
 * A vault-backed implementation can store the secret in HashiCorp Vault and
 * return a vault path reference instead.
 */
interface AiProviderTokenStorage
{
    /**
     * Persist a token and return the value to store in the database
     * (the token itself, or a vault path reference).
     *
     * @param ApiToken $token the new token provided by the user
     * @param ApiToken|null $previousToken the currently stored token, if any
     */
    public function persist(ApiToken $token, ?ApiToken $previousToken): ApiToken;

    /**
     * Remove a stored token from its storage (no-op for database storage).
     */
    public function remove(ApiToken $token): void;
}
