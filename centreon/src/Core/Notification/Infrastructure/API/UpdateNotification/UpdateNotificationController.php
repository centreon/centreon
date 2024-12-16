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

namespace Core\Notification\Infrastructure\API\UpdateNotification;

use Centreon\Application\Controller\AbstractController;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Notification\Application\UseCase\UpdateNotification\UpdateNotification;
use Core\Notification\Application\UseCase\UpdateNotification\UpdateNotificationRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @phpstan-type _RequestArray array{
 *     name: string,
 *     timeperiod_id: int,
 *     users: int[],
 *     contactgroups: int[],
 *     resources: array<array{
 *         type: string,
 *         ids: int[],
 *         events: int,
 *         extra?: array{event_services?: int}
 *     }>,
 *     messages: array<array{
 *         channel: string,
 *         subject: string,
 *         message: string,
 *         formatted_message: string,
 *     }>,
 *     is_activated?: bool,
 * }
 */
final class UpdateNotificationController extends AbstractController
{
    use LoggerTrait;

    public function __invoke(
        int $notificationId,
        Request $request,
        UpdateNotification $useCase,
        UpdateNotificationPresenter $presenter
    ): Response {
        $this->denyAccessUnlessGrantedForApiConfiguration();

        try {
            /** @var _RequestArray $dataSent */
            $dataSent = $this->validateAndRetrieveDataSent($request, __DIR__ . '/UpdateNotificationSchema.json');

            $updateNotificationRequest = $this->createUpdateNotificationRequest($notificationId, $dataSent);
            $useCase($updateNotificationRequest, $presenter);
        } catch (\InvalidArgumentException $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->setResponseStatus(new InvalidArgumentResponse($ex));
        }

        return $presenter->show();
    }

    /**
     * Create DTO.
     *
     * @param int $notificationId
     * @param _RequestArray $dataSent
     *
     * @return UpdateNotificationRequest
     */
    public function createUpdateNotificationRequest(int $notificationId, array $dataSent): UpdateNotificationRequest
    {
        $request = new UpdateNotificationRequest();
        $request->id = $notificationId;
        $request->name = $dataSent['name'];
        $request->users = $dataSent['users'];
        $request->contactGroups = $dataSent['contactgroups'];
        foreach ($dataSent['messages'] as $messageData) {
            $request->messages[] = [
                'channel' => $messageData['channel'],
                'subject' => $messageData['subject'],
                'message' => $messageData['message'],
                'formatted_message' => $messageData['formatted_message'],
            ];
        }
        foreach ($dataSent['resources'] as $resourceData) {
            $request->resources[] = [
                'type' => $resourceData['type'],
                'ids' => $resourceData['ids'],
                'events' => $resourceData['events'],
                'includeServiceEvents' => $resourceData['extra']['event_services']
                    ?? 0,
            ];
        }
        $request->timePeriodId = $dataSent['timeperiod_id'];
        $request->isActivated = $dataSent['is_activated'] ?? true;

        return $request;
    }
}
