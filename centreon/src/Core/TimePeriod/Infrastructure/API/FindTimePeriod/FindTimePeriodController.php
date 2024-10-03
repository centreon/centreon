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

namespace Core\TimePeriod\Infrastructure\API\FindTimePeriod;

use Centreon\Application\Controller\AbstractController;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Infrastructure\Common\Api\StandardPresenter;
use Core\TimePeriod\Application\UseCase\FindTimePeriod\FindTimePeriod;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

final class FindTimePeriodController extends AbstractController
{
    /**
     * @param FindTimePeriod $useCase
     * @param StandardPresenter $presenter
     * @param int $id
     *
     * @throws ExceptionInterface
     *
     * @return Response
     */
    public function __invoke(FindTimePeriod $useCase, StandardPresenter $presenter, int $id): Response
    {
        $this->denyAccessUnlessGrantedForApiConfiguration();
        $response = $useCase($id);

        if ($response instanceof ResponseStatusInterface) {
            return $this->createResponse($response);
        }

        return JsonResponse::fromJsonString(
            $presenter->present($response, ['groups' => ['TimePeriod:Read']])
        );
    }
}
