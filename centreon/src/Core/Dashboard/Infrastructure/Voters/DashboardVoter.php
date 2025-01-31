<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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

namespace Core\Dashboard\Infrastructure\Voters;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, mixed>
 */
final class DashboardVoter extends Voter
{
    public const DASHBOARD_ACCESS = 'dashboard_access';
    public const DASHBOARD_ACCESS_EDITOR = 'dashboard_access_editor';

    /**
     * {@inheritDoc}
     */
    protected function supports(string $attribute, mixed $subject): bool
    {
        if (
            $attribute === self::DASHBOARD_ACCESS
            || $attribute === self::DASHBOARD_ACCESS_EDITOR
        ) {
            return $subject === null;
        }

        return false;
    }

    /**
     * {@inheritDoc}
     */
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (! $user instanceof ContactInterface) {
            return false;
        }

        return match ($attribute) {
            self::DASHBOARD_ACCESS => $this->checkUsersRights($user),
            self::DASHBOARD_ACCESS_EDITOR => $this->checkEditorsRights($user),
            default => throw new \LogicException('Action on dashboard not handled')
        };
    }

    /**
     * @param ContactInterface $user
     * @return bool
     */
    private function checkUsersRights(ContactInterface $user): bool
    {
        return (bool) (
            $user->hasTopologyRole(Contact::ROLE_HOME_DASHBOARD_ADMIN)
            || $user->hasTopologyRole(Contact::ROLE_HOME_DASHBOARD_CREATOR)
            || $user->hasTopologyRole(Contact::ROLE_HOME_DASHBOARD_VIEWER)
        );
    }

    /**
     * @param ContactInterface $user
     * @return bool
     */
    private function checkEditorsRights(ContactInterface $user): bool
    {
        return (bool) (
            $user->hasTopologyRole(Contact::ROLE_HOME_DASHBOARD_ADMIN)
            || $user->hasTopologyRole(Contact::ROLE_HOME_DASHBOARD_CREATOR)
        );
    }
}
