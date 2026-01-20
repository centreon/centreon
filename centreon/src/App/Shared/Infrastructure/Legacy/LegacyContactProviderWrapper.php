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

namespace App\Shared\Infrastructure\Legacy;

use Centreon\Domain\Contact\ContactProvider;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Webmozart\Assert\Assert;

/**
 * @implements UserProviderInterface<UserInterface>
 */
final readonly class LegacyContactProviderWrapper implements UserProviderInterface
{
    private ContactProvider $legacyContactProvider;

    public function __construct(LegacyContainer $legacyContainer)
    {
        $legacyAuthenticator = $legacyContainer->get('contact.provider');
        Assert::isInstanceOf($legacyAuthenticator, ContactProvider::class);

        $this->legacyContactProvider = $legacyAuthenticator;
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        return $this->legacyContactProvider->refreshUser($user);
    }

    public function supportsClass(string $class): bool
    {
        return $this->legacyContactProvider->supportsClass($class);
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        return $this->legacyContactProvider->loadUserByIdentifier($identifier);
    }
}
