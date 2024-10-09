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

namespace Core\Notification\Application\UseCase\DeleteNotifications;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\Repository\RepositoryException;
use Core\Application\Common\UseCase\{
    ErrorResponse,
    ForbiddenResponse,
    NoContentResponse,
    NotFoundResponse,
    ResponseStatusInterface
};
use Core\Notification\Application\Exception\NotificationException;
use Core\Notification\Application\Repository\WriteNotificationRepositoryInterface;
use Core\Notification\Domain\Model\ResponseCode;

final class DeleteNotifications
{
    use LoggerTrait;

    /**
     * @param ContactInterface $contact
     * @param WriteNotificationRepositoryInterface $writeRepository
     */
    public function __construct(
        private readonly ContactInterface $contact,
        private readonly WriteNotificationRepositoryInterface $writeRepository
    ) {
    }

    /**
     * @param DeleteNotificationsRequest $request
     * @param DeleteNotificationsPresenterInterface $presenter
     */
    public function __invoke(
        DeleteNotificationsRequest $request,
        DeleteNotificationsPresenterInterface $presenter
    ): void {
        try {
            if ($this->contactCanExecuteUseCase()) {
                $response = new DeleteNotificationsResponse();
                $results = [];

                foreach ($request->ids as $notificationId) {
                    try {
                        $statusResponse = $this->deleteNotification($notificationId);
                        $responseStatusDto = $this->createStatusResponseDto($statusResponse, $notificationId);
                        $results[] = $responseStatusDto;
                    } catch (\Throwable $ex) {
                        $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
                        $statusResponse = new ErrorResponse(NotificationException::errorWhileDeletingObject());
                        $responseStatusDto = $this->createStatusResponseDto($statusResponse, $notificationId);
                        $results[] = $responseStatusDto;
                    }
                }

                $response->results = $results;
            } else {
                $this->error(
                    "User doesn't have sufficient rights to delete notifications",
                    ['user_id' => $this->contact->getId()]
                );
                $response = new ForbiddenResponse(NotificationException::deleteNotAllowed());
            }
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $response = new ErrorResponse(NotificationException::errorWhileDeletingObject());
        }

        $presenter->presentResponse($response);
    }

    /**
     * @param int $notificationId
     *
     * @throws RepositoryException
     * @throws \Throwable
     *
     * @return ResponseStatusInterface
     */
    private function deleteNotification(int $notificationId): ResponseStatusInterface
    {
        $this->info('Deleting notification', ['id' => $notificationId]);
        if ($this->writeRepository->deleteNotification($notificationId) === 1) {
            return new NoContentResponse();
        }

        $this->error('Notification (%s) not found', ['id' => $notificationId]);

        return new NotFoundResponse('Notification');
    }

    /**
     * @return bool
     */
    private function contactCanExecuteUseCase(): bool
    {
        return $this->contact->hasTopologyRole(Contact::ROLE_CONFIGURATION_NOTIFICATIONS_READ_WRITE);
    }

    /**
     * @param ResponseStatusInterface $statusResponse
     * @param int $notificationId
     *
     * @return DeleteNotificationsStatusResponse
     */
    private function createStatusResponseDto(
        ResponseStatusInterface $statusResponse,
        int $notificationId
    ): DeleteNotificationsStatusResponse {
        $responseStatusDto = new DeleteNotificationsStatusResponse();
        $responseStatusDto->id = $notificationId;
        if ($statusResponse instanceof NoContentResponse) {
            $responseStatusDto->status = ResponseCode::OK;
            $responseStatusDto->message = null;
        } elseif ($statusResponse instanceof NotFoundResponse) {
            $responseStatusDto->status = ResponseCode::NotFound;
            $responseStatusDto->message = $statusResponse->getMessage();
        } else {
            $responseStatusDto->status = ResponseCode::Error;
            $responseStatusDto->message = NotificationException::errorWhileDeletingObject()->getMessage();
        }

        return $responseStatusDto;
    }
}
