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

namespace Core\Security\Token\Application\Repository;

use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Centreon\Infrastructure\RequestParameters\RequestParametersTranslatorException;
use Core\Security\Token\Domain\Model\Token;

interface ReadTokenRepositoryInterface
{
    /**
     * Find one token.
     *
     * @param string $tokenString
     *
     * @throws \Throwable
     *
     * @return Token|null
     */
    public function find(string $tokenString): ?Token;

    /**
     * Find a token exists by its name and user ID.
     *
     * @param string $tokenName
     * @param int $userId
     *
     * @throws \Throwable
     *
     * @return Token|null
     */
    public function findByNameAndUserId(string $tokenName, int $userId): ?Token;

    /**
     * Determine if a token exists by its name and user ID.
     *
     * @param int $userId
     * @param RequestParametersInterface $requestParameters
     *
     * @throws RequestParametersTranslatorException
     * @throws \Throwable
     *
     * @return list<Token>
     */
    public function findByIdAndRequestParameters(int $userId, RequestParametersInterface $requestParameters): array;

    /**
     * @param RequestParametersInterface $requestParameters
     *
     * @throws RequestParametersTranslatorException
     * @throws \Throwable
     *
     * @return list<Token>
     */
    public function findByRequestParameters(RequestParametersInterface $requestParameters): array;

    /**
     * Determine if a token exists by its name.
     *
     * @param string $tokenName
     * @param int $userId
     *
     * @throws \Throwable
     *
     * @return bool
     */
    public function existsByNameAndUserId(string $tokenName, int $userId): bool;

    /**
     * Check if the token type is manual.
     *
     * @param string $token
     *
     * @throws \Throwable
     *
     * @return bool
     */
    public function isTokenTypeManual(string $token): bool;
}
