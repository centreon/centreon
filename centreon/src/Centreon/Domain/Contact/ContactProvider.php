<?php
/*
 * Copyright 2005 - 2019 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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

namespace Centreon\Domain\Contact;

use Centreon\Domain\Contact\Interfaces\ContactRepositoryInterface;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * @package Centreon
 */
class ContactProvider implements UserProviderInterface
{
    /**
     * ContactProvider constructor.
     *
     * @param ContactRepositoryInterface $contactRepository
     */
    public function __construct(private ContactRepositoryInterface $contactRepository)
    {
    }

    /**
     * Loads the user for the given username.
     *
     * This method must throw UsernameNotFoundException if the user is not
     * found.
     *
     * @param string $username The username
     *
     * @return UserInterface
     *
     * @throws UserNotFoundException if the user is not found
     * @throws \Exception
     */
    public function loadUserByUsername(string $username): UserInterface
    {
        $contact = $this->contactRepository->findByName($username);
        if (is_null($contact)) {
            throw new UserNotFoundException();
        }
        return $contact;
    }

    /**
     * @param string $identifier
     *
     * @throws \Exception
     * @return UserInterface
     */
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $contact = $this->contactRepository->findByName($identifier);
        if (is_null($contact)) {
            throw new UserNotFoundException();
        }
        return $contact;
    }

    /**
     * Refreshes the user.
     *
     * It is up to the implementation to decide if the user data should be
     * totally reloaded (e.g. from the database), or if the UserInterface
     * object can just be merged into some internal array of users / identity
     * map.
     *
     * @param UserInterface $user
     * @return UserInterface
     *
     */
    public function refreshUser(UserInterface $user): UserInterface
    {
        return $user;
    }

    /**
     * Whether this provider supports the given user class.
     *
     * @param string $class
     *
     * @return bool
     */
    public function supportsClass(string $class): bool
    {
        return $class === Contact::class;
    }
}
