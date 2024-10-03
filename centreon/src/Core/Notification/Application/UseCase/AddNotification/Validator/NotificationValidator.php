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
use Centreon\Domain\Contact\Interfaces\ContactRepositoryInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Contact\Application\Repository\ReadContactGroupRepositoryInterface;
use Core\Contact\Domain\Model\ContactGroup;
use Core\Notification\Application\Exception\NotificationException;
use Core\TimePeriod\Application\Repository\ReadTimePeriodRepositoryInterface;
use Utility\Difference\BasicDifference;

class NotificationValidator
{
    use LoggerTrait;

    /**
     * Validate that provided user and contactgroup ids exists.
     *
     * @param int[] $userIds
     * @param int[] $contactGroupsIds
     * @param ContactRepositoryInterface $contactRepository
     * @param ReadContactGroupRepositoryInterface $contactGroupRepository
     * @param ContactInterface $user
     *
     * @throws \Throwable|NotificationException
     */
    public function validateUsersAndContactGroups(
        array $userIds,
        array $contactGroupsIds,
        ContactRepositoryInterface $contactRepository,
        ReadContactGroupRepositoryInterface $contactGroupRepository,
        ContactInterface $user
    ): void {
        if (empty($userIds) && empty($contactGroupsIds)) {
            throw NotificationException::emptyArrayNotAllowed('users, contactgroups');
        }
        if (! empty($userIds)) {
            $this->validateUsers($userIds, $contactRepository);
        }
        if (! empty($contactGroupsIds)) {
            $this->validateContactGroups($contactGroupsIds, $contactGroupRepository, $user);
        }
    }

    /**
     * Validate that provided time period id exists.
     *
     * @param int $timePeriodId
     * @param ReadTimePeriodRepositoryInterface $readTimePeriodRepository
     *
     * @throws \Throwable|NotificationException
     */
    public function validateTimePeriod(
        int $timePeriodId,
        ReadTimePeriodRepositoryInterface $readTimePeriodRepository
    ): void {
        if (false === $readTimePeriodRepository->exists($timePeriodId)) {
            $this->error('Time period does not exist', ['timePeriodId' => $timePeriodId]);

            throw NotificationException::invalidId('timeperiodId');
        }
    }

    /**
     * Validate that provided user ids exists.
     *
     * @param int[] $userIds
     * @param ContactRepositoryInterface $contactRepository
     *
     * @throws \Throwable|NotificationException
     */
    private function validateUsers(array $userIds, ContactRepositoryInterface $contactRepository): void
    {
        $userIds = array_unique($userIds);

        $existingUsers = $contactRepository->exist($userIds);
        $difference = new BasicDifference($userIds, $existingUsers);
        $missingUsers = $difference->getRemoved();

        if ([] !== $missingUsers) {
            $this->error(
                'Invalid ID(s) provided',
                ['propertyName' => 'users', 'propertyValues' => array_values($missingUsers)]
            );

            throw NotificationException::invalidId('users');
        }
    }

    /**
     * Validate that provided contactgroup ids exists.
     *
     * @param int[] $contactGroupIds
     * @param ReadContactGroupRepositoryInterface $contactGroupRepository
     * @param ContactInterface $user
     *
     * @throws \Throwable|NotificationException
     */
    private function validateContactGroups(
        array $contactGroupIds,
        ReadContactGroupRepositoryInterface $contactGroupRepository,
        ContactInterface $user
    ): void {
        $contactGroupIds = array_unique($contactGroupIds);

        if ($user->isAdmin()) {
            $contactGroups = $contactGroupRepository->findByIds($contactGroupIds);
        } else {
            $contactGroups = $contactGroupRepository->findByIdsAndUserId($contactGroupIds, $user->getId());
        }
        $existingContactGroups = array_map(fn (ContactGroup $contactgroup) => $contactgroup->getId(), $contactGroups);
        $difference = new BasicDifference($contactGroupIds, $existingContactGroups);
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
