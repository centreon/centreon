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

namespace Core\Infrastructure\Configuration\NotificationPolicy\Repository;

use Centreon\Infrastructure\DatabaseConnection;
use Core\Application\Configuration\Notification\Repository\ReadHostNotificationRepositoryInterface;
use Core\Domain\Configuration\Notification\Model\NotifiedContact;
use Core\Domain\Configuration\Notification\Model\NotifiedContactGroup;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;
use Pimple\Container;

class LegacyReadHostNotificationRepository extends AbstractDbReadNotificationRepository implements ReadHostNotificationRepositoryInterface
{
    /** @var array<int,NotifiedContact[]> */
    private array $notifiedContacts = [];

    /** @var array<int,NotifiedContactGroup[]> */
    private array $notifiedContactGroups = [];

    /**
     * @param DatabaseConnection $db
     * @param Container $dependencyInjector
     */
    public function __construct(
        DatabaseConnection $db,
        private Container $dependencyInjector,
    ) {
        $this->db = $db;
    }

    /**
     * @inheritDoc
     */
    public function findNotifiedContactsById(int $hostId): array
    {
        if (! isset($this->notifiedContacts[$hostId])) {
            $this->fetchNotifiedContactsAndContactGroups($hostId);
        }

        return $this->notifiedContacts[$hostId];
    }

    /**
     * @inheritDoc
     */
    public function findNotifiedContactsByIdAndAccessGroups(int $hostId, array $accessGroups): array
    {
        if (! isset($this->notifiedContacts[$hostId])) {
            $this->fetchNotifiedContactsAndContactGroups($hostId, $accessGroups);
        }

        return $this->notifiedContacts[$hostId];
    }

    /**
     * @inheritDoc
     */
    public function findNotifiedContactGroupsById(int $hostId): array
    {
        if (! isset($this->notifiedContactGroups[$hostId])) {
            $this->fetchNotifiedContactsAndContactGroups($hostId);
        }

        return $this->notifiedContactGroups[$hostId];
    }

    /**
     * @inheritDoc
     */
    public function findNotifiedContactGroupsByIdAndAccessGroups(int $hostId, array $accessGroups): array
    {
        if (! isset($this->notifiedContactGroups[$hostId])) {
            $this->fetchNotifiedContactsAndContactGroups($hostId, $accessGroups);
        }

        return $this->notifiedContactGroups[$hostId];
    }

    /**
     * Initialize notified contacts and contactgroups for given host id.
     *
     * @param int $hostId
     * @param AccessGroup[] $accessGroups
     */
    private function fetchNotifiedContactsAndContactGroups(int $hostId, array $accessGroups = []): void
    {
        /**
         * Call to Legacy code to get the contacts and contactgroups
         * that will be notified for the Host regarding global
         * notification inheritance parameter.
         */
        $hostInstance = \Host::getInstance($this->dependencyInjector);

        [
            'contact' => $notifiedContactIds,
            'cg' => $notifiedContactGroupIds,
        ] = $hostInstance->getCgAndContacts($hostId);

        if ($accessGroups === []) {
            $this->notifiedContacts[$hostId] = $this->findContactsByIds($notifiedContactIds);
            $this->notifiedContactGroups[$hostId] = $this->findContactGroupsByIds($notifiedContactGroupIds);
        } else {
            $this->notifiedContacts[$hostId] = $this->findContactsByIdsAndAccessGroups(
                $notifiedContactIds,
                $accessGroups
            );
            $this->notifiedContactGroups[$hostId] = $this->findContactGroupsByIdsAndAccessGroups(
                $notifiedContactGroupIds,
                $accessGroups
            );
        }
    }
}
