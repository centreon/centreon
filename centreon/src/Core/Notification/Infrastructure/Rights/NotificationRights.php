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

namespace Core\Notification\Infrastructure\Rights;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Notification\Application\Rights\NotificationRightsInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;

/**
 * Helps to manage ACLs for ON-PREM + SAAS for the notifications.
 */
final class NotificationRights implements NotificationRightsInterface
{
    private const CUSTOMER_ADMIN_ACL = 'customer_admin_acl';
    private const CUSTOMER_EDITOR_ACL = 'customer_editor_acl';

    /** @var array<int, array<string>> */
    private array $accessGroupNamesByContactId = [];

    public function __construct(
        private readonly ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private readonly bool $isCloudPlatform,
    ) {
    }

    public function isAdmin(ContactInterface $contact): bool
    {
        if ($this->isCloudPlatform) {
            return \in_array(
                self::CUSTOMER_ADMIN_ACL,
                $this->getContactAccessGroupNames($contact),
                true
            );
        }

        return $contact->isAdmin();
    }

    public function isEditor(ContactInterface $contact): bool
    {
        if ($this->isCloudPlatform) {
            return \in_array(
                self::CUSTOMER_EDITOR_ACL,
                $this->getContactAccessGroupNames($contact),
                true
            );
        }

        return $contact->isAdmin();
    }

    /**
     * @param ContactInterface $contact
     *
     * @return array<string>
     */
    private function getContactAccessGroupNames(ContactInterface $contact): array
    {
        return $this->accessGroupNamesByContactId[$contact->getId()] ??= array_map(
            static fn(AccessGroup $accessGroup) => $accessGroup->getName(),
            $this->readAccessGroupRepository->findByContact($contact)
        );
    }
}
