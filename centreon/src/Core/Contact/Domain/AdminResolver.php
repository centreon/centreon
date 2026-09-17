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

namespace Core\Contact\Domain;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

/**
 * This service allows to determine if a contact is admin or not.
 * It would probably be better to add this logic to the Contact model but
 * for now we keep it that way to avoid touching too many things.
 */
class AdminResolver
{
    public function __construct(
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly bool $isCloudPlatform,
    ) {
    }

    public function isAdmin(ContactInterface $contact): bool
    {
        if ($contact->isAdmin()) {
            return true;
        }

        if (! $this->isCloudPlatform) {
            return false;
        }

        // In cloud platform, check if user has customer_admin_acl
        $accessGroups = $this->readAccessGroupRepository->findByContact($contact);
        $accessGroupNames = array_map(
            fn (AccessGroup $accessGroup): string => $accessGroup->getName(),
            $accessGroups
        );

        return in_array('customer_admin_acl', $accessGroupNames, true);
    }
}
