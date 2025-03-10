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

namespace Core\HostGroup\Infrastructure\Voters;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, mixed>
 */
final class HostGroupVoters extends Voter
{
    public const HOSTGROUP_DELETE = 'hostgroup_delete';
    public const HOSTGROUP_ADD = 'hostgroup_add';
    public const HOSTGROUP_ENABLE_DISABLE = 'hostgroup_enable_disable';
    public const HOSTGROUP_DUPLICATE = 'hostgroup_duplicate';
    private const ALLOWED_ATTRIBUTES = [
        self::HOSTGROUP_ADD,
        self::HOSTGROUP_DELETE,
        self::HOSTGROUP_ENABLE_DISABLE,
        self::HOSTGROUP_DUPLICATE,
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

        return match ($attribute) {
            self::HOSTGROUP_DELETE,
            self::HOSTGROUP_ADD,
            self::HOSTGROUP_ENABLE_DISABLE,
            self::HOSTGROUP_DUPLICATE => $this->checkUserRights($user),
            default => false,
        };
    }

    /**
     * Check that user has rights to perform write operations on host groups.
     *
     * @param ContactInterface $user
     *
     * @return bool
     */
    private function checkUserRights(ContactInterface $user): bool
    {
        return $user->hasTopologyRole(Contact::ROLE_CONFIGURATION_HOSTS_HOST_GROUPS_READ_WRITE);
    }
}
