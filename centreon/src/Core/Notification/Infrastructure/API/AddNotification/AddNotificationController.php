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

namespace Core\Notification\Infrastructure\API\AddNotification;

use Centreon\Application\Controller\AbstractController;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Notification\Application\UseCase\AddNotification\AddNotification;
use Core\Notification\Application\UseCase\AddNotification\AddNotificationRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class AddNotificationController extends AbstractController
{
    use LoggerTrait;

    /**
     * @param Request $request
     * @param AddNotification $useCase
     * @param AddNotificationPresenter $presenter
     *
     * @throws AccessDeniedException
     *
     * @return Response
     */
    public function __invoke(
        Request $request,
        AddNotification $useCase,
        AddNotificationPresenter $presenter
    ): Response {
        $this->denyAccessUnlessGrantedForApiConfiguration();

        try {
            /** @var array{
             *     name: string,
             *     timeperiod_id: int,
             *     users: int[],
             *     contactgroups: int[],
             *     resources: array<array{
             *         type:string,
             *         ids:int[],
             *         events:int,
             *         extra?:array{event_services?: int}
             *     }>,
             *     messages: array<array{
             *         channel:string,
             *         subject:string,
             *         message:string,
             *         formatted_message:string
             *     }>,
             *     is_activated?: bool,
             * } $data
             */
            $data = $this->validateAndRetrieveDataSent($request, __DIR__ . '/AddNotificationSchema.json');
        } catch (\InvalidArgumentException $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->setResponseStatus(new InvalidArgumentResponse($ex));

            return $presenter->show();
        }

        $notificationRequest = $this->createRequestDto($data);
        $useCase($notificationRequest, $presenter);

        return $presenter->show();
    }

    /**
     * @param array{
     *     name: string,
     *     timeperiod_id: int,
     *     users: int[],
     *     contactgroups: int[],
     *     resources: array<array{
     *         type:string,
     *         ids:int[],
     *         events:int,
     *         extra?:array{event_services?: int}
     *     }>,
     *     messages: array<array{
     *         channel:string,
     *         subject:string,
     *         message:string,
     *         formatted_message:string
     *     }>,
     *     is_activated?: bool,
     * } $data
     *
     * @return AddNotificationRequest
     */
    private function createRequestDto(array $data): AddNotificationRequest
    {
        $notificationRequest = new AddNotificationRequest();
        $notificationRequest->name = $data['name'];
        $notificationRequest->timePeriodId = $data['timeperiod_id'];
        $notificationRequest->isActivated = $data['is_activated'] ?? true;
        $notificationRequest->users = $data['users'];
        $notificationRequest->contactGroups = $data['contactgroups'];
        foreach ($data['messages'] as $messageData) {
            $notificationRequest->messages[] = [
                'channel' => $messageData['channel'],
                'subject' => $messageData['subject'],
                'message' => $messageData['message'],
                'formatted_message' => $messageData['formatted_message'],
            ];
        }
        foreach ($data['resources'] as $resourceData) {
            $notificationRequest->resources[] = [
                'type' => $resourceData['type'],
                'ids' => $resourceData['ids'],
                'events' => $resourceData['events'],
                'includeServiceEvents' => $resourceData['extra']['event_services']
                    ?? 0,
            ];
        }

        return $notificationRequest;
    }
}
