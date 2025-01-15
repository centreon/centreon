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

namespace Core\Notification\Application\UseCase\PartialUpdateNotification;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Repository\Interfaces\DataStorageEngineInterface;
use Core\Application\Common\UseCase\{
    ErrorResponse,
    ForbiddenResponse,
    NoContentResponse,
    NotFoundResponse,
    ResponseStatusInterface
};
use Core\Common\Application\Type\NoValue;
use Core\Notification\Application\Exception\NotificationException;
use Core\Notification\Application\Repository\{
    ReadNotificationRepositoryInterface,
    WriteNotificationRepositoryInterface
};
use Core\Notification\Domain\Model\Notification;

final class PartialUpdateNotification
{
    use LoggerTrait;

    /**
     * @param ContactInterface $contact
     * @param ReadNotificationRepositoryInterface $readRepository
     * @param WriteNotificationRepositoryInterface $writeRepository
     * @param DataStorageEngineInterface $dataStorageEngine
     */
    public function __construct(
        private readonly ContactInterface $contact,
        private readonly ReadNotificationRepositoryInterface $readRepository,
        private readonly WriteNotificationRepositoryInterface $writeRepository,
        private readonly DataStorageEngineInterface $dataStorageEngine
    ) {
    }

    /**
     * @param PartialUpdateNotificationRequest $request
     * @param PartialUpdateNotificationPresenterInterface $presenter
     * @param int $notificationId
     */
    public function __invoke(
        PartialUpdateNotificationRequest $request,
        PartialUpdateNotificationPresenterInterface $presenter,
        int $notificationId
    ): void {
        try {
            if ($this->contactCanExecuteUseCase()) {
                $response = $this->partiallyUpdateNotification($request, $notificationId);
            } else {
                $this->error(
                    "User doesn't have sufficient rights to delete notifications",
                    ['user_id' => $this->contact->getId()]
                );
                $response = new ForbiddenResponse(NotificationException::partialUpdateNotAllowed()->getMessage());
            }
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $response = new ErrorResponse(NotificationException::errorWhilePartiallyUpdatingObject());
        }

        $presenter->presentResponse($response);
    }

    /**
     * @return bool
     */
    private function contactCanExecuteUseCase(): bool
    {
        return $this->contact->hasTopologyRole(Contact::ROLE_CONFIGURATION_NOTIFICATIONS_READ_WRITE);
    }

    /**
     * @param PartialUpdateNotificationRequest $request
     * @param int $notificationId
     *
     * @throws \Throwable
     *
     * @return ResponseStatusInterface
     */
    private function partiallyUpdateNotification(
        PartialUpdateNotificationRequest $request,
        int $notificationId
    ): ResponseStatusInterface {
        if (! ($notification = $this->readRepository->findById($notificationId))) {
            $this->error('Notification not found', ['notification_id' => $notificationId]);

            return new NotFoundResponse('Notification');
        }
        $this->updatePropertiesInTransaction($request, $notification);

        return new NoContentResponse();
    }

    /**
     * @param PartialUpdateNotificationRequest $request
     * @param Notification $notification
     *
     * @throws \Throwable
     */
    private function updatePropertiesInTransaction(
        PartialUpdateNotificationRequest $request,
        Notification $notification
    ): void {
        try {
            $this->dataStorageEngine->startTransaction();
            $this->updateNotification($request, $notification);
            $this->dataStorageEngine->commitTransaction();
        } catch (\Throwable $ex) {
            $this->error('Rollback of \'PartialUpdateNotification\' transaction');
            $this->dataStorageEngine->rollbackTransaction();

            throw $ex;
        }
    }

    /**
     * @param PartialUpdateNotificationRequest $request
     * @param Notification $notification
     *
     * @throws \Throwable
     */
    private function updateNotification(PartialUpdateNotificationRequest $request, Notification $notification): void
    {
        $this->info(
            'PartialUpdateNotification: update is_activated',
            ['notification_id' => $notification->getId(), 'is_activated' => $request->isActivated]
        );

        if ($request->isActivated instanceof NoValue) {
            $this->info('is_activated property is not provided. Nothing to update');

            return;
        }
        $notification->setIsActivated($request->isActivated);

        $this->writeRepository->updateNotification($notification);
    }
}
