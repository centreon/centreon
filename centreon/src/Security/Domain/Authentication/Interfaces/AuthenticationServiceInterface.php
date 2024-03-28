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

namespace Security\Domain\Authentication\Interfaces;

use Centreon\Domain\Authentication\Exception\AuthenticationException;
use Core\Security\Authentication\Domain\Model\AuthenticationTokens;
use Security\Domain\Authentication\Exceptions\ProviderException;

interface AuthenticationServiceInterface
{
    /**
     * Check authentication token.
     *
     * @param string $token
     *
     * @throws ProviderException
     * @throws AuthenticationException
     *
     * @return bool
     */
    public function isValidToken(string $token): bool;

    /**
     * Delete a session.
     *
     * @param string $sessionToken
     *
     * @throws AuthenticationException
     */
    public function deleteSession(string $sessionToken): void;

    /**
     * @param AuthenticationTokens $authenticationToken
     *
     * @throws AuthenticationException
     */
    public function updateAuthenticationTokens(AuthenticationTokens $authenticationToken): void;

    /**
     * @param string $token
     *
     * @throws AuthenticationException
     *
     * @return AuthenticationTokens|null
     */
    public function findAuthenticationTokensByToken(string $token): ?AuthenticationTokens;
}
