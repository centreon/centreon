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

namespace Tests\Core\Notification\Application\UseCase\DeleteNotifications;

use Centreon\Domain\Log\LoggerTrait;
use Symfony\Component\HttpFoundation\Response;
use Core\Notification\Domain\Model\ResponseCode;
use Core\Application\Common\UseCase\AbstractPresenter;
use Core\Application\Common\UseCase\MultiStatusResponse;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Infrastructure\Common\Api\Router;
use Core\Notification\Application\UseCase\DeleteNotifications\DeleteNotificationsResponse;
use Core\Notification\Application\UseCase\DeleteNotifications\DeleteNotificationsStatusResponse;
use Core\Notification\Application\UseCase\DeleteNotifications\DeleteNotificationsPresenterInterface;

class DeleteNotificationsPresenterStub extends AbstractPresenter implements DeleteNotificationsPresenterInterface
{
    private const HREF = 'centreon/api/latest/configuration/notifications/';

    /** @var ResponseStatusInterface */
    public ResponseStatusInterface $response;

    public function presentResponse(DeleteNotificationsResponse|ResponseStatusInterface $response): void
    {
        if ($response instanceof DeleteNotificationsResponse) {
            $multiStatusResponse = [
                'results' => array_map(fn(DeleteNotificationsStatusResponse $notificationDto) => [
                    'href' => self::HREF . $notificationDto->id,
                    'status' => $this->enumToIntConverter($notificationDto->status),
                    'message' => $notificationDto->message,
                ], $response->results),
            ];

            $this->response = new MultiStatusResponse($multiStatusResponse);
        } else {
            $this->response = $response;
        }
    }

    /**
     * @param ResponseCode $code
     *
     * @return int
     */
    private function enumToIntConverter(ResponseCode $code): int
    {
        return match ($code) {
            ResponseCode::OK => Response::HTTP_NO_CONTENT,
            ResponseCode::NotFound => Response::HTTP_NOT_FOUND,
            ResponseCode::Error => Response::HTTP_INTERNAL_SERVER_ERROR
        };
    }
}
