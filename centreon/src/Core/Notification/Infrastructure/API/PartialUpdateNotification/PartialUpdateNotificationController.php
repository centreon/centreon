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

namespace Core\Notification\Infrastructure\API\PartialUpdateNotification;

use Centreon\Application\Controller\AbstractController;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\{ErrorResponse, InvalidArgumentResponse};
use Core\Notification\Application\Exception\NotificationException;
use Core\Notification\Application\UseCase\PartialUpdateNotification\{
    PartialUpdateNotification,
    PartialUpdateNotificationRequest
};
use Symfony\Component\HttpFoundation\{Request, Response};

final class PartialUpdateNotificationController extends AbstractController
{
    use LoggerTrait;

    /**
     * @param Request $request
     * @param PartialUpdateNotification $useCase
     * @param PartialUpdateNotificationPresenter $presenter
     * @param int $notificationId
     *
     * @return Response
     */
    public function __invoke(
        Request $request,
        PartialUpdateNotification $useCase,
        PartialUpdateNotificationPresenter $presenter,
        int $notificationId
    ): Response {
        $this->denyAccessUnlessGrantedForApiConfiguration();

        try {
            $data = $this->validateAndRetrieveDataSent($request, __DIR__ . '/PartialUpdateNotificationSchema.json');
            /** @var array{is_activated?: bool} $requestDto */
            $requestDto = new PartialUpdateNotificationRequest();

            if (\array_key_exists('is_activated', $data)) {
                $requestDto->isActivated = $data['is_activated'];
            }

            $useCase($requestDto, $presenter, $notificationId);
        } catch (\InvalidArgumentException $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->setResponseStatus(new InvalidArgumentResponse($ex));
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->setResponseStatus(
                new ErrorResponse(NotificationException::errorWhilePartiallyUpdatingObject())
            );
        }

        return $presenter->show();
    }
}
