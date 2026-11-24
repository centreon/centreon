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

use App\AiConfiguration\Domain\Aggregate\AiProvider\AiProviderId;
use App\AiConfiguration\Domain\Aggregate\AiProvider\ApiToken;
use App\AiConfiguration\Domain\Aggregate\AiProvider\StoredApiToken;

/**
 * Port abstracting where AI provider API tokens are persisted: in the vault when it is enabled
 * (the database then only keeps a "secret::..." reference), in the database otherwise.
 */
interface AiProviderTokenStorage
{
    /**
     * Persist a token in an entry of its own and return the value to store in the database
     * (the token itself, or a vault path reference). No existing entry is rewritten, so the
     * token in use stays valid until the configuration referencing the new one is saved.
     *
     * @throws \Throwable when the token cannot be written to its storage
     */
    public function persist(ApiToken $token): StoredApiToken;

    /**
     * Remove a stored token from its storage (no-op for a token kept in the database).
     *
     * @throws \Throwable when the token cannot be removed from its storage
     */
    public function remove(StoredApiToken $token, AiProviderId $aiProviderId): void;

    /**
     * Remove a token that no saved configuration references (replaced, or never saved), on a
     * best-effort basis: a failure only leaves an unused entry behind, and never throws.
     */
    public function discard(StoredApiToken $token, ?AiProviderId $aiProviderId): void;
}
