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

namespace App\MonitoringConfiguration\Infrastructure\Legacy;

use App\MonitoringConfiguration\Domain\Security\ServiceCategoryPermissionEnum;
use Centreon\Domain\Contact\Contact;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<value-of<ServiceCategoryPermissionEnum>, mixed>
 */
final class LegacyServiceCategoryPermissionVoter extends Voter
{
    /**
     * @var array<value-of<ServiceCategoryPermissionEnum>, string>
     */
    private const LEGACY_PERMISSION_MAP = [
        ServiceCategoryPermissionEnum::CanRead->value => Contact::ROLE_CONFIGURATION_SERVICES_CATEGORIES_READ,
        ServiceCategoryPermissionEnum::CanWrite->value => Contact::ROLE_CONFIGURATION_SERVICES_CATEGORIES_READ_WRITE,
    ];

    protected function supports(string $attribute, mixed $subject): bool
    {
        return ServiceCategoryPermissionEnum::tryFrom($attribute) !== null;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        if (! $user instanceof Contact) {
            $vote?->addReason('The user is not logged in.');

            return false;
        }

        if (! $user->hasTopologyRole(self::LEGACY_PERMISSION_MAP[$attribute])) {
            $vote?->addReason('The user has not the required topology role.');

            return false;
        }

        return true;
    }
}
