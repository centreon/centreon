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

namespace Core\Notification\Application\UseCase\UpdateNotification\Factory;

use Centreon\Domain\Log\LoggerTrait;
use Core\Common\Domain\TrimmedString;
use Core\Notification\Application\Exception\NotificationException;
use Core\Notification\Application\Repository\ReadNotificationRepositoryInterface;
use Core\Notification\Application\UseCase\UpdateNotification\UpdateNotificationRequest;
use Core\Notification\Domain\Model\ConfigurationTimePeriod;
use Core\Notification\Domain\Model\Notification;

final class NotificationFactory
{
    use LoggerTrait;

    public function __construct(private readonly ReadNotificationRepositoryInterface $repository)
    {
    }

    /**
     * Create an instance of Notification.
     *
     * @param UpdateNotificationRequest $request
     *
     * @throws NotificationException
     *
     * @return Notification
     */
    public function create(UpdateNotificationRequest $request): Notification
    {
        $this->assertNameDoesNotAlreadyExists($request->name, $request->id);

        return new Notification(
            $request->id,
            $request->name,
            new ConfigurationTimePeriod($request->timeperiodId, ''),
            $request->isActivated
        );
    }

    /**
     * Validate that a notification with this name doesn't already exist.
     *
     * @param string $name
     * @param int $id
     *
     * @throws NotificationException
     */
    private function assertNameDoesNotAlreadyExists(string $name, int $id): void
    {
        $notification = $this->repository->findByName(new TrimmedString($name));
        if ($notification !== null && $notification->getId() !== $id) {
            $this->error(
                'Notification name already exists for another notification',
                ['name' => $name, 'existing_notification_id' => $notification->getId()]
            );

            throw NotificationException::nameAlreadyExists();
        }
    }
}