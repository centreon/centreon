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

namespace Core\Common\Infrastructure;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Common\Application\CurrentUserIdResolverInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * The API authenticates the user through the Symfony firewall, which fills the token storage.
 * Legacy pages do not go through that firewall: their user only lives in the PHP session.
 */
final readonly class CurrentUserIdResolver implements CurrentUserIdResolverInterface
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
    ) {
    }

    public function getUserId(): ?int
    {
        $user = $this->tokenStorage->getToken()?->getUser();
        if ($user instanceof ContactInterface) {
            return $user->getId();
        }

        $legacyUser = $_SESSION['centreon']->user ?? null;

        return $legacyUser instanceof \CentreonUser ? (int) $legacyUser->get_id() : null;
    }
}
