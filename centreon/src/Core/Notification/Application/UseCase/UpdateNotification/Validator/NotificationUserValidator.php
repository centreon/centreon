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
 * For more information : user@centreon.com
 *
 */

declare(strict_types=1);

namespace Core\Notification\Application\UseCase\UpdateNotification\Validator;

use Centreon\Domain\Contact\Interfaces\ContactRepositoryInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Notification\Application\Exception\NotificationException;
use Utility\Difference\BasicDifference;

class NotificationUserValidator
{
    use LoggerTrait;

    /**
     * Validate that provided user ids exists.
     *
     * @param int[] $userIds
     * @param ContactRepositoryInterface $contactRepository
     *
     * @throws \Throwable|NotificationException
     */
    public function validate(array $userIds, ContactRepositoryInterface $contactRepository): void
    {
        $userIds = array_unique($userIds);
        if ($userIds === []) {
            throw NotificationException::emptyArrayNotAllowed('user');
        }

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
}
