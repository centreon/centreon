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

namespace Core\Security\Token\Application\UseCase\AddToken;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Contact\Application\Repository\ReadContactRepositoryInterface;
use Core\Security\Token\Application\Exception\TokenException;
use Core\Security\Token\Application\Repository\ReadTokenRepositoryInterface;

class AddTokenValidation
{
    use LoggerTrait;

    public function __construct(
        private readonly ReadTokenRepositoryInterface $readTokenRepository,
        private readonly ReadContactRepositoryInterface $readContactRepository,
        private readonly ContactInterface $user
    ) {
    }

    /**
     * Assert name is not already used.
     *
     * @param string $name
     * @param int $userId
     *
     * @throws TokenException|\Throwable
     */
    public function assertIsValidName(string $name, int $userId): void
    {
        $trimmedName = trim($name);
        if ($this->readTokenRepository->existsByNameANdUserId($trimmedName, $userId)) {
            $this->error('Token name already exists', ['name' => $trimmedName, 'userId' => $userId]);

            throw TokenException::nameAlreadyExists($trimmedName);
        }
    }

    /**
     * Assert user id is valid.
     *
     * @param int $userId
     *
     * @throws TokenException|\Throwable
     */
    public function assertIsValidUser(int $userId): void
    {
        if (! $this->user->isAdmin() && $this->user->getId() !== $userId && ! $this->user->hasRole(Contact::ROLE_MANAGE_TOKENS))
        {
            throw TokenException::notAllowedToCreateTokenForUser($userId);
        }
        if (false === $this->readContactRepository->exists($userId)) {
            throw TokenException::invalidUserId($userId);
        }
    }
}
