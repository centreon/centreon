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

use Centreon\Domain\Log\LoggerTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Centreon\Application\Controller\AbstractController;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Notification\Application\UseCase\DeleteNotifications\DeleteNotifications;
use Core\Notification\Application\UseCase\DeleteNotifications\DeleteNotificationsRequest;

final class DeleteNotificationsController extends AbstractController
{
    use LoggerTrait;

    /**
     * @param Request $request
     * @param DeleteNotifications $useCase
     * @param DeleteNotificationsPresenter $presenter
     *
     * @return Response
     */
    public function __invoke(
        Request $request,
        DeleteNotifications $useCase,
        DeleteNotificationsPresenter $presenter
    ): Response {
        $this->denyAccessUnlessGrantedForApiConfiguration();

        try {
            /**
             * @var array{
             *  ids: int[]
             * } $data
             */
            $data = $this->validateAndRetrieveDataSent($request, __DIR__ . '/DeleteNotificationsSchema.json');
        } catch (\InvalidArgumentException $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->setResponseStatus(new InvalidArgumentResponse($ex));

            return $presenter->show();
        }

        $requestDto = $this->createRequestDto($data);
        $useCase($requestDto, $presenter);

        return $presenter->show();
    }

    /**
     * @param array{
     *  ids: int[]
     * } $data
     *
     * @return DeleteNotificationsRequest
     */
    private function createRequestDto(array $data): DeleteNotificationsRequest
    {
        $requestDto = new DeleteNotificationsRequest();
        $requestDto->ids = $data['ids'];

        return $requestDto;
    }
}
