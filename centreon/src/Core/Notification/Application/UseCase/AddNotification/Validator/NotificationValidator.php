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

namespace Core\Notification\Application\UseCase\AddNotification\Validator;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Contact\Application\Repository\ReadContactGroupRepositoryInterface;
use Core\Contact\Application\Repository\ReadContactRepositoryInterface;
use Core\Contact\Domain\Model\BasicContact;
use Core\Contact\Domain\Model\ContactGroup;
use Core\Notification\Application\Exception\NotificationException;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\TimePeriod\Application\Repository\ReadTimePeriodRepositoryInterface;
use Utility\Difference\BasicDifference;

class NotificationValidator
{
    use LoggerTrait;

    private ContactInterface $currentContact;

    public function __construct(
        private readonly ReadContactRepositoryInterface $contactRepository,
        private readonly ReadContactGroupRepositoryInterface $contactGroupRepository,
        private readonly ReadAccessGroupRepositoryInterface $accessGroupRepository,
        private readonly ReadTimePeriodRepositoryInterface $readTimePeriodRepository,
    ) {
    }

    /**
     * Validate that provided user and contact group ids exists.
     *
     * @param int[] $userIds
     * @param int[] $contactGroupsIds
     * @param ContactInterface $currentContact
     *
     * @throws NotificationException
     * @throws \Throwable
     */
    public function validateUsersAndContactGroups(
        array $userIds,
        array $contactGroupsIds,
        ContactInterface $currentContact
    ): void {
        if ($userIds === [] && $contactGroupsIds === []) {
            throw NotificationException::emptyArrayNotAllowed('users, contact groups');
        }
        $this->currentContact = $currentContact;
        if ($userIds !== []) {
            $this->validateUsers($userIds);
        }
        if ($contactGroupsIds !== []) {
            $this->validateContactGroups($contactGroupsIds);
        }
    }

    /**
     * Validate that provided time period id exists.
     *
     * @param int $timePeriodId
     *
     * @throws \Throwable|NotificationException
     */
    public function validateTimePeriod(int $timePeriodId): void {
        if (false === $this->readTimePeriodRepository->exists($timePeriodId)) {
            $this->error('Time period does not exist', ['timePeriodId' => $timePeriodId]);

            throw NotificationException::invalidId('timeperiodId');
        }
    }

    /**
     * Validate that provided user ids exists.
     *
     * @param int[] $contactIdsToValidate
     *
     * @throws \Throwable|NotificationException
     */
    private function validateUsers(array $contactIdsToValidate): void
    {
        $contactIdsToValidate = array_unique($contactIdsToValidate);

        if ($this->currentContact->isAdmin()) {
            $existingContactIds = $this->contactRepository->retrieveExistingContactIds($contactIdsToValidate);
        } else {
            $accessGroups = $this->accessGroupRepository->findByContact($this->currentContact);
            $existingContacts = $this->contactRepository->findByAccessGroupsAndUserAndRequestParameters(
                $accessGroups,
                $this->currentContact
            );
            $existingContactIds = array_map(fn (BasicContact $contact) => $contact->getId(), $existingContacts);
        }

        $contactDifference = new BasicDifference($contactIdsToValidate, $existingContactIds);
        $missingContact = $contactDifference->getRemoved();

        if ([] !== $missingContact) {
            $this->error(
                'Invalid ID(s) provided',
                ['propertyName' => 'users', 'propertyValues' => array_values($missingContact)]
            );

            throw NotificationException::invalidId('users');
        }
    }

    /**
     * Validate that provided contact group ids exists.
     *
     * @param int[] $contactGroupIds
     *
     * @throws \Throwable|NotificationException
     */
    private function validateContactGroups(array $contactGroupIds): void {
        $contactGroupIds = array_unique($contactGroupIds);

        if ($this->currentContact->isAdmin()) {
            $contactGroups = $this->contactGroupRepository->findByIds($contactGroupIds);
        } else {
            $accessGroups = $this->accessGroupRepository->findByContact($this->currentContact);
            $contactGroups = $this->contactGroupRepository->findByAccessGroupsAndUserAndRequestParameter(
                $accessGroups,
                $this->currentContact
            );
        }
        $existingContactGroupIds = array_map(fn (ContactGroup $contactGroup) => $contactGroup->getId(), $contactGroups);
        $difference = new BasicDifference($contactGroupIds, $existingContactGroupIds);
        $missingContactGroups = $difference->getRemoved();

        if ([] !== $missingContactGroups) {
            $this->error(
                'Invalid ID(s) provided',
                ['propertyName' => 'contactgroups', 'propertyValues' => array_values($missingContactGroups)]
            );

            throw NotificationException::invalidId('contactgroups');
        }
    }
}
