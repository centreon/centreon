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

namespace Tests\App\Shared\Infrastructure\Logging\Attribute\Fake;

use App\Shared\Domain\Logging\Attribute\Sensitive;

/**
 * Pins the TARGET_METHOD path: a `#[Sensitive]` accessor masks the
 * snake_cased key it exposes (`getApiToken` → `api_token`,
 * `getSsoTicket` → `sso_ticket`, `canManageUsers` → `manage_users`).
 */
final readonly class MethodSecretCommand
{
    public function __construct(
        private string $apiToken,
        public string $login,
    ) {
    }

    #[Sensitive]
    public function getApiToken(): string
    {
        return $this->apiToken;
    }

    #[Sensitive]
    public function getSsoTicket(): string
    {
        return 'st-secret';
    }

    #[Sensitive]
    public function canManageUsers(): bool
    {
        return true;
    }
}
