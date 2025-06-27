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

namespace Core\Security\Token\Infrastructure\Voters;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, mixed>
 */
final class TokenVoters extends Voter
{
    public const TOKEN_ADD = 'token_add';
    public const TOKEN_LIST = 'token_list';
    public const ALLOWED_ATTRIBUTES = [
        self::TOKEN_ADD,
        self::TOKEN_LIST,
    ];

    /**
     * @inheritDoc
     */
    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, self::ALLOWED_ATTRIBUTES, true);
    }

    /**
     * @inheritDoc
     */
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (! $user instanceof ContactInterface) {
            return false;
        }

        if (
            $subject !== null
            && $subject !== $user->getId()
            && ! $this->canAccessOthersTokens($user, $subject)
        ) {
            return false;
        }

        return match ($attribute) {
            self::TOKEN_ADD, self::TOKEN_LIST => $this->checkUserRights($user),
            default => false,
        };
    }

    /**
     * Check that user has rights to perform write operations on tokens.
     *
     * @param ContactInterface $user
     *
     * @return bool
     */
    private function checkUserRights(ContactInterface $user): bool
    {
        return $user->hasTopologyRole(Contact::ROLE_ADMINISTRATION_AUTHENTICATION_TOKENS_RW);
    }

    /**
     * Check that current user has rights to access other users' tokens.
     *
     * @param ContactInterface $user
     * @param mixed $userId
     *
     * @return bool
     */
    private function canAccessOthersTokens(ContactInterface $user, mixed $userId): bool
    {
        return
            $user->isAdmin()
            || $user->hasRole(Contact::ROLE_MANAGE_TOKENS);
    }
}
