<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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

declare(strict_types = 1);

namespace Core\User\Infrastructure\API\FindUserPermissions;

use Centreon\Application\Controller\AbstractController;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Infrastructure\Common\Api\StandardPresenter;
use Core\User\Application\UseCase\FindUserPermissions\FindUserPermissions;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

final class FindUserPermissionsController extends AbstractController
{
    /**
     * @param StandardPresenter $presenter
     * @param FindUserPermissions $useCase
     * @param ContactInterface $user
     *
     * @throws ExceptionInterface
     *
     * @return Response
     */
    public function __invoke(
        StandardPresenter $presenter,
        FindUserPermissions $useCase,
        ContactInterface $user
    ): Response
    {
        $response = $useCase($user);

        if ($response instanceof ResponseStatusInterface) {
            return $this->createResponse($response);
        }

        return JsonResponse::fromJsonString(
            $presenter->present($response, ['groups' => ['FindUserPermissions:Read', 'NotEmptyString:Read']])
        );
    }
}
