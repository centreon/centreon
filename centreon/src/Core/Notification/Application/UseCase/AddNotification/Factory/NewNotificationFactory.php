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

namespace Core\Notification\Application\UseCase\AddNotification\Factory;

use Centreon\Domain\Log\LoggerTrait;
use Core\Common\Domain\TrimmedString;
use Core\Notification\Application\Exception\NotificationException;
use Core\Notification\Application\Repository\ReadNotificationRepositoryInterface;
use Core\Notification\Domain\Model\ConfigurationTimePeriod;
use Core\Notification\Domain\Model\NewNotification;

class NewNotificationFactory
{
    use LoggerTrait;

    public function __construct(private ReadNotificationRepositoryInterface $notificationRepository)
    {
    }

    /**
     * Create New Notification.
     *
     * @param string $name
     * @param bool $isActivated
     * @param int $timeperiodId
     *
     * @throws NotificationException
     * @throws \Assert\AssertionFailedException
     *
     * @return NewNotification
     */
    public function create(string $name, bool $isActivated, int $timeperiodId): NewNotification
    {
        $this->assertNameDoesNotAlreadyExists($name);

        return new NewNotification(
            $name,
            new ConfigurationTimePeriod($timeperiodId, ''),
            $isActivated
        );
    }

    /**
     * Validate that a notification with this name doesn't already exist.
     *
     * @param string $name
     *
     * @throws NotificationException
     */
    private function assertNameDoesNotAlreadyExists(string $name): void
    {
        if ($this->notificationRepository->existsByName(new TrimmedString($name))) {
            $this->error('Notification name already exists', ['name' => $name]);

            throw NotificationException::nameAlreadyExists();
        }
    }
}
