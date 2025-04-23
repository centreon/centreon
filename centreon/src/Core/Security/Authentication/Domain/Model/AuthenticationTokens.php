<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

namespace Core\Security\Authentication\Domain\Model;

class AuthenticationTokens
{
    /**
     * @param int $userId
     * @param int $configurationProviderId
     * @param string $sessionToken
     * @param NewProviderToken|ProviderToken $providerToken
     * @param NewProviderToken|ProviderToken|null $providerRefreshToken
     */
    public function __construct(
        private int $userId,
        private int $configurationProviderId,
        private string $sessionToken,
        private NewProviderToken|ProviderToken $providerToken,
        private NewProviderToken|ProviderToken|null $providerRefreshToken,
    ) {}

    /**
     * @return string
     */
    public function getSessionToken(): string
    {
        return $this->sessionToken;
    }

    /**
     * @return ProviderToken|NewProviderToken
     */
    public function getProviderToken(): ProviderToken|NewProviderToken
    {
        return $this->providerToken;
    }

    /**
     * @return ProviderToken|NewProviderToken|null
     */
    public function getProviderRefreshToken(): ProviderToken|NewProviderToken|null
    {
        return $this->providerRefreshToken;
    }

    /**
     * @return int
     */
    public function getUserId(): int
    {
        return $this->userId;
    }

    /**
     * @return int
     */
    public function getConfigurationProviderId(): int
    {
        return $this->configurationProviderId;
    }
}
