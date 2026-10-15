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

namespace App\MonitoringConfiguration\Infrastructure\Security;

use App\MonitoringConfiguration\Domain\Security\ContactGroupPermissionEnum;
use App\Security\Domain\Aggregate\Permission;
use App\Security\Infrastructure\Security\CredentialUser;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<value-of<ContactGroupPermissionEnum>, mixed>
 */
final class ContactGroupPermissionVoter extends Voter
{
    public function __construct(
        #[Autowire(env: 'bool:default::IS_CLOUD_PLATFORM')]
        private readonly bool $isCloudPlatform = false,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return ContactGroupPermissionEnum::tryFrom($attribute) !== null;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        if (! $user instanceof CredentialUser) {
            $vote?->addReason('The user is not logged in.');

            return false;
        }

        // On Cloud the contact-group configuration pages don't exist, so no user carries the
        // matching topology permission; the legacy endpoint nonetheless lets any authenticated
        // user read contact groups (the provider still scopes the rows to what they may see).
        if ($this->isCloudPlatform) {
            return true;
        }

        if (! $user->credential->isPermissionGranted(new Permission($attribute))) {
            $vote?->addReason('The user has not the required permission.');

            return false;
        }

        return true;
    }
}
