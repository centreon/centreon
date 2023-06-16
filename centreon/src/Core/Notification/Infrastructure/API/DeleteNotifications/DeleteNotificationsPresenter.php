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

namespace Core\Notification\Infrastructure\API\DeleteNotifications;

use Core\Application\Common\UseCase\{AbstractPresenter, MultiStatusResponse, ResponseStatusInterface};
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Notification\Application\UseCase\DeleteNotifications\{
    DeleteNotificationsPresenterInterface,
    DeleteNotificationsResponse,
    DeleteNotificationsStatusResponse
};
use Core\Notification\Domain\Model\ResponseCode;
use Symfony\Component\HttpFoundation\Response;

final class DeleteNotificationsPresenter extends AbstractPresenter implements DeleteNotificationsPresenterInterface
{
    /**
     * @param PresenterFormatterInterface $presenterFormatter
     */
    public function __construct(protected PresenterFormatterInterface $presenterFormatter)
    {
        parent::__construct($presenterFormatter);
    }

    /**
     * @inheritDoc
     */
    public function presentResponse(DeleteNotificationsResponse|ResponseStatusInterface $response): void
    {
        if ($response instanceof DeleteNotificationsResponse) {
            $multiStatusResponse = [
                'results' => array_map(function (DeleteNotificationsStatusResponse $notificationDto) {
                    return [
                        'href' => $notificationDto->href,
                        'status' => $this->enumToIntConverter($notificationDto->status),
                        'message' => $notificationDto->message,
                    ];
                }, $response->results),
            ];

            $this->present(new MultiStatusResponse($multiStatusResponse));
        } else {
            $this->setResponseStatus($response);
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
