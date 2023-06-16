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

use Core\Notification\Domain\Model\ResponseCode;
use Core\Application\Common\UseCase\AbstractPresenter;
use Core\Application\Common\UseCase\MultiStatusResponse;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Notification\Application\UseCase\DeleteNotifications\DeleteNotificationsResponse;
use Core\Notification\Application\UseCase\DeleteNotifications\DeleteNotificationsStatusResponse;
use Core\Notification\Application\UseCase\DeleteNotifications\DeleteNotificationsPresenterInterface;
use Symfony\Component\HttpFoundation\Response;

final class DeleteNotificationsPresenter extends AbstractPresenter implements DeleteNotificationsPresenterInterface
{
    public function __construct(
        private readonly RequestParametersInterface $requestParameters,
        protected PresenterFormatterInterface $presenterFormatter
    ) {
        parent::__construct($presenterFormatter);
    }

    /**
     * @inheritDoc
     */
    public function presentResponse(DeleteNotificationsResponse|ResponseStatusInterface $response): void
    {
        if ($response instanceof DeleteNotificationsResponse) {
            $results = array_map(function (DeleteNotificationsStatusResponse $notificationDto) {
                return [
                    'href' => $notificationDto->href,
                    'status' => $this->enumToIntConverter($notificationDto->status),
                    'message' => $notificationDto->message
                ];
            }, $response->results);

            $meta = $this->requestParameters->toArray();


            // $resp = [
            //     'toto' => array_map(function (DeleteNotificationsStatusResponse $notificationDto) {
            //         return [
            //             'href' => $notificationDto->href,
            //             'status' => $this->enumToIntConverter($notificationDto->status),
            //             'message' => $notificationDto->message
            //         ];
            //     }, $response->results),
            //     'meta' => $this->requestParameters->toArray()
            // ];

            $this->present(new MultiStatusResponse($results, $meta));
        } else {
            $this->setResponseStatus($response);
        }
    }

    private function enumToIntConverter(ResponseCode $code): int
    {
        return match ($code) {
            ResponseCode::OK => Response::HTTP_NO_CONTENT,
            ResponseCode::NotFound => Response::HTTP_NOT_FOUND,
            ResponseCode::Error => Response::HTTP_INTERNAL_SERVER_ERROR
        };
    }
}
